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

        $introResult = $this->client->sendText($sessionId, $chatId, $intro);
        $messageIds[] = isset($introResult['messageId']) ? (string) $introResult['messageId'] : null;
        $sent++;

        $safeNumero = preg_replace('/[^A-Za-z0-9_-]+/', '-', $documento->numero_completo) ?: 'comprobante';

        foreach ($attachments as $attachment) {
            try {
                $result = $this->client->sendDocument(
                    sessionId: $sessionId,
                    chatId: $chatId,
                    url: $attachment['url'] ?? null,
                    binaryContent: $attachment['binary'] ?? null,
                    filename: $attachment['filename'],
                    mimetype: $attachment['mimetype'],
                    caption: null,
                );

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

                $linkLines = $this->buildFallbackLinks($attachments);
                if ($linkLines !== '') {
                    try {
                        $fallback = $this->client->sendText(
                            $sessionId,
                            $chatId,
                            "No se pudo adjuntar el archivo por WhatsApp. Descarga aquí:\n\n".$linkLines,
                        );
                        $messageIds[] = isset($fallback['messageId']) ? (string) $fallback['messageId'] : null;
                        $sent++;
                    } catch (Throwable) {
                        // ignore
                    }
                }

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
     * @return list<array{tipo: string, label: string, filename: string, mimetype: string, caption: string, url?: string, binary?: string}>
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
                'label' => 'PDF Ticket',
                'filename' => $safeNumero.'-ticket.pdf',
                'mimetype' => 'application/pdf',
                'caption' => 'PDF Ticket '.$documento->numero_completo,
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
                    'caption' => 'PDF A4 '.$documento->numero_completo,
                    'url' => $urlA4,
                ];
            }
        }

        if ($xml && filled($documento->url_xml)) {
            $attachments[] = [
                'tipo' => 'xml',
                'label' => 'XML',
                'filename' => $safeNumero.'.xml',
                'mimetype' => 'text/xml',
                'caption' => 'XML '.$documento->numero_completo,
                'url' => (string) $documento->url_xml,
            ];
        }

        if ($cdr && filled($documento->url_cdr)) {
            $attachments[] = [
                'tipo' => 'cdr',
                'label' => 'CDR',
                'filename' => 'CDR-'.$safeNumero.'.xml',
                'mimetype' => 'text/xml',
                'caption' => 'CDR '.$documento->numero_completo,
                'url' => (string) $documento->url_cdr,
            ];
        }

        return $attachments;
    }

    /**
     * @param  list<array{tipo: string, label: string, url?: string}>  $attachments
     */
    private function buildFallbackLinks(array $attachments): string
    {
        $lines = [];
        foreach ($attachments as $att) {
            $url = $att['url'] ?? null;
            if (is_string($url) && $url !== '') {
                $lines[] = '• '.$att['label'].': '.$url;
            }
        }

        return implode("\n", $lines);
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
