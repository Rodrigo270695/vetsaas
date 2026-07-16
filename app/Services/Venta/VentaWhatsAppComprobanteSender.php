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
use Illuminate\Support\Facades\Log;
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
        ?string $anchoMm = null,
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
            'felDocument',
            'lineas',
            'propietario',
            'paciente',
            'creadoPor',
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

        $sessionId = (string) $session->openwa_session_id;
        $mode = 'text';
        $messageId = null;
        $lastError = null;
        $documentAttempted = false;

        $felPdfUrl = $venta->felDocument?->url_pdf ?: $venta->felDocument?->enlace_consulta;
        $felPdfUrl = is_string($felPdfUrl) && trim($felPdfUrl) !== '' ? trim($felPdfUrl) : null;

        if ($felPdfUrl !== null && str_starts_with($felPdfUrl, 'http')) {
            $documentAttempted = true;
            try {
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
            } catch (Throwable $e) {
                $lastError = $e;
                Log::warning('Envío PDF FEL por WhatsApp falló; se intenta ticket interno', [
                    'venta_id' => $venta->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($mode === 'text') {
            $pdf = $this->ticketPdf->renderForWhatsApp($venta, $clinic, (string) $tenant->id, $anchoMm);
            if ($pdf !== null) {
                $documentAttempted = true;
                try {
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
                } catch (Throwable $e) {
                    $lastError = $e;
                    Log::warning('Envío ticket PDF por WhatsApp falló', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($mode === 'text') {
            if ($documentAttempted) {
                throw new RuntimeException(
                    $lastError?->getMessage() ?? 'No se pudo enviar el ticket por WhatsApp.',
                    0,
                    $lastError,
                );
            }

            try {
                $result = $this->client->sendText($sessionId, $chatId, $caption);
                $messageId = isset($result['messageId']) ? (string) $result['messageId'] : null;
            } catch (Throwable $e) {
                Log::warning('Envío texto WhatsApp de venta falló', [
                    'venta_id' => $venta->id,
                    'error' => $e->getMessage(),
                ]);

                throw new RuntimeException($e->getMessage(), 0, $e);
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
