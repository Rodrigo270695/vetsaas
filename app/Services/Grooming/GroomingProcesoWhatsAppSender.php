<?php

declare(strict_types=1);

namespace App\Services\Grooming;

use App\Models\ClinicSetting;
use App\Models\GroomingTurno;
use App\Models\GroomingTurnoFoto;
use App\Models\NotificationQueue;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\Notifications\ReminderMessageBuilder;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Notifica al propietario por WhatsApp cambios de estado y fotos de grooming.
 */
final class GroomingProcesoWhatsAppSender
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppSessionSync $sessionSync,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    /**
     * Texto de cambio de estado (inicio / completada / cancelada / no asistió).
     * Si hay fotos, también las envía.
     *
     * @param  Collection<int, GroomingTurnoFoto>|null  $fotos
     * @return array{sent_text: bool, sent_fotos: int, message_ids: list<string|null>}
     */
    public function notifyEstado(
        GroomingTurno $turno,
        Tenant $tenant,
        string $chatId,
        string $ownerName,
        ClinicSetting $clinic,
        string $estado,
        ?Collection $fotos = null,
    ): array {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('WhatsApp (OpenWA) no está configurado.');
        }

        $session = $this->resolveReadySession($tenant);
        if ($session === null) {
            throw new RuntimeException('La sesión WhatsApp de la clínica no está conectada.');
        }

        $turno->loadMissing(['paciente:id,nombre', 'groomingServicio:id,nombre']);

        $clinicName = $this->messages->clinicDisplayName($clinic);
        $petName = trim((string) ($turno->paciente?->nombre ?? 'tu mascota')) ?: 'tu mascota';
        $servicioLabel = trim((string) $turno->servicio_label) ?: 'Grooming';
        $sessionId = (string) $session->openwa_session_id;

        $text = match ($estado) {
            GroomingTurno::ESTADO_EN_PROCESO => $this->messages->groomingEstadoInicio(
                $clinicName, $ownerName, $petName, $servicioLabel,
            ),
            GroomingTurno::ESTADO_COMPLETADA => $this->messages->groomingEstadoCompletada(
                $clinicName, $ownerName, $petName, $servicioLabel,
            ),
            GroomingTurno::ESTADO_CANCELADA => $this->messages->groomingEstadoCancelada(
                $clinicName, $ownerName, $petName, $servicioLabel,
            ),
            GroomingTurno::ESTADO_NO_ASISTIO => $this->messages->groomingEstadoNoAsistio(
                $clinicName, $ownerName, $petName, $servicioLabel,
            ),
            default => null,
        };

        $messageIds = [];
        $sentText = false;

        if ($text !== null) {
            $result = $this->client->sendTextWithDeliveryFallback($sessionId, $chatId, $text);
            $messageId = isset($result['messageId']) ? (string) $result['messageId'] : null;
            $messageIds[] = $messageId;
            $sentText = true;
            $this->recordSent(
                tipo: 'grooming_estado',
                chatId: $chatId,
                ownerName: $ownerName,
                cuerpo: $text,
                turnoId: $turno->id,
                dedupeSuffix: $estado,
                messageId: $messageId,
            );
        }

        $sentFotos = 0;
        $toSend = ($fotos ?? collect())->values();
        if ($toSend->isNotEmpty()) {
            $fotoResult = $this->sendFotos(
                $turno,
                $sessionId,
                $chatId,
                $ownerName,
                $clinicName,
                $petName,
                $servicioLabel,
                $toSend,
            );
            $sentFotos = $fotoResult['sent'];
            $messageIds = array_merge($messageIds, $fotoResult['message_ids']);
        }

        if (! $sentText && $sentFotos === 0) {
            throw new RuntimeException('No se pudo notificar por WhatsApp.');
        }

        return [
            'sent_text' => $sentText,
            'sent_fotos' => $sentFotos,
            'message_ids' => $messageIds,
        ];
    }

    /**
     * @param  Collection<int, GroomingTurnoFoto>|null  $fotos
     * @return array{sent: int, message_ids: list<string|null>}
     */
    public function send(
        GroomingTurno $turno,
        Tenant $tenant,
        string $chatId,
        string $ownerName,
        ClinicSetting $clinic,
        ?Collection $fotos = null,
    ): array {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('WhatsApp (OpenWA) no está configurado.');
        }

        $session = $this->resolveReadySession($tenant);
        if ($session === null) {
            throw new RuntimeException('La sesión WhatsApp de la clínica no está conectada.');
        }

        $turno->loadMissing(['paciente:id,nombre', 'groomingServicio:id,nombre', 'fotos']);

        $toSend = $fotos ?? $turno->fotos;
        $toSend = $toSend->values();
        if ($toSend->isEmpty()) {
            throw new RuntimeException('No hay fotos para enviar.');
        }

        $clinicName = $this->messages->clinicDisplayName($clinic);
        $petName = trim((string) ($turno->paciente?->nombre ?? 'tu mascota')) ?: 'tu mascota';
        $servicioLabel = trim((string) $turno->servicio_label) ?: 'Grooming';

        return $this->sendFotos(
            $turno,
            (string) $session->openwa_session_id,
            $chatId,
            $ownerName,
            $clinicName,
            $petName,
            $servicioLabel,
            $toSend,
        );
    }

    /**
     * @param  Collection<int, GroomingTurnoFoto>  $toSend
     * @return array{sent: int, message_ids: list<string|null>}
     */
    private function sendFotos(
        GroomingTurno $turno,
        string $sessionId,
        string $chatId,
        string $ownerName,
        string $clinicName,
        string $petName,
        string $servicioLabel,
        Collection $toSend,
    ): array {
        $sent = 0;
        $messageIds = [];

        foreach ($toSend as $foto) {
            if (! $foto instanceof GroomingTurnoFoto) {
                continue;
            }

            $publicUrl = $foto->url;
            if ($publicUrl === null || $publicUrl === '') {
                continue;
            }

            if (! str_starts_with($publicUrl, 'http')) {
                $publicUrl = rtrim((string) config('app.url'), '/').'/'.ltrim($publicUrl, '/');
            }

            $esFinal = $foto->tipo === GroomingTurnoFoto::TIPO_FINAL;
            $caption = $this->messages->groomingProcesoFoto(
                $clinicName,
                $ownerName,
                $petName,
                $servicioLabel,
                $esFinal,
            );

            try {
                $result = $this->client->sendImage(
                    sessionId: $sessionId,
                    chatId: $chatId,
                    url: $publicUrl,
                    caption: $caption,
                );
                $messageId = isset($result['messageId']) ? (string) $result['messageId'] : null;
                $messageIds[] = $messageId;
                $foto->enviado_whatsapp_at = now();
                $foto->save();
                $sent++;

                $this->recordSent(
                    tipo: 'grooming_foto',
                    chatId: $chatId,
                    ownerName: $ownerName,
                    cuerpo: $caption."\n\n[adjunto: foto grooming]",
                    turnoId: $turno->id,
                    dedupeSuffix: $foto->id,
                    messageId: $messageId,
                );
            } catch (Throwable $e) {
                Log::warning('Envío foto grooming por WhatsApp falló', [
                    'turno_id' => $turno->id,
                    'foto_id' => $foto->id,
                    'error' => $e->getMessage(),
                ]);

                if ($sent === 0) {
                    throw new RuntimeException($e->getMessage(), 0, $e);
                }

                break;
            }
        }

        if ($sent === 0) {
            throw new RuntimeException('No se pudo enviar ninguna foto por WhatsApp.');
        }

        return ['sent' => $sent, 'message_ids' => $messageIds];
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
        string $tipo,
        string $chatId,
        string $ownerName,
        string $cuerpo,
        string $turnoId,
        string $dedupeSuffix,
        ?string $messageId,
    ): void {
        try {
            NotificationQueue::query()->create([
                'tipo' => $tipo,
                'canal' => NotificationQueue::CANAL_WHATSAPP,
                'destinatario' => $chatId,
                'destinatario_nombre' => $ownerName,
                'cuerpo' => $cuerpo,
                'referencia_tipo' => 'grooming_turno',
                'referencia_id' => $turnoId,
                'dedupe_key' => $tipo.':'.$turnoId.':'.$dedupeSuffix.':'.now()->timestamp,
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
