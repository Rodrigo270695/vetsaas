<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Models\ClinicSetting;
use App\Models\NotificationQueue;
use App\Models\Receta;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\Notifications\ReminderMessageBuilder;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use RuntimeException;
use Throwable;

final class RecetaWhatsAppSender
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppSessionSync $sessionSync,
        private readonly RecetaPdfService $pdf,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    /**
     * @return array{message_id: string|null}
     */
    public function send(
        Receta $receta,
        Tenant $tenant,
        string $chatId,
        string $ownerName,
        ClinicSetting $clinic,
    ): array {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('WhatsApp (OpenWA) no está configurado.');
        }

        $session = $this->sessionSync->ensureReadyForSend($tenant);
        if (! $session instanceof TenantWhatsAppSession || ! $session->isReady()) {
            throw new RuntimeException('La sesión WhatsApp de la clínica no está conectada.');
        }

        $receta->loadMissing(['paciente:id,nombre', 'lineas']);

        $clinicName = $this->messages->clinicDisplayName($clinic);
        $petName = $receta->paciente?->nombre ?? 'tu mascota';
        $fecha = $receta->emitida_at
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
        $meds = $receta->lineas
            ->pluck('nombre_medicamento')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $caption = $this->messages->recetaPdf(
            $clinicName,
            $ownerName,
            $petName,
            array_values(array_map(strval(...), $meds)),
            $fecha,
        );

        $file = $this->pdf->render($receta);

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

        $this->recordSent($chatId, $ownerName, $caption, $receta->id, $messageId);

        return ['message_id' => $messageId];
    }

    private function recordSent(
        string $chatId,
        string $ownerName,
        string $cuerpo,
        string $recetaId,
        ?string $messageId,
    ): void {
        try {
            NotificationQueue::query()->create([
                'tipo' => 'receta',
                'canal' => NotificationQueue::CANAL_WHATSAPP,
                'destinatario' => $chatId,
                'destinatario_nombre' => $ownerName,
                'cuerpo' => $cuerpo."\n\n[adjunto: receta PDF]",
                'referencia_tipo' => 'receta',
                'referencia_id' => $recetaId,
                'dedupe_key' => 'receta:'.$recetaId.':'.now()->timestamp,
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
