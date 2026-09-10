<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Models\Cita;
use App\Models\GroomingTurno;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantModuleAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class SalaEsperaHoyService
{
    public const TIPO_CONSULTA = 'consulta';

    public const TIPO_GROOMING = 'grooming';

    /**
     * @return array{
     *     tipo: string,
     *     fecha: string,
     *     count: int,
     *     espera: list<array<string, mixed>>,
     *     proximas: list<array<string, mixed>>,
     *     en_curso: list<array<string, mixed>>,
     *     can_marcar: bool
     * }
     */
    public function forQueue(User $user, ?Tenant $tenant, string $tipo): array
    {
        $tipo = $this->normalizeTipo($tipo);
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);
        $inicio = $now->copy()->startOfDay();
        $fin = $now->copy()->endOfDay();

        $espera = [];
        $proximas = [];
        $enCurso = [];

        if ($tipo === self::TIPO_CONSULTA) {
            $this->assertCanVerConsulta($user, $tenant);
            if (Schema::hasTable('citas')) {
                $query = Cita::query()
                    ->with(['paciente:id,nombre'])
                    ->whereBetween('inicio_at', [$inicio, $fin])
                    ->whereIn('estado', [
                        ...Cita::ESTADOS_EN_ESPERA,
                        Cita::ESTADO_EN_ATENCION,
                    ])
                    ->orderBy('inicio_at')
                    ->limit(50);

                if (Schema::hasColumn('citas', 'sala_espera_atendido_at')) {
                    $query->whereNull('sala_espera_atendido_at');
                }

                foreach ($query->get() as $cita) {
                    $item = $this->serialize(
                        (string) $cita->id,
                        self::TIPO_CONSULTA,
                        (string) ($cita->paciente?->nombre ?: '—'),
                        $cita->inicio_at,
                        (string) $cita->estado,
                        '/clinica/citas',
                        $tz,
                    );
                    $at = $cita->inicio_at instanceof Carbon
                        ? $cita->inicio_at->timezone($tz)
                        : Carbon::parse((string) $cita->inicio_at, $tz);

                    if ($cita->estado === Cita::ESTADO_EN_ATENCION) {
                        $enCurso[] = $item;
                    } elseif ($at->lte($now)) {
                        $espera[] = $item;
                    } else {
                        $proximas[] = $item;
                    }
                }
            }
        } else {
            $this->assertCanVerGrooming($user, $tenant);
            if (Schema::hasTable('grooming_turnos')) {
                $query = GroomingTurno::query()
                    ->with(['paciente:id,nombre'])
                    ->whereBetween('inicio_at', [$inicio, $fin])
                    ->whereIn('estado', [
                        ...GroomingTurno::ESTADOS_EN_ESPERA,
                        GroomingTurno::ESTADO_EN_PROCESO,
                    ])
                    ->orderBy('inicio_at')
                    ->limit(50);

                if (Schema::hasColumn('grooming_turnos', 'sala_espera_atendido_at')) {
                    $query->whereNull('sala_espera_atendido_at');
                }

                foreach ($query->get() as $turno) {
                    $item = $this->serialize(
                        (string) $turno->id,
                        self::TIPO_GROOMING,
                        (string) ($turno->paciente?->nombre ?: '—'),
                        $turno->inicio_at,
                        (string) $turno->estado,
                        '/servicios/grooming',
                        $tz,
                    );
                    $at = $turno->inicio_at instanceof Carbon
                        ? $turno->inicio_at->timezone($tz)
                        : Carbon::parse((string) $turno->inicio_at, $tz);

                    if ($turno->estado === GroomingTurno::ESTADO_EN_PROCESO) {
                        $enCurso[] = $item;
                    } elseif ($at->lte($now)) {
                        $espera[] = $item;
                    } else {
                        $proximas[] = $item;
                    }
                }
            }
        }

        return [
            'tipo' => $tipo,
            'fecha' => $now->toDateString(),
            'count' => count($espera),
            'espera' => $espera,
            'proximas' => $proximas,
            'en_curso' => $enCurso,
            'can_marcar' => $user->can('sala-espera.marcar-atendido'),
        ];
    }

    /**
     * @return array{created: bool, item: array<string, mixed>}
     */
    public function enviar(User $user, ?Tenant $tenant, Paciente $paciente, string $tipo): array
    {
        abort_unless($user->can('sala-espera.enviar'), 403);
        $tipo = $this->normalizeTipo($tipo);
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);

        if ($tipo === self::TIPO_CONSULTA) {
            $this->assertModule($tenant, 'citas');
            $existing = $this->citaEnColaHoy($paciente, $now);
            if ($existing !== null) {
                return [
                    'created' => false,
                    'item' => $this->serialize(
                        (string) $existing->id,
                        self::TIPO_CONSULTA,
                        (string) ($paciente->nombre ?: '—'),
                        $existing->inicio_at,
                        (string) $existing->estado,
                        '/clinica/citas',
                        $tz,
                    ),
                ];
            }

            $cita = Cita::query()->create([
                'paciente_id' => $paciente->id,
                'inicio_at' => $now->copy()->startOfMinute(),
                'duracion_minutos' => 15,
                'estado' => Cita::ESTADO_PROGRAMADA,
                'motivo' => 'Sala de espera',
                'created_by_id' => $user->id,
                'updated_by_id' => $user->id,
            ]);

            return [
                'created' => true,
                'item' => $this->serialize(
                    (string) $cita->id,
                    self::TIPO_CONSULTA,
                    (string) ($paciente->nombre ?: '—'),
                    $cita->inicio_at,
                    (string) $cita->estado,
                    '/clinica/citas',
                    $tz,
                ),
            ];
        }

        $this->assertModule($tenant, 'grooming');
        $existing = $this->groomingEnColaHoy($paciente, $now);
        if ($existing !== null) {
            return [
                'created' => false,
                'item' => $this->serialize(
                    (string) $existing->id,
                    self::TIPO_GROOMING,
                    (string) ($paciente->nombre ?: '—'),
                    $existing->inicio_at,
                    (string) $existing->estado,
                    '/servicios/grooming',
                    $tz,
                ),
            ];
        }

        $turno = GroomingTurno::query()->create([
            'paciente_id' => $paciente->id,
            'inicio_at' => $now->copy()->startOfMinute(),
            'duracion_minutos' => 30,
            'estado' => GroomingTurno::ESTADO_PROGRAMADA,
            'servicio' => 'bano_higienico',
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);

        return [
            'created' => true,
            'item' => $this->serialize(
                (string) $turno->id,
                self::TIPO_GROOMING,
                (string) ($paciente->nombre ?: '—'),
                $turno->inicio_at,
                (string) $turno->estado,
                '/servicios/grooming',
                $tz,
            ),
        ];
    }

    public function marcarAtendido(User $user, string $tipo, string $id): void
    {
        abort_unless($user->can('sala-espera.marcar-atendido'), 403);
        $tipo = $this->normalizeTipo($tipo);
        $now = now();

        if ($tipo === self::TIPO_CONSULTA) {
            abort_unless(Schema::hasColumn('citas', 'sala_espera_atendido_at'), 422, 'Migración de sala de espera pendiente.');
            $cita = Cita::query()->whereKey($id)->firstOrFail();
            $cita->forceFill(['sala_espera_atendido_at' => $now])->save();

            return;
        }

        abort_unless(Schema::hasColumn('grooming_turnos', 'sala_espera_atendido_at'), 422, 'Migración de sala de espera pendiente.');
        $turno = GroomingTurno::query()->whereKey($id)->firstOrFail();
        $turno->forceFill(['sala_espera_atendido_at' => $now])->save();
    }

    private function citaEnColaHoy(Paciente $paciente, Carbon $now): ?Cita
    {
        $query = Cita::query()
            ->where('paciente_id', $paciente->id)
            ->whereBetween('inicio_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->whereIn('estado', Cita::ESTADOS_EN_ESPERA);

        if (Schema::hasColumn('citas', 'sala_espera_atendido_at')) {
            $query->whereNull('sala_espera_atendido_at');
        }

        return $query->orderBy('inicio_at')->first();
    }

    private function groomingEnColaHoy(Paciente $paciente, Carbon $now): ?GroomingTurno
    {
        $query = GroomingTurno::query()
            ->where('paciente_id', $paciente->id)
            ->whereBetween('inicio_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->whereIn('estado', GroomingTurno::ESTADOS_EN_ESPERA);

        if (Schema::hasColumn('grooming_turnos', 'sala_espera_atendido_at')) {
            $query->whereNull('sala_espera_atendido_at');
        }

        return $query->orderBy('inicio_at')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(
        string $id,
        string $tipo,
        string $paciente,
        mixed $inicioAt,
        string $estado,
        string $href,
        string $tz,
    ): array {
        $at = $inicioAt instanceof Carbon
            ? $inicioAt
            : Carbon::parse((string) $inicioAt);

        return [
            'id' => $id,
            'tipo' => $tipo,
            'paciente' => $paciente,
            'hora' => $at->timezone($tz)->format('H:i'),
            'estado' => $estado,
            'href' => $href,
        ];
    }

    private function normalizeTipo(string $tipo): string
    {
        $tipo = $tipo === 'cita' ? self::TIPO_CONSULTA : $tipo;
        if (! in_array($tipo, [self::TIPO_CONSULTA, self::TIPO_GROOMING], true)) {
            throw new RuntimeException('Tipo de sala de espera inválido.');
        }

        return $tipo;
    }

    private function assertCanVerConsulta(User $user, ?Tenant $tenant): void
    {
        abort_unless($user->can('sala-espera.consulta'), 403);
        $this->assertModule($tenant, 'citas');
    }

    private function assertCanVerGrooming(User $user, ?Tenant $tenant): void
    {
        abort_unless($user->can('sala-espera.grooming'), 403);
        $this->assertModule($tenant, 'grooming');
    }

    private function assertModule(?Tenant $tenant, string $module): void
    {
        abort_unless(TenantModuleAccess::isEnabled($tenant, $module), 403);
    }
}
