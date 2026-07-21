<?php

declare(strict_types=1);

namespace App\Services\Hotel;

use App\Models\ClinicSetting;
use App\Models\HotelEstancia;
use App\Models\HotelEstanciaDiario;
use App\Models\Tenant;
use App\Services\Notifications\NotificationQueueService;
use App\Services\Notifications\ReminderMessageBuilder;
use App\Services\Notifications\WhatsAppNotificationDispatcher;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HotelWhatsAppNotifier
{
    public function __construct(
        private readonly NotificationQueueService $queue,
        private readonly WhatsAppNotificationDispatcher $dispatcher,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    /**
     * @param  'programada'|'reprogramada'|'confirmada'|'en_estancia'|'completada'|'cancelada'|'no_presento'|'bitacora'  $evento
     * @return 'disabled'|'no_phone'|'queued'|'duplicate'|'failed'
     */
    public function notify(
        HotelEstancia $estancia,
        string $evento,
        ?HotelEstanciaDiario $diario = null,
    ): string {
        $setting = ClinicSetting::current();
        if (! $setting->notificarHotelWhatsAppActivo($evento)) {
            return 'disabled';
        }

        $estancia->loadMissing(['paciente.propietario']);
        $propietario = $estancia->paciente?->propietario;
        $chatId = WhatsAppChatId::fromPhone($propietario?->telefono);
        if ($chatId === null) {
            return 'no_phone';
        }

        try {
            $ownerName = trim((string) ($propietario?->displayName() ?? ''));
            if ($ownerName === '') {
                $ownerName = 'cliente';
            }

            $petName = trim((string) ($estancia->paciente?->nombre ?? ''));
            if ($petName === '') {
                $petName = 'tu mascota';
            }

            $clinicName = $this->messages->clinicDisplayName($setting);
            $cuerpo = $evento === 'bitacora' && $diario !== null
                ? $this->messages->hotelBitacora(
                    $clinicName,
                    $ownerName,
                    $petName,
                    $diario->fecha,
                    $diario->notas,
                )
                : $this->messages->hotelEstanciaEvento(
                    $clinicName,
                    $ownerName,
                    $petName,
                    $evento,
                    $estancia->ingreso_at,
                    $estancia->egreso_at,
                );

            $version = $diario !== null
                ? (string) $diario->id
                : ($estancia->updated_at?->format('U.u') ?? now()->format('U.u'));
            $tipo = $evento === 'bitacora' ? 'hotel_bitacora' : 'hotel_'.$evento;

            $item = $this->queue->enqueue(
                tipo: $tipo,
                destinatario: $chatId,
                cuerpo: $cuerpo,
                enviarAt: now(),
                destinatarioNombre: $ownerName,
                referenciaTipo: 'hotel_estancia',
                referenciaId: $estancia->id,
                dedupeKey: $tipo.':'.$estancia->id.':'.$version,
                prioridad: 4,
            );

            if ($item === null) {
                return 'duplicate';
            }

            $tenantId = tenant_id();
            $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
            if ($tenant !== null) {
                try {
                    $this->dispatcher->dispatchOne($item, $tenant);
                } catch (Throwable $e) {
                    Log::warning('Dispatch inmediato de hotel falló; queda en cola', [
                        'hotel_estancia_id' => $estancia->id,
                        'evento' => $evento,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return 'queued';
        } catch (Throwable $e) {
            Log::warning('No se pudo encolar WhatsApp de hotel', [
                'hotel_estancia_id' => $estancia->id,
                'evento' => $evento,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }
}
