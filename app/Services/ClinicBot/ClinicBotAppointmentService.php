<?php

declare(strict_types=1);

namespace App\Services\ClinicBot;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Models\Cita;
use App\Models\GroomingServicio;
use App\Models\GroomingTurno;
use App\Models\Paciente;
use App\Support\ClinicBot\ClinicBotPeruClock;
use App\Support\ClinicBot\ClinicBotRelativeDateParser;
use App\Support\Grooming\GroomingTurnoServicioRules;
use Illuminate\Support\Carbon;

final class ClinicBotAppointmentService
{
    public function __construct(
        private readonly ClinicBotRelativeDateParser $dateParser,
        private readonly ClinicBotClientResolver $clientResolver,
    ) {}

    /**
     * @return array{ok: true, cita_id: string, inicio_at: string, paciente: string}|array{ok: false, error: string}
     */
    public function registerCita(
        string $phone,
        string $pacienteId,
        string $fecha,
        string $hora,
        ?string $motivo = null,
        ?int $duracionMinutos = null,
    ): array {
        if (! $this->clientResolver->pacienteBelongsToPhone($pacienteId, $phone)) {
            return [
                'ok' => false,
                'error' => 'No encontramos esa mascota asociada a tu número de WhatsApp.',
            ];
        }

        $parsed = $this->dateParser->parseDateTime($fecha, $hora);
        if ($parsed['ok'] === false) {
            return $parsed;
        }

        /** @var Carbon $inicioAt */
        $inicioAt = $parsed['datetime'];
        $duracion = $duracionMinutos ?? 30;

        if ($this->hasCitaConflict($inicioAt, $duracion, $pacienteId)) {
            return [
                'ok' => false,
                'error' => 'Ya existe una cita programada en ese horario para esa mascota.',
            ];
        }

        $paciente = Paciente::query()->findOrFail($pacienteId);

        $cita = Cita::query()->create([
            'paciente_id' => $pacienteId,
            'inicio_at' => $inicioAt,
            'duracion_minutos' => $duracion,
            'estado' => Cita::ESTADO_PROGRAMADA,
            'motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : 'Consulta veterinaria',
            'notas' => 'Agendada automáticamente por el asistente WhatsApp IA.',
        ]);

        return [
            'ok' => true,
            'cita_id' => $cita->id,
            'inicio_at' => $inicioAt->timezone(ClinicBotPeruClock::TIMEZONE)->format('d/m/Y H:i'),
            'paciente' => $paciente->nombre,
        ];
    }

    /**
     * @return array{ok: true, turno_id: string, inicio_at: string, paciente: string, servicio: string}|array{ok: false, error: string}
     */
    public function registerGroomingTurno(
        string $phone,
        string $pacienteId,
        string $servicioId,
        string $fecha,
        string $hora,
        ?int $duracionMinutos = null,
    ): array {
        if (! $this->clientResolver->pacienteBelongsToPhone($pacienteId, $phone)) {
            return [
                'ok' => false,
                'error' => 'No encontramos esa mascota asociada a tu número de WhatsApp.',
            ];
        }

        $parsed = $this->dateParser->parseDateTime($fecha, $hora);
        if ($parsed['ok'] === false) {
            return $parsed;
        }

        /** @var Carbon $inicioAt */
        $inicioAt = $parsed['datetime'];

        $serviceData = $this->resolveGroomingService($servicioId);
        if ($serviceData['ok'] === false) {
            return $serviceData;
        }

        $duracion = $duracionMinutos ?? $serviceData['duracion_minutos'] ?? 60;

        if ($this->hasGroomingConflict($inicioAt, $duracion, $pacienteId)) {
            return [
                'ok' => false,
                'error' => 'Ya existe un turno de grooming programado en ese horario para esa mascota.',
            ];
        }

        $paciente = Paciente::query()->findOrFail($pacienteId);

        $payload = GroomingTurnoServicioRules::normalizarParaPersistencia(array_merge(
            $serviceData['payload'],
            [
                'paciente_id' => $pacienteId,
                'inicio_at' => $inicioAt,
                'duracion_minutos' => $duracion,
            ],
        ));

        $turno = GroomingTurno::query()->create(array_merge($payload, [
            'estado' => GroomingTurno::ESTADO_PROGRAMADA,
            'notas' => 'Agendado automáticamente por el asistente WhatsApp IA.',
        ]));

        return [
            'ok' => true,
            'turno_id' => $turno->id,
            'inicio_at' => $inicioAt->timezone(ClinicBotPeruClock::TIMEZONE)->format('d/m/Y H:i'),
            'paciente' => $paciente->nombre,
            'servicio' => $serviceData['nombre'],
        ];
    }

    /**
     * @return array{ok: true, date: string, label: string, referencia_actual: string}|array{ok: false, error: string}
     */
    public function resolveDateExpression(string $expression): array
    {
        $resolved = $this->dateParser->resolveExpression($expression);
        if ($resolved['ok'] === false) {
            return $resolved;
        }

        return [
            'ok' => true,
            'date' => $resolved['date'],
            'label' => $resolved['label'],
            'referencia_actual' => ClinicBotPeruClock::promptReference(),
        ];
    }

    /**
     * @return array{ok: true, payload: array<string, mixed>, nombre: string, duracion_minutos: int|null}|array{ok: false, error: string}
     */
    private function resolveGroomingService(string $servicioId): array
    {
        if (GroomingCatalogoMode::usaCatalogoPersonalizado()) {
            $servicio = GroomingServicio::query()
                ->whereKey($servicioId)
                ->where('activo', true)
                ->first();

            if ($servicio === null) {
                return ['ok' => false, 'error' => 'Servicio de grooming no encontrado.'];
            }

            return [
                'ok' => true,
                'payload' => ['grooming_servicio_id' => $servicio->id],
                'nombre' => $servicio->nombre,
                'duracion_minutos' => $servicio->duracion_minutos,
            ];
        }

        $slug = trim($servicioId);
        if (! in_array($slug, GroomingCatalogoServicio::slugs(), true)) {
            return ['ok' => false, 'error' => 'Servicio de grooming no encontrado.'];
        }

        return [
            'ok' => true,
            'payload' => ['servicio' => $slug],
            'nombre' => mb_convert_case(str_replace('_', ' ', $slug), MB_CASE_TITLE, 'UTF-8'),
            'duracion_minutos' => GroomingCatalogoServicio::duracionSugeridaPara($slug),
        ];
    }

    private function hasCitaConflict(Carbon $inicioAt, int $duracionMinutos, string $pacienteId): bool
    {
        $fin = $inicioAt->copy()->addMinutes($duracionMinutos);

        return Cita::query()
            ->where('paciente_id', $pacienteId)
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA, Cita::ESTADO_NO_ASISTIO])
            ->where('inicio_at', '<', $fin)
            ->whereRaw('inicio_at + (duracion_minutos * interval \'1 minute\') > ?', [$inicioAt])
            ->exists();
    }

    private function hasGroomingConflict(Carbon $inicioAt, int $duracionMinutos, string $pacienteId): bool
    {
        $fin = $inicioAt->copy()->addMinutes($duracionMinutos);

        return GroomingTurno::query()
            ->where('paciente_id', $pacienteId)
            ->whereNotIn('estado', [GroomingTurno::ESTADO_CANCELADA, GroomingTurno::ESTADO_NO_ASISTIO])
            ->where('inicio_at', '<', $fin)
            ->whereRaw('inicio_at + (duracion_minutos * interval \'1 minute\') > ?', [$inicioAt])
            ->exists();
    }
}
