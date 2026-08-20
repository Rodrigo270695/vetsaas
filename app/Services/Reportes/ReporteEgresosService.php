<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\CajaEgreso;
use App\Models\ClinicSetting;
use App\Models\Sede;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte general de egresos de caja (arqueo / salidas de efectivo).
 */
final class ReporteEgresosService
{
    /**
     * @return array{
     *     moneda: string,
     *     filtros: array{fecha_desde: string, fecha_hasta: string, periodo: string, sede_id: ?string, motivo: ?string},
     *     totales: array{cantidad: int, monto: float},
     *     por_motivo: list<array{motivo: string, motivo_label: string, cantidad: int, monto: float}>,
     *     items: list<array<string, mixed>>,
     *     sedes: list<array{id: string, nombre: string}>,
     *     motivos: list<array{value: string, label: string}>
     * }
     */
    public function egresos(
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?string $periodo = null,
        ?string $sedeId = null,
        ?string $motivo = null,
    ): array {
        [$periodoKey, $start, $end] = $this->resolveRange($fechaDesde, $fechaHasta, $periodo);

        $sedeId = is_string($sedeId) && preg_match('/^[0-9a-f-]{36}$/i', $sedeId) === 1
            ? $sedeId
            : null;
        $motivo = is_string($motivo) && in_array($motivo, CajaEgreso::MOTIVOS, true)
            ? $motivo
            : null;

        $sedes = $this->sedesOpciones();
        $sedeIdsPermitidos = array_column($sedes, 'id');
        if ($sedeId !== null && ! in_array($sedeId, $sedeIdsPermitidos, true)) {
            $sedeId = null;
        }

        if (! Schema::hasTable('caja_egresos') || ! Schema::hasTable('caja_sesiones')) {
            return $this->emptyPayload($periodoKey, $start, $end, $sedeId, $motivo, $sedes);
        }

        $q = DB::table('caja_egresos as e')
            ->join('caja_sesiones as s', 's.id', '=', 'e.caja_sesion_id')
            ->leftJoin('public.sedes as se', 'se.id', '=', 's.sede_id')
            ->leftJoin('public.users as u', 'u.id', '=', 'e.created_by_id')
            ->whereBetween('e.created_at', [$start, $end]);

        if ($sedeId !== null) {
            $q->where('s.sede_id', $sedeId);
        }
        if ($motivo !== null) {
            $q->where('e.motivo', $motivo);
        }

        /** @var \Illuminate\Support\Collection<int, object> $rows */
        $rows = $q
            ->orderByDesc('e.created_at')
            ->select([
                'e.id',
                'e.monto',
                'e.motivo',
                'e.notas',
                'e.created_at',
                'e.caja_sesion_id',
                's.sede_id',
                'se.nombre as sede_nombre',
                'u.name as registrado_por',
            ])
            ->limit(2000)
            ->get();

        $items = [];
        $totalMonto = 0.0;
        /** @var array<string, array{motivo: string, cantidad: int, monto: float}> $porMotivo */
        $porMotivo = [];

        foreach ($rows as $row) {
            $monto = round((float) ($row->monto ?? 0), 2);
            $motivoKey = (string) ($row->motivo ?? CajaEgreso::MOTIVO_OTROS);
            $totalMonto += $monto;

            if (! isset($porMotivo[$motivoKey])) {
                $porMotivo[$motivoKey] = [
                    'motivo' => $motivoKey,
                    'cantidad' => 0,
                    'monto' => 0.0,
                ];
            }
            $porMotivo[$motivoKey]['cantidad']++;
            $porMotivo[$motivoKey]['monto'] += $monto;

            $items[] = [
                'id' => (string) $row->id,
                'fecha' => $this->toDateTimeString($row->created_at ?? null),
                'sede_id' => $row->sede_id !== null ? (string) $row->sede_id : null,
                'sede_nombre' => is_string($row->sede_nombre) && $row->sede_nombre !== ''
                    ? $row->sede_nombre
                    : null,
                'motivo' => $motivoKey,
                'motivo_label' => CajaEgreso::labelMotivo($motivoKey),
                'monto' => $monto,
                'notas' => is_string($row->notas) && trim($row->notas) !== '' ? trim($row->notas) : null,
                'caja_sesion_id' => (string) $row->caja_sesion_id,
                'registrado_por' => is_string($row->registrado_por) && $row->registrado_por !== ''
                    ? $row->registrado_por
                    : null,
            ];
        }

        $porMotivoList = [];
        foreach ($porMotivo as $slice) {
            $porMotivoList[] = [
                'motivo' => $slice['motivo'],
                'motivo_label' => CajaEgreso::labelMotivo($slice['motivo']),
                'cantidad' => $slice['cantidad'],
                'monto' => round($slice['monto'], 2),
            ];
        }
        usort($porMotivoList, static fn (array $a, array $b): int => $b['monto'] <=> $a['monto']);

        return [
            'moneda' => $this->resolveMoneda(),
            'filtros' => [
                'fecha_desde' => $start->toDateString(),
                'fecha_hasta' => $end->toDateString(),
                'periodo' => $periodoKey,
                'sede_id' => $sedeId,
                'motivo' => $motivo,
            ],
            'totales' => [
                'cantidad' => count($items),
                'monto' => round($totalMonto, 2),
            ],
            'por_motivo' => $porMotivoList,
            'items' => $items,
            'sedes' => $sedes,
            'motivos' => collect(CajaEgreso::MOTIVOS)
                ->map(fn (string $m): array => [
                    'value' => $m,
                    'label' => CajaEgreso::labelMotivo($m),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<array{id: string, nombre: string}>  $sedes
     * @return array<string, mixed>
     */
    private function emptyPayload(
        string $periodo,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $sedeId,
        ?string $motivo,
        array $sedes,
    ): array {
        return [
            'moneda' => $this->resolveMoneda(),
            'filtros' => [
                'fecha_desde' => $start->toDateString(),
                'fecha_hasta' => $end->toDateString(),
                'periodo' => $periodo,
                'sede_id' => $sedeId,
                'motivo' => $motivo,
            ],
            'totales' => [
                'cantidad' => 0,
                'monto' => 0.0,
            ],
            'por_motivo' => [],
            'items' => [],
            'sedes' => $sedes,
            'motivos' => collect(CajaEgreso::MOTIVOS)
                ->map(fn (string $m): array => [
                    'value' => $m,
                    'label' => CajaEgreso::labelMotivo($m),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{id: string, nombre: string}>
     */
    private function sedesOpciones(): array
    {
        $tenantId = tenant_id();
        if ($tenantId === null) {
            return [];
        }

        return Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Sede $s): array => [
                'id' => (string) $s->id,
                'nombre' => (string) $s->nombre,
            ])
            ->values()
            ->all();
    }

    private function toDateTimeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)
                ->timezone((string) config('app.timezone'))
                ->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: CarbonInterface, 2: CarbonInterface}
     */
    private function resolveRange(?string $fechaDesde, ?string $fechaHasta, ?string $periodo): array
    {
        $tz = (string) config('app.timezone');
        $now = now($tz);

        if (is_string($fechaDesde) && is_string($fechaHasta)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta) === 1
        ) {
            $start = $now->copy()->parse($fechaDesde)->startOfDay();
            $end = $now->copy()->parse($fechaHasta)->endOfDay();
            if ($start->greaterThan($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            return ['personalizado', $start, $end];
        }

        return match ($periodo) {
            'semana' => [
                'semana',
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ],
            'mes_pasado' => [
                'mes_pasado',
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            default => [
                'mes_actual',
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
        };
    }

    private function resolveMoneda(): string
    {
        if (! Schema::hasTable('cfg_clinic_settings')) {
            return 'PEN';
        }

        $moneda = ClinicSetting::query()->value('moneda');

        return is_string($moneda) && $moneda !== '' ? strtoupper($moneda) : 'PEN';
    }
}
