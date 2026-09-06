<?php

declare(strict_types=1);

namespace App\Support\Portal;

use App\Models\Cita;
use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Models\VacunaAplicada;
use Illuminate\Support\Collection;

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

        $cita = null;
        $vacuna = null;
        if ($selected !== null) {
            $proximaCita = Cita::query()
                ->where('paciente_id', $selected->id)
                ->whereIn('estado', Cita::ESTADOS_EN_ESPERA)
                ->where('inicio_at', '>=', now()->subHours(2))
                ->orderBy('inicio_at')
                ->first();

            if ($proximaCita !== null) {
                $cita = [
                    'id' => $proximaCita->id,
                    'inicio_at' => $proximaCita->inicio_at->toIso8601String(),
                    'motivo' => $proximaCita->motivo,
                    'estado' => $proximaCita->estado,
                ];
            }

            $proximaVacuna = VacunaAplicada::query()
                ->where('paciente_id', $selected->id)
                ->whereNotNull('fecha_proxima_sugerida')
                ->whereDate('fecha_proxima_sugerida', '>=', now()->toDateString())
                ->orderBy('fecha_proxima_sugerida')
                ->first();

            if ($proximaVacuna !== null) {
                $vacuna = [
                    'nombre' => $proximaVacuna->nombre_vacuna,
                    'fecha' => $proximaVacuna->fecha_proxima_sugerida?->toDateString(),
                    'categoria' => $proximaVacuna->categoria_registro,
                ];
            }
        }

        $nombres = trim((string) $propietario->nombres);
        $saludo = $nombres !== ''
            ? explode(' ', $nombres)[0]
            : $propietario->displayName();

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
            'cita' => $cita,
            'vacuna' => $vacuna,
        ];
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
