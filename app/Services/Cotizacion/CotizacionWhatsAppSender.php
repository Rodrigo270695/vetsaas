<?php

declare(strict_types=1);

namespace App\Services\Cotizacion;

use App\Models\ClinicSetting;
use App\Models\Cotizacion;
use App\Models\NotificationQueue;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\Notifications\ReminderMessageBuilder;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use RuntimeException;
use Throwable;

final class CotizacionWhatsAppSender
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppSessionSync $sessionSync,
        private readonly CotizacionPdfService $pdf,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    public function send(Cotizacion $cotizacion, Tenant $tenant, string $chatId, ClinicSetting $clinic): void
    {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('WhatsApp (OpenWA) no está configurado.');
        }

        $session = $this->sessionSync->ensureReadyForSend($tenant);
        if (! $session instanceof TenantWhatsAppSession || ! $session->isReady()) {
            throw new RuntimeException('La sesión WhatsApp de la clínica no está conectada.');
        }

        $caption = self::mensaje($cotizacion, $this->messages->clinicDisplayName($clinic));
        $file = $this->pdf->render($cotizacion);

        try {
            $result = $this->client->sendDocument(
                sessionId: (string) $session->openwa_session_id,
                chatId: $chatId,
                binaryContent: $file['binary'],
                filename: $file['filename'],
                mimetype: 'application/pdf',
                caption: $caption,
            );
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $messageId = isset($result['messageId']) ? (string) $result['messageId'] : null;
        $this->recordSent($chatId, $cotizacion, $caption, $messageId);
    }

    public static function mensaje(Cotizacion $cotizacion, string $clinicName): string
    {
        $nombre = trim(explode(' ', trim($cotizacion->cliente_nombre))[0] ?? '');
        $saludo = $nombre !== '' ? "Hola {$nombre}," : 'Hola,';
        $total = number_format((float) $cotizacion->total, 2, '.', ',');
        $simbolo = strtoupper((string) $cotizacion->moneda) === 'USD' ? 'US$' : 'S/';

        return implode("\n", [
            $saludo,
            "{$clinicName} te comparte la cotización {$cotizacion->numero}.",
            "Total: {$simbolo} {$total}",
            'Válida hasta el '.$cotizacion->vigencia_hasta->format('d/m/Y').'.',
            '',
            'Cualquier consulta, respóndenos por aquí.',
        ]);
    }

    private function recordSent(string $chatId, Cotizacion $cotizacion, string $cuerpo, ?string $messageId): void
    {
        try {
            NotificationQueue::query()->create([
                'tipo' => 'cotizacion',
                'canal' => NotificationQueue::CANAL_WHATSAPP,
                'destinatario' => $chatId,
                'destinatario_nombre' => $cotizacion->cliente_nombre,
                'cuerpo' => $cuerpo."\n\n[adjunto: cotización PDF]",
                'referencia_tipo' => 'cotizacion',
                'referencia_id' => $cotizacion->id,
                'dedupe_key' => 'cotizacion:'.$cotizacion->id.':'.now()->timestamp,
                'enviar_at' => now(),
                'prioridad' => 3,
                'estado' => NotificationQueue::ESTADO_ENVIADO,
                'intentos' => 1,
                'max_intentos' => (int) config('openwa.max_attempts', 3),
                'ultimo_intento_at' => now(),
                'proveedor_msg_id' => $messageId,
            ]);
        } catch (Throwable) {
        }
    }
}
