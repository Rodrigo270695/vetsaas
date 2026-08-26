<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Models\Cita;
use App\Models\ClinicSetting;
use App\Models\ServicioClinico;
use App\Models\VacunaAplicada;
use App\Services\Notifications\NotificationQueueService;
use App\Services\Notifications\ReminderMessageBuilder;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Al programar la próxima aplicación (paquete + fecha/hora) crea o actualiza
 * una cita en agenda. Los recordatorios de General (días antes / 2h) usan esa cita.
 */
final class VacunaProximaCitaSync
{
    public function __construct(
        private readonly NotificationQueueService $queue,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    /**
     * @param  array{
     *     proxima_servicio_clinico_id?: string|null,
     *     proxima_inicio_at?: string|null,
     *     proxima_duracion_minutos?: int|null
     * }  $proxima
     */
    public function sync(VacunaAplicada $vacuna, array $proxima): void
    {
        if (! Schema::hasColumn('vacunas_aplicadas', 'cita_proxima_id')) {
            return;
        }

        $servicioId = trim((string) ($proxima['proxima_servicio_clinico_id'] ?? ''));
        $inicioRaw = trim((string) ($proxima['proxima_inicio_at'] ?? ''));
        $duracion = (int) ($proxima['proxima_duracion_minutos'] ?? 30);
        if ($duracion < 5) {
            $duracion = 30;
        }
        if ($duracion > 480) {
            $duracion = 480;
        }

        if ($servicioId === '' || $inicioRaw === '') {
            $this->detachCitaOnly($vacuna);

            return;
        }

        $servicio = ServicioClinico::query()->with('categoria:id,nombre')->find($servicioId);
        if ($servicio === null) {
            $this->detachCitaOnly($vacuna);

            return;
        }

        $inicio = Carbon::parse($inicioRaw);
        $motivo = $this->motivoDesdeServicio($servicio);
        $userId = Auth::id() !== null ? (string) Auth::id() : null;

        $cita = null;
        $evento = 'creada';

        if ($vacuna->cita_proxima_id !== null) {
            $cita = Cita::query()->find($vacuna->cita_proxima_id);
            if ($cita !== null && in_array($cita->estado, Cita::ESTADOS_EN_ESPERA, true)) {
                $prevInicio = $cita->inicio_at?->toIso8601String();
                $cita->fill([
                    'paciente_id' => $vacuna->paciente_id,
                    'veterinario_id' => $vacuna->veterinario_id,
                    'sede_id' => $vacuna->sede_id,
                    'inicio_at' => $inicio,
                    'duracion_minutos' => $duracion,
                    'motivo' => $motivo,
                    'notas' => $this->notasCita($vacuna, $servicio),
                    'updated_by_id' => $userId,
                ]);
                $cita->save();
                $evento = ($prevInicio !== null && $prevInicio !== $inicio->toIso8601String())
                    ? 'reprogramada'
                    : 'actualizada';
            } else {
                $cita = null;
            }
        }

        if ($cita === null) {
            $cita = Cita::query()->create([
                'paciente_id' => $vacuna->paciente_id,
                'veterinario_id' => $vacuna->veterinario_id,
                'sede_id' => $vacuna->sede_id,
                'inicio_at' => $inicio,
                'duracion_minutos' => $duracion,
                'estado' => Cita::ESTADO_PROGRAMADA,
                'motivo' => $motivo,
                'notas' => $this->notasCita($vacuna, $servicio),
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);
            $evento = 'creada';
        }

        $vacuna->forceFill([
            'cita_proxima_id' => $cita->id,
            'fecha_proxima_sugerida' => $inicio->toDateString(),
        ])->save();

        $this->enqueueCitaWhatsApp($cita->fresh(['paciente.propietario']), $evento);
    }

    public function clearProxima(VacunaAplicada $vacuna): void
    {
        $this->detachCitaOnly($vacuna);
        $vacuna->forceFill(['fecha_proxima_sugerida' => null])->save();
    }

    /** Cancela la cita vinculada si sigue en espera; no toca fecha_proxima_sugerida. */
    private function detachCitaOnly(VacunaAplicada $vacuna): void
    {
        if (! Schema::hasColumn('vacunas_aplicadas', 'cita_proxima_id')) {
            return;
        }

        $citaId = $vacuna->cita_proxima_id;
        if ($citaId === null) {
            return;
        }

        $vacuna->forceFill(['cita_proxima_id' => null])->save();

        $cita = Cita::query()->find($citaId);
        if ($cita === null || ! in_array($cita->estado, Cita::ESTADOS_EN_ESPERA, true)) {
            return;
        }

        $cita->update([
            'estado' => Cita::ESTADO_CANCELADA,
            'updated_by_id' => Auth::id() !== null ? (string) Auth::id() : null,
        ]);
    }

    private function motivoDesdeServicio(ServicioClinico $servicio): string
    {
        $categoria = VacunaAplicada::categoriaRegistroDesdeServicioClinico($servicio);
        $nombre = trim((string) $servicio->nombre);

        return match ($categoria) {
            VacunaAplicada::CATEGORIA_DESPARASITACION => $nombre !== ''
                ? 'Desparasitación — '.$nombre
                : 'Desparasitación',
            VacunaAplicada::CATEGORIA_OTRO => $nombre !== '' ? $nombre : 'Control clínico',
            default => $nombre !== '' ? 'Vacunación — '.$nombre : 'Vacunación',
        };
    }

    private function notasCita(VacunaAplicada $vacuna, ServicioClinico $servicio): string
    {
        $aplicada = trim((string) $vacuna->nombre_vacuna);

        return 'Programada desde vacunaciones'
            .($aplicada !== '' ? " (última aplicación: {$aplicada})" : '')
            .' → próxima: '.trim((string) $servicio->nombre);
    }

    /**
     * @param  'creada'|'actualizada'|'reprogramada'  $evento
     */
    private function enqueueCitaWhatsApp(Cita $cita, string $evento): void
    {
        $setting = ClinicSetting::current();
        if (! $setting->notificarCitaWhatsAppActivo()) {
            return;
        }

        $propietario = $cita->paciente?->propietario;
        $chatId = WhatsAppChatId::fromPhone($propietario?->telefono);
        if ($chatId === null) {
            return;
        }

        $tipo = match ($evento) {
            'reprogramada' => 'cita_reprogramada',
            'actualizada' => 'cita_actualizada',
            default => 'cita_creada',
        };

        $clinicName = $this->messages->clinicDisplayName($setting);
        $ownerName = trim((string) ($propietario?->displayName() ?? ''));
        if ($ownerName === '') {
            $ownerName = 'cliente';
        }
        $petName = (string) ($cita->paciente?->nombre ?? 'tu mascota');
        $inicioAt = $cita->inicio_at instanceof Carbon
            ? $cita->inicio_at
            : Carbon::parse((string) $cita->inicio_at);
        $motivo = trim((string) ($cita->motivo ?? ''));
        $motivo = $motivo !== '' ? $motivo : null;

        $cuerpo = match ($evento) {
            'reprogramada' => $this->messages->citaReprogramada(
                $clinicName,
                $ownerName,
                $petName,
                $inicioAt,
                $motivo,
            ),
            'actualizada' => $this->messages->citaActualizada(
                $clinicName,
                $ownerName,
                $petName,
                $inicioAt,
                $motivo,
            ),
            default => $this->messages->citaCreada(
                $clinicName,
                $ownerName,
                $petName,
                $inicioAt,
                $motivo,
            ),
        };

        $this->queue->enqueue(
            tipo: $tipo,
            destinatario: $chatId,
            cuerpo: $cuerpo,
            enviarAt: now(),
            destinatarioNombre: $ownerName,
            referenciaTipo: 'cita',
            referenciaId: $cita->id,
            dedupeKey: $tipo.':'.$cita->id.':'.$inicioAt->timestamp,
        );
    }
}
