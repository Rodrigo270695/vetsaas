<?php

declare(strict_types=1);

namespace App\Support\Portal;

use App\Models\Cita;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\GroomingTurno;
use App\Models\Paciente;
use App\Models\PortalAviso;
use App\Models\PortalPropietario;
use App\Models\Propietario;
use App\Models\VacunaAplicada;
use App\Support\Clinica\PublicClinicalHistoryPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class PortalHomePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function overview(Propietario $propietario): array
    {
        $pacientes = Paciente::query()
            ->where('propietario_id', $propietario->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $portal = PortalPropietario::query()
            ->where('propietario_id', $propietario->id)
            ->first();

        $nombres = trim((string) $propietario->nombres);
        $saludo = $nombres !== ''
            ? explode(' ', $nombres)[0]
            : $propietario->displayName();

        return [
            'saludo' => $saludo,
            'titular' => [
                'nombre' => $propietario->displayName(),
                'telefono' => $propietario->telefono,
                'email' => $propietario->email,
                'documento' => trim(implode(' ', array_filter([
                    $propietario->tipo_documento,
                    $propietario->numero_documento,
                ]))) ?: null,
                'direccion' => $propietario->direccion,
            ],
            'mascotas' => $pacientes->map(fn (Paciente $p): array => self::mascotaCard($p))->values()->all(),
            'avisos' => $portal ? self::avisos($portal->id, null) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pet(
        Propietario $propietario,
        Paciente $paciente,
        ?string $desde,
        ?string $hasta,
    ): array {
        abort_unless($paciente->propietario_id === $propietario->id, 404);

        $hc = PublicClinicalHistoryPayload::forPaciente($paciente);
        $timeline = array_values(array_filter(
            $hc['timeline'],
            static fn (array $item): bool => self::inRange((string) $item['ocurrido_at'], $desde, $hasta),
        ));

        return [
            'mascota' => [
                'id' => $paciente->id,
                'nombre' => $paciente->nombre,
                'foto_url' => $paciente->foto_url,
                'especie' => $paciente->especie,
                'raza' => $paciente->raza,
                'sexo' => $paciente->sexo,
                'fecha_nacimiento' => $paciente->fecha_nacimiento?->toDateString(),
                'color' => $paciente->color,
                'peso_kg' => $paciente->peso_kg,
            ],
            'citas' => self::citas($paciente->id, $desde, $hasta),
            'grooming' => self::grooming($paciente->id, $desde, $hasta),
            'vacunas' => self::vacunas($paciente->id, $desde, $hasta),
            'historial' => [
                'timeline' => $timeline,
                'pdf_url' => $hc['links']['historial_pdf'] ?? null,
                'permisos' => $hc['permisos'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mascotaCard(Paciente $p): array
    {
        $proxima = Cita::query()
            ->where('paciente_id', $p->id)
            ->whereIn('estado', Cita::ESTADOS_EN_ESPERA)
            ->where('inicio_at', '>=', now()->subHours(2))
            ->orderBy('inicio_at')
            ->first();

        $ultimaConsulta = Consulta::query()
            ->whereHas('historiaClinica', fn ($q) => $q->where('paciente_id', $p->id))
            ->whereNotNull('cerrada_at')
            ->orderByDesc('atendido_at')
            ->first(['atendido_at', 'motivo']);

        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'foto_url' => $p->foto_url,
            'especie' => $p->especie,
            'raza' => $p->raza,
            'sexo' => $p->sexo,
            'fecha_nacimiento' => $p->fecha_nacimiento?->toDateString(),
            'proxima_cita' => $proxima === null ? null : [
                'inicio_at' => $proxima->inicio_at->toIso8601String(),
                'motivo' => $proxima->motivo,
            ],
            'ultima_consulta' => $ultimaConsulta === null ? null : [
                'atendido_at' => $ultimaConsulta->atendido_at->toIso8601String(),
                'motivo' => $ultimaConsulta->motivo,
            ],
        ];
    }

    /**
     * @return array{proxima: ?array<string, mixed>, historial: list<array<string, mixed>>}
     */
    private static function citas(string $pacienteId, ?string $desde, ?string $hasta): array
    {
        $proxima = Cita::query()
            ->where('paciente_id', $pacienteId)
            ->whereIn('estado', Cita::ESTADOS_EN_ESPERA)
            ->where('inicio_at', '>=', now()->subHours(2))
            ->orderBy('inicio_at')
            ->first();

        $rowsQuery = Cita::query()
            ->where('paciente_id', $pacienteId)
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA]);
        self::constrainDate($rowsQuery, 'inicio_at', $desde, $hasta);
        $rows = $rowsQuery
            ->orderByDesc('inicio_at')
            ->limit(40)
            ->get(['id', 'inicio_at', 'motivo', 'estado', 'duracion_minutos']);

        $map = static fn (Cita $c): array => [
            'id' => $c->id,
            'inicio_at' => $c->inicio_at->toIso8601String(),
            'motivo' => $c->motivo,
            'estado' => $c->estado,
        ];

        return [
            'proxima' => $proxima instanceof Cita ? $map($proxima) : null,
            'historial' => $rows->map($map)->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function grooming(string $pacienteId, ?string $desde, ?string $hasta): array
    {
        $turnosQuery = GroomingTurno::query()
            ->where('paciente_id', $pacienteId)
            ->whereNotIn('estado', [GroomingTurno::ESTADO_CANCELADA]);
        self::constrainDate($turnosQuery, 'inicio_at', $desde, $hasta);
        $turnos = $turnosQuery
            ->with(['fotos' => fn ($q) => $q->orderBy('created_at')])
            ->orderByDesc('inicio_at')
            ->limit(30)
            ->get();

        return $turnos->map(function (GroomingTurno $t): array {
            return [
                'id' => $t->id,
                'inicio_at' => $t->inicio_at->toIso8601String(),
                'estado' => $t->estado,
                'servicio' => $t->servicio_label ?: $t->servicio,
                'notas' => $t->notas,
                'fotos' => $t->fotos->map(fn ($f): array => [
                    'id' => $f->id,
                    'tipo' => $f->tipo,
                    'url' => $f->url,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array{proxima: ?array<string, mixed>, historial: list<array<string, mixed>>}
     */
    private static function vacunas(string $pacienteId, ?string $desde, ?string $hasta): array
    {
        $rowsQuery = VacunaAplicada::query()
            ->where('paciente_id', $pacienteId);
        self::constrainDate($rowsQuery, 'aplicada_at', $desde, $hasta);
        $rows = $rowsQuery
            ->orderByDesc('aplicada_at')
            ->limit(40)
            ->get(['id', 'nombre_vacuna', 'aplicada_at', 'fecha_proxima_sugerida', 'categoria_registro']);

        $proxima = VacunaAplicada::query()
            ->where('paciente_id', $pacienteId)
            ->whereNotNull('fecha_proxima_sugerida')
            ->whereDate('fecha_proxima_sugerida', '>=', now()->toDateString())
            ->orderBy('fecha_proxima_sugerida')
            ->first();

        return [
            'proxima' => $proxima === null ? null : [
                'nombre' => $proxima->nombre_vacuna,
                'fecha' => $proxima->fecha_proxima_sugerida?->toDateString(),
                'categoria' => $proxima->categoria_registro,
            ],
            'historial' => $rows->map(fn (VacunaAplicada $v): array => [
                'nombre' => $v->nombre_vacuna,
                'aplicada_at' => $v->aplicada_at->toIso8601String(),
                'proxima' => $v->fecha_proxima_sugerida?->toDateString(),
                'categoria' => $v->categoria_registro,
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function avisos(string $portalId, ?string $pacienteId): array
    {
        if (! Schema::hasTable('portal_avisos')) {
            return [];
        }

        return PortalAviso::query()
            ->where('portal_propietario_id', $portalId)
            ->when($pacienteId, fn ($q) => $q->where(function ($q) use ($pacienteId): void {
                $q->whereNull('paciente_id')->orWhere('paciente_id', $pacienteId);
            }))
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (PortalAviso $a): array => [
                'id' => $a->id,
                'tipo' => $a->tipo,
                'titulo' => $a->titulo,
                'cuerpo' => $a->cuerpo,
                'created_at' => $a->created_at?->toIso8601String(),
                'leido' => $a->leido_at !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<*>  $query
     */
    private static function constrainDate(Builder $query, string $column, ?string $desde, ?string $hasta): void
    {
        if (is_string($desde) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) === 1) {
            $query->whereDate($column, '>=', $desde);
        }
        if (is_string($hasta) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) === 1) {
            $query->whereDate($column, '<=', $hasta);
        }
    }

    private static function inRange(string $iso, ?string $desde, ?string $hasta): bool
    {
        try {
            $day = Carbon::parse($iso)->toDateString();
        } catch (\Throwable) {
            return true;
        }

        if (is_string($desde) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) === 1 && $day < $desde) {
            return false;
        }
        if (is_string($hasta) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) === 1 && $day > $hasta) {
            return false;
        }

        return true;
    }

    public static function clinic(): array
    {
        $clinic = ClinicSetting::current();

        return [
            'nombre' => trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social))
                ?: (string) config('app.name'),
            'logo_url' => $clinic->logo_url,
        ];
    }
}
