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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class SalaEsperaHoyService
{
    public const TIPO_CONSULTA = 'consulta';

    public const TIPO_GROOMING = 'grooming';

    /**
     * @return array{
     *     consulta: array<string, mixed>,
     *     grooming: array<string, mixed>,
     *     can_enviar: bool,
     *     can_marcar: bool,
     *     can_consulta: bool,
     *     can_grooming: bool
     * }
     */
    public function board(User $user, ?Tenant $tenant): array
    {
        $canConsulta = $user->can('sala-espera.consulta') && TenantModuleAccess::isEnabled($tenant, 'citas');
        $canGrooming = $user->can('sala-espera.grooming') && TenantModuleAccess::isEnabled($tenant, 'grooming');

        return [
            'consulta' => $canConsulta
                ? $this->forQueue($user, $tenant, self::TIPO_CONSULTA)
                : $this->emptyQueue($user, self::TIPO_CONSULTA),
            'grooming' => $canGrooming
                ? $this->forQueue($user, $tenant, self::TIPO_GROOMING)
                : $this->emptyQueue($user, self::TIPO_GROOMING),
            'can_enviar' => $user->can('sala-espera.enviar'),
            'can_marcar' => $user->can('sala-espera.marcar-atendido'),
            'can_consulta' => $canConsulta,
            'can_grooming' => $canGrooming,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buscarPacientes(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $like = '%'.$q.'%';

        $pacientes = Paciente::query()
            ->with(['propietario:id,nombres,apellidos,razon_social,telefono'])
            ->where('activo', true)
            ->where(function ($query) use ($like): void {
                $query->where('nombre', 'ILIKE', $like)
                    ->orWhere('microchip', 'ILIKE', $like)
                    ->orWhereHas('propietario', function ($owner) use ($like): void {
                        $owner->where('nombres', 'ILIKE', $like)
                            ->orWhere('apellidos', 'ILIKE', $like)
                            ->orWhereRaw(
                                "trim(concat(coalesce(nombres,''),' ',coalesce(apellidos,''))) ILIKE ?",
                                [$like],
                            )
                            ->orWhere('razon_social', 'ILIKE', $like)
                            ->orWhere('telefono', 'ILIKE', $like)
                            ->orWhere('numero_documento', 'ILIKE', $like);
                    });
            })
            ->orderBy('nombre')
            ->limit(30)
            ->get(['id', 'nombre', 'especie', 'foto_path', 'propietario_id']);

        return $pacientes->map(function (Paciente $paciente): array {
            return [
                'id' => (string) $paciente->id,
                'nombre' => (string) ($paciente->nombre ?: '—'),
                'especie' => $paciente->especie,
                'foto_url' => $paciente->foto_url,
                'propietario' => $paciente->propietario?->displayName() ?: '—',
                'propietario_id' => $paciente->propietario_id,
                'href' => '/clinica/pacientes/'.$paciente->id,
            ];
        })->all();
    }

    /**
     * @return array{
     *     tipo: string,
     *     fecha: string,
     *     count: int,
     *     espera: list<array<string, mixed>>,
     *     proximas: list<array<string, mixed>>,
     *     en_curso: list<array<string, mixed>>,
     *     can_marcar: bool,
     *     visible: bool
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
        $pacienteWith = [
            'paciente:id,nombre,especie,foto_path,propietario_id',
            'paciente.propietario:id,nombres,apellidos,razon_social',
        ];

        if ($tipo === self::TIPO_CONSULTA) {
            $this->assertCanVerConsulta($user, $tenant);
            if (Schema::hasTable('citas')) {
                $query = Cita::query()
                    ->with($pacienteWith)
                    ->whereBetween('inicio_at', [$inicio, $fin])
                    ->whereIn('estado', [
                        ...Cita::ESTADOS_EN_ESPERA,
                        Cita::ESTADO_EN_ATENCION,
                    ])
                    ->orderBy('inicio_at')
                    ->limit(50);

                $this->constrainSalaActiva($query, 'citas');

                foreach ($query->get() as $cita) {
                    $this->ensureNumero($cita, $now);
                    $item = $this->serializeRecord($cita, self::TIPO_CONSULTA, '/clinica/citas', $tz);
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
                    ->with($pacienteWith)
                    ->whereBetween('inicio_at', [$inicio, $fin])
                    ->whereIn('estado', [
                        ...GroomingTurno::ESTADOS_EN_ESPERA,
                        GroomingTurno::ESTADO_EN_PROCESO,
                    ])
                    ->orderBy('inicio_at')
                    ->limit(50);

                $this->constrainSalaActiva($query, 'grooming_turnos');

                foreach ($query->get() as $turno) {
                    $this->ensureNumero($turno, $now);
                    $item = $this->serializeRecord($turno, self::TIPO_GROOMING, '/servicios/grooming', $tz);
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
            'visible' => (count($espera) + count($proximas) + count($enCurso)) > 0,
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
        $paciente->loadMissing('propietario');

        return DB::transaction(function () use ($user, $tenant, $paciente, $tipo, $tz, $now): array {
            $this->lockNumeroDia($now);

            if ($tipo === self::TIPO_CONSULTA) {
                $this->assertModule($tenant, 'citas');
                $existing = $this->citaEnColaHoy($paciente, $now);
                if ($existing !== null) {
                    $this->marcarEnviado($existing, $now);
                    $existing->setRelation('paciente', $paciente);

                    return [
                        'created' => false,
                        'item' => $this->serializeRecord($existing, self::TIPO_CONSULTA, '/clinica/citas', $tz),
                    ];
                }

                $payload = [
                    'paciente_id' => $paciente->id,
                    'inicio_at' => $now->copy()->startOfMinute(),
                    'duracion_minutos' => 15,
                    'estado' => Cita::ESTADO_PROGRAMADA,
                    'motivo' => 'Sala de espera',
                    'sala_espera_enviado_at' => $now,
                    'created_by_id' => $user->id,
                    'updated_by_id' => $user->id,
                ];
                $numero = $this->siguienteNumeroDia($now);
                if ($numero !== null) {
                    $payload['sala_espera_numero'] = $numero;
                }

                $cita = Cita::query()->create($payload);
                $cita->setRelation('paciente', $paciente);

                return [
                    'created' => true,
                    'item' => $this->serializeRecord($cita, self::TIPO_CONSULTA, '/clinica/citas', $tz),
                ];
            }

            $this->assertModule($tenant, 'grooming');
            $existing = $this->groomingEnColaHoy($paciente, $now);
            if ($existing !== null) {
                $this->marcarEnviado($existing, $now);
                $existing->setRelation('paciente', $paciente);

                return [
                    'created' => false,
                    'item' => $this->serializeRecord($existing, self::TIPO_GROOMING, '/servicios/grooming', $tz),
                ];
            }

            $payload = [
                'paciente_id' => $paciente->id,
                'inicio_at' => $now->copy()->startOfMinute(),
                'duracion_minutos' => 30,
                'estado' => GroomingTurno::ESTADO_PROGRAMADA,
                'servicio' => 'bano_higienico',
                'sala_espera_enviado_at' => $now,
                'created_by_id' => $user->id,
                'updated_by_id' => $user->id,
            ];
            $numero = $this->siguienteNumeroDia($now);
            if ($numero !== null) {
                $payload['sala_espera_numero'] = $numero;
            }

            $turno = GroomingTurno::query()->create($payload);
            $turno->setRelation('paciente', $paciente);

            return [
                'created' => true,
                'item' => $this->serializeRecord($turno, self::TIPO_GROOMING, '/servicios/grooming', $tz),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function item(User $user, string $tipo, string $id): array
    {
        $tipo = $this->normalizeTipo($tipo);
        $tz = (string) config('app.timezone');

        if ($tipo === self::TIPO_CONSULTA) {
            abort_unless($user->can('sala-espera.consulta') || $user->can('sala-espera.view'), 403);
            $record = Cita::query()
                ->with([
                    'paciente:id,nombre,especie,foto_path,propietario_id',
                    'paciente.propietario:id,nombres,apellidos,razon_social',
                ])
                ->whereKey($id)
                ->firstOrFail();

            return $this->serializeRecord($record, self::TIPO_CONSULTA, '/clinica/citas', $tz);
        }

        abort_unless($user->can('sala-espera.grooming') || $user->can('sala-espera.view'), 403);
        $record = GroomingTurno::query()
            ->with([
                'paciente:id,nombre,especie,foto_path,propietario_id',
                'paciente.propietario:id,nombres,apellidos,razon_social',
            ])
            ->whereKey($id)
            ->firstOrFail();

        return $this->serializeRecord($record, self::TIPO_GROOMING, '/servicios/grooming', $tz);
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

        $this->constrainColaPendiente($query, 'citas');

        return $query->orderBy('inicio_at')->first();
    }

    private function groomingEnColaHoy(Paciente $paciente, Carbon $now): ?GroomingTurno
    {
        $query = GroomingTurno::query()
            ->where('paciente_id', $paciente->id)
            ->whereBetween('inicio_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->whereIn('estado', GroomingTurno::ESTADOS_EN_ESPERA);

        $this->constrainColaPendiente($query, 'grooming_turnos');

        return $query->orderBy('inicio_at')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecord(Cita|GroomingTurno $record, string $tipo, string $href, string $tz): array
    {
        $inicioAt = $record->inicio_at;
        $at = $inicioAt instanceof Carbon
            ? $inicioAt
            : Carbon::parse((string) $inicioAt);

        $enviadoAt = $record->sala_espera_enviado_at ?? $at;
        $enviado = $enviadoAt instanceof Carbon
            ? $enviadoAt
            : Carbon::parse((string) $enviadoAt);
        $now = Carbon::now($tz);
        $minutos = (int) max(0, $enviado->timezone($tz)->diffInMinutes($now));

        $paciente = $record->paciente;
        $motivo = $tipo === self::TIPO_CONSULTA
            ? (string) ($record->motivo ?? '')
            : (string) ($record->servicio_label ?? $record->servicio ?? '');

        return [
            'id' => (string) $record->id,
            'tipo' => $tipo,
            'paciente' => (string) ($paciente?->nombre ?: '—'),
            'paciente_id' => $paciente?->id ? (string) $paciente->id : null,
            'propietario' => $paciente?->propietario?->displayName() ?: '—',
            'especie' => $paciente?->especie,
            'foto_url' => $paciente?->foto_url,
            'numero' => $record->sala_espera_numero !== null ? (int) $record->sala_espera_numero : null,
            'hora' => $at->timezone($tz)->format('H:i'),
            'estado' => (string) $record->estado,
            'motivo' => $motivo !== '' ? $motivo : null,
            'minutos_espera' => $minutos,
            'href' => $href,
            'hc_href' => $paciente?->id ? '/clinica/pacientes/'.$paciente->id : $href,
        ];
    }

    /**
     * @return array{
     *     tipo: string,
     *     fecha: string,
     *     count: int,
     *     espera: list<array<string, mixed>>,
     *     proximas: list<array<string, mixed>>,
     *     en_curso: list<array<string, mixed>>,
     *     can_marcar: bool,
     *     visible: bool
     * }
     */
    private function emptyQueue(User $user, string $tipo): array
    {
        $tz = (string) config('app.timezone');

        return [
            'tipo' => $tipo,
            'fecha' => Carbon::now($tz)->toDateString(),
            'count' => 0,
            'espera' => [],
            'proximas' => [],
            'en_curso' => [],
            'can_marcar' => $user->can('sala-espera.marcar-atendido'),
            'visible' => false,
        ];
    }

    /**
     * Un solo correlativo por clínica y día calendario (consulta + peluquería).
     * Al cambiar el día vuelve a 1.
     */
    private function siguienteNumeroDia(Carbon $now): ?int
    {
        if (! Schema::hasColumn('citas', 'sala_espera_numero')
            && ! Schema::hasColumn('grooming_turnos', 'sala_espera_numero')) {
            return null;
        }

        $inicio = $now->copy()->startOfDay();
        $fin = $now->copy()->endOfDay();
        $max = max(
            $this->maxNumeroHoy('citas', $inicio, $fin),
            $this->maxNumeroHoy('grooming_turnos', $inicio, $fin),
        );

        return $max + 1;
    }

    private function maxNumeroHoy(string $table, Carbon $inicio, Carbon $fin): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sala_espera_numero')) {
            return 0;
        }

        $query = DB::table($table)->whereNotNull('sala_espera_numero');

        if (Schema::hasColumn($table, 'sala_espera_enviado_at')) {
            $query->where(function ($inner) use ($inicio, $fin): void {
                $inner->whereBetween('sala_espera_enviado_at', [$inicio, $fin])
                    ->orWhere(function ($fallback) use ($inicio, $fin): void {
                        $fallback->whereNull('sala_espera_enviado_at')
                            ->whereBetween('inicio_at', [$inicio, $fin]);
                    });
            });
        } else {
            $query->whereBetween('inicio_at', [$inicio, $fin]);
        }

        return (int) $query->max('sala_espera_numero');
    }

    private function lockNumeroDia(Carbon $now): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $key = crc32('sala_espera_numero|'.$now->toDateString());
        DB::select('select pg_advisory_xact_lock(?)', [$key]);
    }

    private function normalizeTipo(string $tipo): string
    {
        $tipo = $tipo === 'cita' ? self::TIPO_CONSULTA : $tipo;
        if (! in_array($tipo, [self::TIPO_CONSULTA, self::TIPO_GROOMING], true)) {
            throw new RuntimeException('Tipo de sala de espera inválido.');
        }

        return $tipo;
    }

    /**
     * Solo lo que recepción mandó a sala (no la agenda normal).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Cita>|\Illuminate\Database\Eloquent\Builder<GroomingTurno>  $query
     */
    private function constrainSalaActiva($query, string $table): void
    {
        if (Schema::hasColumn($table, 'sala_espera_enviado_at')) {
            $query->whereNotNull('sala_espera_enviado_at');
        } else {
            $query->whereRaw('1 = 0');
        }

        if (Schema::hasColumn($table, 'sala_espera_atendido_at')) {
            $query->whereNull('sala_espera_atendido_at');
        }
    }

    /**
     * Pendiente de sala o cita/turno del día aún no enviada (para reutilizar al pulsar Sala).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Cita>|\Illuminate\Database\Eloquent\Builder<GroomingTurno>  $query
     */
    private function constrainColaPendiente($query, string $table): void
    {
        if (Schema::hasColumn($table, 'sala_espera_atendido_at')) {
            $query->whereNull('sala_espera_atendido_at');
        }
    }

    private function marcarEnviado(Cita|GroomingTurno $record, Carbon $now): void
    {
        $updates = [];

        if (Schema::hasColumn($record->getTable(), 'sala_espera_enviado_at')
            && $record->sala_espera_enviado_at === null) {
            $updates['sala_espera_enviado_at'] = now();
        }

        if ($updates !== []) {
            $record->forceFill($updates)->save();
        }

        $this->ensureNumero($record, $now);
    }

    private function ensureNumero(Cita|GroomingTurno $record, Carbon $now): void
    {
        if (! Schema::hasColumn($record->getTable(), 'sala_espera_numero')) {
            return;
        }

        if ($record->sala_espera_numero !== null) {
            return;
        }

        $assign = function () use ($record, $now): void {
            $record->refresh();
            if ($record->sala_espera_numero !== null) {
                return;
            }

            $numero = $this->siguienteNumeroDia($now);
            if ($numero === null) {
                return;
            }

            $record->forceFill(['sala_espera_numero' => $numero])->save();
        };

        if (DB::transactionLevel() > 0) {
            $assign();

            return;
        }

        DB::transaction(function () use ($now, $assign): void {
            $this->lockNumeroDia($now);
            $assign();
        });
    }

    /**
     * @return array{consulta: bool, grooming: bool}
     */
    public function iconosVisibles(User $user, ?Tenant $tenant): array
    {
        $consulta = false;
        $grooming = false;

        if ($user->can('sala-espera.consulta') && TenantModuleAccess::isEnabled($tenant, 'citas')) {
            $consulta = $this->colaTieneGente($user, $tenant, self::TIPO_CONSULTA);
        }

        if ($user->can('sala-espera.grooming') && TenantModuleAccess::isEnabled($tenant, 'grooming')) {
            $grooming = $this->colaTieneGente($user, $tenant, self::TIPO_GROOMING);
        }

        return [
            'consulta' => $consulta,
            'grooming' => $grooming,
        ];
    }

    private function colaTieneGente(User $user, ?Tenant $tenant, string $tipo): bool
    {
        $queue = $this->forQueue($user, $tenant, $tipo);

        return ($queue['visible'] ?? false) === true;
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
