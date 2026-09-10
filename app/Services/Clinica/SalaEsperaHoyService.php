<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Models\Cita;
use App\Models\GroomingTurno;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantModuleAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class SalaEsperaHoyService
{
    /**
     * @return array{
     *     fecha: string,
     *     count: int,
     *     espera: list<array<string, mixed>>,
     *     en_curso: list<array<string, mixed>>
     * }
     */
    public function forUser(User $user, ?Tenant $tenant): array
    {
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);
        $inicio = $now->copy()->startOfDay();
        $fin = $now->copy()->endOfDay();

        $includeCitas = $user->can('citas.view')
            && TenantModuleAccess::isEnabled($tenant, 'citas')
            && Schema::hasTable('citas');
        $includeGrooming = $user->can('grooming.view')
            && TenantModuleAccess::isEnabled($tenant, 'grooming')
            && Schema::hasTable('grooming_turnos');

        $espera = [];
        $enCurso = [];

        if ($includeCitas) {
            $citas = Cita::query()
                ->with(['paciente:id,nombre'])
                ->whereBetween('inicio_at', [$inicio, $fin])
                ->whereIn('estado', [
                    ...Cita::ESTADOS_EN_ESPERA,
                    Cita::ESTADO_EN_ATENCION,
                ])
                ->orderBy('inicio_at')
                ->limit(40)
                ->get();

            foreach ($citas as $cita) {
                $item = $this->serialize(
                    (string) $cita->id,
                    'cita',
                    (string) ($cita->paciente?->nombre ?: '—'),
                    $cita->inicio_at,
                    (string) $cita->estado,
                    '/clinica/citas',
                    $tz,
                );
                if (in_array($cita->estado, Cita::ESTADOS_EN_ESPERA, true)) {
                    $espera[] = $item;
                } else {
                    $enCurso[] = $item;
                }
            }
        }

        if ($includeGrooming) {
            $turnos = GroomingTurno::query()
                ->with(['paciente:id,nombre'])
                ->whereBetween('inicio_at', [$inicio, $fin])
                ->whereIn('estado', [
                    ...GroomingTurno::ESTADOS_EN_ESPERA,
                    GroomingTurno::ESTADO_EN_PROCESO,
                ])
                ->orderBy('inicio_at')
                ->limit(40)
                ->get();

            foreach ($turnos as $turno) {
                $item = $this->serialize(
                    (string) $turno->id,
                    'grooming',
                    (string) ($turno->paciente?->nombre ?: '—'),
                    $turno->inicio_at,
                    (string) $turno->estado,
                    '/servicios/grooming',
                    $tz,
                );
                if (in_array($turno->estado, GroomingTurno::ESTADOS_EN_ESPERA, true)) {
                    $espera[] = $item;
                } else {
                    $enCurso[] = $item;
                }
            }
        }

        usort($espera, $this->byHora(...));
        usort($enCurso, $this->byHora(...));

        $espera = array_slice($espera, 0, 30);
        $enCurso = array_slice($enCurso, 0, 20);

        return [
            'fecha' => $now->toDateString(),
            'count' => count($espera),
            'espera' => $espera,
            'en_curso' => $enCurso,
        ];
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

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function byHora(array $a, array $b): int
    {
        return strcmp((string) ($a['hora'] ?? ''), (string) ($b['hora'] ?? ''));
    }
}
