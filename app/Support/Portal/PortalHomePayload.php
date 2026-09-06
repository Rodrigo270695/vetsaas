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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PortalHomePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Propietario $propietario, ?string $mascotaId = null): array
    {
        $pacientes = Paciente::query()
            ->where('propietario_id', $propietario->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'foto_path', 'especie', 'raza']);

        $selected = self::resolveSelected($pacientes, $mascotaId);

        $nombres = trim((string) $propietario->nombres);
        $saludo = $nombres !== ''
            ? explode(' ', $nombres)[0]
            : $propietario->displayName();

        $portal = PortalPropietario::query()
            ->where('propietario_id', $propietario->id)
            ->first();

        return [
            'saludo' => $saludo,
            'mascotas' => $pacientes->map(fn (Paciente $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'foto_url' => $p->foto_url,
                'especie' => $p->especie,
                'raza' => $p->raza,
            ])->values()->all(),
            'mascota' => $selected === null ? null : [
                'id' => $selected->id,
                'nombre' => $selected->nombre,
                'foto_url' => $selected->foto_url,
                'especie' => $selected->especie,
                'raza' => $selected->raza,
            ],
            'citas' => $selected ? self::citas($selected->id) : ['proxima' => null, 'historial' => []],
            'grooming' => $selected ? self::grooming($selected->id) : [],
            'consultas' => $selected ? self::consultas($selected->id) : [],
            'vacunas' => $selected ? self::vacunas($selected->id) : ['proxima' => null, 'historial' => []],
            'avisos' => $portal ? self::avisos($portal->id, $selected?->id) : [],
        ];
    }

    /**
     * @return array{proxima: ?array<string, mixed>, historial: list<array<string, mixed>>}
     */
    private static function citas(string $pacienteId): array
    {
        $proxima = Cita::query()
            ->where('paciente_id', $pacienteId)
            ->whereIn('estado', Cita::ESTADOS_EN_ESPERA)
            ->where('inicio_at', '>=', now()->subHours(2))
            ->orderBy('inicio_at')
            ->first();

        $rows = Cita::query()
            ->where('paciente_id', $pacienteId)
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA])
            ->orderByDesc('inicio_at')
            ->limit(20)
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
    private static function grooming(string $pacienteId): array
    {
        $turnos = GroomingTurno::query()
            ->where('paciente_id', $pacienteId)
            ->whereNotIn('estado', [GroomingTurno::ESTADO_CANCELADA])
            ->with(['fotos' => fn ($q) => $q->orderBy('created_at')])
            ->orderByDesc('inicio_at')
            ->limit(8)
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
     * @return list<array<string, mixed>>
     */
    private static function consultas(string $pacienteId): array
    {
        return Consulta::query()
            ->whereHas('historiaClinica', fn ($q) => $q->where('paciente_id', $pacienteId))
            ->whereNotNull('cerrada_at')
            ->orderByDesc('atendido_at')
            ->limit(12)
            ->get(['id', 'atendido_at', 'motivo', 'plan', 'analisis', 'medico_tratante', 'peso_kg'])
            ->map(fn (Consulta $c): array => [
                'id' => $c->id,
                'atendido_at' => $c->atendido_at->toIso8601String(),
                'motivo' => $c->motivo,
                'plan' => $c->plan,
                'analisis' => $c->analisis,
                'medico' => $c->medico_tratante,
                'peso_kg' => $c->peso_kg,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{proxima: ?array<string, mixed>, historial: list<array<string, mixed>>}
     */
    private static function vacunas(string $pacienteId): array
    {
        $rows = VacunaAplicada::query()
            ->where('paciente_id', $pacienteId)
            ->orderByDesc('aplicada_at')
            ->limit(12)
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
            ->limit(12)
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
     * @param  Collection<int, Paciente>  $pacientes
     */
    private static function resolveSelected(Collection $pacientes, ?string $mascotaId): ?Paciente
    {
        if ($pacientes->isEmpty()) {
            return null;
        }

        if ($mascotaId !== null && $mascotaId !== '') {
            $match = $pacientes->firstWhere('id', $mascotaId);
            if ($match instanceof Paciente) {
                return $match;
            }
        }

        return $pacientes->first();
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
