<?php

declare(strict_types=1);

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\NotificationQueue;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\Notifications\ReminderMessageBuilder;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use App\Support\Fel\FelDocumentPdfUrls;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Envía comprobantes electrónicos (CPE) por WhatsApp con adjuntos seleccionables.
 */
final class FelDocumentWhatsAppSender
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppSessionSync $sessionSync,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    /**
     * @return array{sent: int, message_ids: list<string|null>}
     */
    public function send(
        FelDocument $documento,
        Tenant $tenant,
        string $chatId,
        string $recipientName,
        ClinicSetting $clinic,
        bool $pdfTicket,
        bool $pdfA4,
        bool $xml,
        bool $cdr,
    ): array {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('WhatsApp (OpenWA) no está configurado.');
        }

        $session = $this->resolveReadySession($tenant);
        if ($session === null) {
            throw new RuntimeException('La sesión WhatsApp de la clínica no está conectada.');
        }

        $attachments = $this->resolveAttachments($documento, $pdfTicket, $pdfA4, $xml, $cdr);
        if ($attachments === []) {
            throw new RuntimeException('No hay archivos disponibles para los formatos seleccionados.');
        }

        $clinicName = $this->messages->clinicDisplayName($clinic);
        $tipoLabel = FelSerie::labelTipo($documento->tipo_comprobante);
        $fecha = $documento->emitido_at
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
        $totalFormatted = number_format((float) $documento->total, 2, '.', ',').' '.$documento->moneda;

        $intro = $this->messages->felDocumento(
            $clinicName,
            $recipientName,
            $documento->numero_completo,
            $tipoLabel,
            $totalFormatted,
            $fecha,
        );

        $sessionId = (string) $session->openwa_session_id;
        $messageIds = [];
        $sent = 0;
        $isFirstAttachment = true;

        foreach ($attachments as $attachment) {
            try {
                $result = $this->sendAttachment(
                    sessionId: $sessionId,
                    chatId: $chatId,
                    attachment: $attachment,
                    caption: $isFirstAttachment ? $intro : null,
                );

                $isFirstAttachment = false;
                $messageIds[] = isset($result['messageId']) ? (string) $result['messageId'] : null;
                $sent++;

                usleep(600_000);
            } catch (Throwable $e) {
                Log::warning('Adjunto WhatsApp de CPE falló', [
                    'fel_document_id' => $documento->id,
                    'tipo' => $attachment['tipo'],
                    'filename' => $attachment['filename'],
                    'source_url' => $attachment['url'] ?? null,
                    'error' => $e->getMessage(),
                ]);

                throw new RuntimeException(
                    'No se pudo enviar '.$attachment['label'].': '.$e->getMessage(),
                    0,
                    $e,
                );
            }
        }

        $this->recordSent(
            chatId: $chatId,
            recipientName: $recipientName,
            cuerpo: $intro."\n\n[adjuntos: ".implode(', ', array_column($attachments, 'label')).']',
            documentoId: $documento->id,
            messageId: $messageIds[0] ?? null,
        );

        return ['sent' => $sent, 'message_ids' => $messageIds];
    }

    /**
     * @return list<array{tipo: string, label: string, filename: string, mimetype: string, url?: string, binary?: string}>
     */
    private function resolveAttachments(
        FelDocument $documento,
        bool $pdfTicket,
        bool $pdfA4,
        bool $xml,
        bool $cdr,
    ): array {
        $safeNumero = preg_replace('/[^A-Za-z0-9_-]+/', '-', $documento->numero_completo) ?: 'comprobante';
        $attachments = [];

        if ($pdfTicket && filled($documento->url_pdf)) {
            $attachments[] = [
                'tipo' => 'pdf_ticket',
                'label' => 'PDF',
                'filename' => $safeNumero.'.pdf',
                'mimetype' => 'application/pdf',
                'url' => (string) $documento->url_pdf,
            ];
        }

        if ($pdfA4) {
            $urlA4 = FelDocumentPdfUrls::pdfA4FromTicket($documento->url_pdf);
            if ($urlA4 !== null && $urlA4 !== '') {
                $attachments[] = [
                    'tipo' => 'pdf_a4',
                    'label' => 'PDF A4',
                    'filename' => $safeNumero.'-a4.pdf',
                    'mimetype' => 'application/pdf',
                    'url' => $urlA4,
                ];
            }
        }

        if ($xml && filled($documento->url_xml)) {
            $attachments[] = [
                'tipo' => 'xml',
                'label' => 'XML',
                'filename' => $safeNumero.'.xml',
                'mimetype' => 'application/xml',
                'url' => (string) $documento->url_xml,
            ];
        }

        if ($cdr && filled($documento->url_cdr)) {
            $attachments[] = [
                'tipo' => 'cdr',
                'label' => 'CDR',
                'filename' => 'R-'.$safeNumero.'.xml',
                'mimetype' => 'application/xml',
                'url' => (string) $documento->url_cdr,
            ];
        }

        return $attachments;
    }

    /**
     * @param  array{tipo: string, label: string, filename: string, mimetype: string, url?: string}  $attachment
     * @return array<string, mixed>
     */
    private function sendAttachment(
        string $sessionId,
        string $chatId,
        array $attachment,
        ?string $caption,
    ): array {
        $url = trim((string) ($attachment['url'] ?? ''));

        // 1) URL APISUNAT directa (OpenWA descarga; mismo patrón que ventas con URL remota).
        if ($url !== '') {
            try {
                return $this->client->sendDocument(
                    sessionId: $sessionId,
                    chatId: $chatId,
                    url: $url,
                    filename: $attachment['filename'],
                    mimetype: $attachment['mimetype'],
                    caption: $caption,
                );
            } catch (Throwable $urlError) {
                Log::notice('CPE WhatsApp: URL APISUNAT falló; reintento vía storage temporal', [
                    'tipo' => $attachment['tipo'],
                    'url' => $url,
                    'error' => $urlError->getMessage(),
                ]);
            }
        }

        // 2) Descarga en VetSaaS + URL temporal pública (igual que ticket de venta).
        $binary = $this->downloadAttachment($attachment);

        return $this->client->sendDocument(
            sessionId: $sessionId,
            chatId: $chatId,
            binaryContent: $binary,
            filename: $attachment['filename'],
            mimetype: $attachment['mimetype'],
            caption: $caption,
        );
    }

    /**
     * @param  array{url?: string, binary?: string, label: string, tipo?: string, filename?: string}  $attachment
     */
    private function downloadAttachment(array $attachment): string
    {
        if (isset($attachment['binary']) && is_string($attachment['binary']) && $attachment['binary'] !== '') {
            return $attachment['binary'];
        }

        $url = trim((string) ($attachment['url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('No hay URL para descargar '.$attachment['label'].'.');
        }

        try {
            $response = Http::timeout(45)->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Error al descargar '.$attachment['label'].': '.$e->getMessage(),
                0,
                $e,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'HTTP '.$response->status().' al descargar '.$attachment['label'].'.',
            );
        }

        $body = (string) $response->body();
        if ($body === '') {
            throw new RuntimeException('El archivo '.$attachment['label'].' está vacío.');
        }

        $filename = (string) ($attachment['filename'] ?? '');
        if (
            ($attachment['tipo'] ?? '') === 'pdf_ticket'
            || str_ends_with(strtolower($filename), '.pdf')
        ) {
            if (! str_starts_with($body, '%PDF')) {
                throw new RuntimeException(
                    'El PDF de '.$attachment['label'].' no es válido. Verifica la URL en APISUNAT.',
                );
            }
        }

        return $body;
    }

    private function resolveReadySession(Tenant $tenant): ?TenantWhatsAppSession
    {
        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($session === null) {
            $session = $this->sessionSync->ensureForTenant($tenant);
        } elseif (! $session->isReady()) {
            $session = $this->sessionSync->refresh($session);
        }

        return $session instanceof TenantWhatsAppSession && $session->isReady()
            ? $session
            : null;
    }

    private function recordSent(
        string $chatId,
        string $recipientName,
        string $cuerpo,
        string $documentoId,
        ?string $messageId,
    ): void {
        try {
            NotificationQueue::query()->create([
                'tipo' => 'documento_comprobante',
                'canal' => NotificationQueue::CANAL_WHATSAPP,
                'destinatario' => $chatId,
                'destinatario_nombre' => $recipientName,
                'cuerpo' => $cuerpo,
                'referencia_tipo' => 'fel_document',
                'referencia_id' => $documentoId,
                'dedupe_key' => 'documento_comprobante:'.$documentoId.':'.now()->timestamp,
                'enviar_at' => now(),
                'prioridad' => 3,
                'estado' => NotificationQueue::ESTADO_ENVIADO,
                'intentos' => 1,
                'max_intentos' => (int) config('openwa.max_attempts', 3),
                'ultimo_intento_at' => now(),
                'proveedor_msg_id' => $messageId,
            ]);
        } catch (Throwable) {
            // best-effort
        }
    }
}
