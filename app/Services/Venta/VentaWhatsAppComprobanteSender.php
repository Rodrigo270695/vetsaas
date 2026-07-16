<?php

declare(strict_types=1);

namespace App\Services\Venta;

use App\Models\ClinicSetting;
use App\Models\NotificationQueue;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Models\Venta;
use App\Services\Notifications\ReminderMessageBuilder;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use RuntimeException;
use Throwable;

/**
 * Envía el ticket/comprobante de una venta por WhatsApp (documento PDF + caption).
 */
final class VentaWhatsAppComprobanteSender
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppSessionSync $sessionSync,
        private readonly VentaTicketPdfService $ticketPdf,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    /**
     * @return array{message_id: string|null, mode: 'fel_pdf'|'ticket_pdf'|'text'}
     */
    public function send(
        Venta $venta,
        Tenant $tenant,
        string $chatId,
        string $ownerName,
        ClinicSetting $clinic,
    ): array {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('WhatsApp (OpenWA) no está configurado.');
        }

        $session = $this->resolveReadySession($tenant);
        if ($session === null) {
            throw new RuntimeException('La sesión WhatsApp de la clínica no está conectada.');
        }

        $clinicName = $this->messages->clinicDisplayName($clinic);
        $venta->loadMissing([
            'felDocument:id,venta_id,numero_completo,estado,url_pdf,enlace_consulta',
        ]);

        $numeroDisplay = $venta->felDocument?->numero_completo ?? $venta->numero;
        $fecha = ($venta->fecha_pago ?? $venta->created_at)
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
        $totalFormatted = number_format((float) $venta->total, 2, '.', ',').' '.$venta->moneda;

        $caption = $this->messages->ventaComprobante(
            $clinicName,
            $ownerName,
            $numeroDisplay,
            $totalFormatted,
            $fecha,
            null,
        );

        $felPdfUrl = $venta->felDocument?->url_pdf ?: $venta->felDocument?->enlace_consulta;
        $felPdfUrl = is_string($felPdfUrl) && trim($felPdfUrl) !== '' ? trim($felPdfUrl) : null;

        $mode = 'text';
        $messageId = null;
        $sessionId = (string) $session->openwa_session_id;

        if ($felPdfUrl !== null && str_starts_with($felPdfUrl, 'http')) {
            $safeNumero = preg_replace('/[^A-Za-z0-9_-]+/', '-', $numeroDisplay) ?: 'comprobante';
            $result = $this->client->sendDocument(
                sessionId: $sessionId,
                chatId: $chatId,
                url: $felPdfUrl,
                filename: 'comprobante-'.$safeNumero.'.pdf',
                mimetype: 'application/pdf',
                caption: $caption,
            );
            $mode = 'fel_pdf';
            $messageId = isset($result['messageId']) ? (string) $result['messageId'] : null;
        } else {
            $pdf = $this->ticketPdf->renderIfAllowed($venta, $clinic, $tenant, (string) $tenant->id);
            if ($pdf !== null) {
                $result = $this->client->sendDocument(
                    sessionId: $sessionId,
                    chatId: $chatId,
                    binaryContent: $pdf['binary'],
                    filename: $pdf['filename'],
                    mimetype: 'application/pdf',
                    caption: $caption,
                );
                $mode = 'ticket_pdf';
                $messageId = isset($result['messageId']) ? (string) $result['messageId'] : null;
            } else {
                $result = $this->client->sendText($sessionId, $chatId, $caption);
                $mode = 'text';
                $messageId = isset($result['messageId']) ? (string) $result['messageId'] : null;
            }
        }

        $this->recordSent(
            chatId: $chatId,
            ownerName: $ownerName,
            cuerpo: $caption.($mode !== 'text' ? "\n\n[adjunto: ticket PDF]" : ''),
            ventaId: $venta->id,
            messageId: $messageId,
        );

        return ['message_id' => $messageId, 'mode' => $mode];
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
        string $ownerName,
        string $cuerpo,
        string $ventaId,
        ?string $messageId,
    ): void {
        try {
            NotificationQueue::query()->create([
                'tipo' => 'venta_comprobante',
                'canal' => NotificationQueue::CANAL_WHATSAPP,
                'destinatario' => $chatId,
                'destinatario_nombre' => $ownerName,
                'cuerpo' => $cuerpo,
                'referencia_tipo' => 'venta',
                'referencia_id' => $ventaId,
                'dedupe_key' => 'venta_comprobante:'.$ventaId.':'.now()->timestamp,
                'enviar_at' => now(),
                'prioridad' => 3,
                'estado' => NotificationQueue::ESTADO_ENVIADO,
                'intentos' => 1,
                'max_intentos' => (int) config('openwa.max_attempts', 3),
                'ultimo_intento_at' => now(),
                'proveedor_msg_id' => $messageId,
            ]);
        } catch (Throwable) {
            // El envío ya ocurrió; el histórico es best-effort.
        }
    }
}
