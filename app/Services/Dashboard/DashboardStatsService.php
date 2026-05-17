<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\CajaSesion;
use App\Models\Cita;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\Internamiento;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\User;
use App\Models\VacunaAplicada;
use App\Models\Venta;
use App\Tenancy\TenantManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Métricas del panel principal. Solo tiene sentido con tenant resuelto
 * (search_path del schema de la clínica).
 */
final class DashboardStatsService
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<string, mixed>
     */
    public function build(User $user, array $capabilities): array
    {
        abort_unless($this->tenantManager->check(), 403);

        $tz = (string) config('app.timezone');
        $now = now($tz);
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        $moneda = $this->resolveMoneda();

        $kpis = [
            'citas_hoy' => 0,
            'citas_pendientes_hoy' => 0,
            'consultas_hoy' => 0,
            'consultas_abiertas' => 0,
            'ventas_hoy_count' => 0,
            'ventas_hoy_total' => '0.00',
            'pacientes_nuevos_mes' => 0,
            'propietarios_nuevos_mes' => 0,
            'vacunaciones_mes' => 0,
            'grooming_hoy' => 0,
            'hotel_en_estancia' => 0,
            'internamientos_activos' => 0,
            'fel_pendientes' => 0,
            'productos_activos' => 0,
            'alertas_stock' => 0,
            'caja_abierta' => false,
        ];

        if ($capabilities['citas'] ?? false) {
            $citasHoy = Cita::query()
                ->whereBetween('inicio_at', [$todayStart, $todayEnd]);

            $kpis['citas_hoy'] = (clone $citasHoy)->count();
            $kpis['citas_pendientes_hoy'] = (clone $citasHoy)
                ->whereIn('estado', [Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA])
                ->count();
        }

        if ($capabilities['consultas'] ?? false) {
            $kpis['consultas_hoy'] = Consulta::query()
                ->whereBetween('atendido_at', [$todayStart, $todayEnd])
                ->count();

            $kpis['consultas_abiertas'] = Consulta::query()
                ->whereNull('cerrada_at')
                ->count();
        }

        if ($capabilities['ventas'] ?? false) {
            $ventasHoy = $this->ventasPagadasEntre($todayStart, $todayEnd);

            $kpis['ventas_hoy_count'] = (clone $ventasHoy)->count();
            $kpis['ventas_hoy_total'] = number_format((float) (clone $ventasHoy)->sum('total'), 2, '.', '');

            $kpis['fel_pendientes'] = Venta::query()
                ->where('estado', Venta::ESTADO_PAGADO)
                ->where('fel_estado', Venta::FEL_PENDIENTE)
                ->count();
        }

        if ($capabilities['pacientes'] ?? false) {
            $kpis['pacientes_nuevos_mes'] = Paciente::query()
                ->where('created_at', '>=', $monthStart)
                ->count();
        }

        if ($capabilities['propietarios'] ?? false) {
            $kpis['propietarios_nuevos_mes'] = Propietario::query()
                ->where('created_at', '>=', $monthStart)
                ->count();
        }

        if ($capabilities['vacunaciones'] ?? false) {
            $kpis['vacunaciones_mes'] = VacunaAplicada::query()
                ->where('aplicada_at', '>=', $monthStart)
                ->count();
        }

        if ($capabilities['grooming'] ?? false) {
            $kpis['grooming_hoy'] = GroomingTurno::query()
                ->whereBetween('inicio_at', [$todayStart, $todayEnd])
                ->whereNotIn('estado', [GroomingTurno::ESTADO_CANCELADA, GroomingTurno::ESTADO_NO_ASISTIO])
                ->count();
        }

        if ($capabilities['hotel'] ?? false) {
            $kpis['hotel_en_estancia'] = HotelEstancia::query()
                ->whereIn('estado', [
                    HotelEstancia::ESTADO_CONFIRMADA,
                    HotelEstancia::ESTADO_EN_ESTANCIA,
                ])
                ->count();
        }

        if ($capabilities['hospitalizacion'] ?? false) {
            $kpis['internamientos_activos'] = Internamiento::query()
                ->where('estado', Internamiento::ESTADO_ACTIVO)
                ->count();
        }

        if ($capabilities['productos'] ?? false) {
            $kpis['productos_activos'] = Producto::query()
                ->where('activo', true)
                ->count();
        }

        if ($capabilities['alertas_stock'] ?? false) {
            $kpis['alertas_stock'] = $this->countStockAlerts($user);
        }

        if ($capabilities['caja_sesiones'] ?? false) {
            $kpis['caja_abierta'] = CajaSesion::query()
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->where('opened_by_id', $user->id)
                ->exists();
        }

        return [
            'moneda' => $moneda,
            'kpis' => $kpis,
            'ventas_por_dia' => ($capabilities['ventas'] ?? false)
                ? $this->ventasPorDia($now)
                : [],
            'consultas_por_dia' => ($capabilities['consultas'] ?? false)
                ? $this->consultasPorDia($now)
                : [],
            'ventas_por_metodo' => ($capabilities['ventas'] ?? false)
                ? $this->ventasPorMetodoSemana($weekStart, $weekEnd)
                : [],
            'citas_por_estado' => ($capabilities['citas'] ?? false)
                ? $this->citasPorEstadoSemana($weekStart, $weekEnd)
                : [],
            'proximas_citas' => ($capabilities['citas'] ?? false)
                ? $this->proximasCitas($now)
                : [],
        ];
    }

    private function resolveMoneda(): string
    {
        $moneda = ClinicSetting::query()->value('moneda');

        return is_string($moneda) && $moneda !== '' ? strtoupper($moneda) : 'PEN';
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Venta> */
    private function ventasPagadasEntre(CarbonInterface $start, CarbonInterface $end)
    {
        return Venta::query()
            ->where('estado', Venta::ESTADO_PAGADO)
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('fecha_pago', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end): void {
                        $q2->whereNull('fecha_pago')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });
    }

    private function countStockAlerts(User $user): int
    {
        $tenantId = $user->tenant_id;
        if ($tenantId === null && $this->tenantManager->check()) {
            $tenantId = $this->tenantManager->id();
        }

        if ($tenantId === null) {
            return 0;
        }

        $sedeId = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->value('id');

        if ($sedeId === null) {
            return 0;
        }

        return (int) Producto::query()
            ->leftJoin('existencias_sede as es', function ($join) use ($sedeId): void {
                $join->on('es.producto_id', '=', 'productos.id')
                    ->where('es.sede_id', '=', $sedeId);
            })
            ->where('productos.activo', true)
            ->where(function ($q): void {
                $q->whereRaw('COALESCE(es.cantidad, 0) <= 0')
                    ->orWhere(function ($q2): void {
                        $q2->whereNotNull('productos.stock_minimo')
                            ->where('productos.stock_minimo', '>', 0)
                            ->whereRaw('COALESCE(es.cantidad, 0) <= productos.stock_minimo');
                    });
            })
            ->count();
    }

    /**
     * @return list<array{date: string, label: string, total: float, count: int}>
     */
    private function ventasPorDia(CarbonInterface $now): array
    {
        $locale = app()->getLocale();
        $rows = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $start = $day->copy()->startOfDay();
            $end = $day->copy()->endOfDay();

            $agg = $this->ventasPagadasEntre($start, $end)
                ->selectRaw('COUNT(*) as ventas_count, COALESCE(SUM(total), 0) as ventas_total')
                ->first();

            $rows[] = [
                'date' => $day->toDateString(),
                'label' => $day->locale($locale)->isoFormat('ddd D/M'),
                'total' => round((float) ($agg->ventas_total ?? 0), 2),
                'count' => (int) ($agg->ventas_count ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{date: string, label: string, count: int}>
     */
    private function consultasPorDia(CarbonInterface $now): array
    {
        $locale = app()->getLocale();
        $rows = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $start = $day->copy()->startOfDay();
            $end = $day->copy()->endOfDay();

            $count = Consulta::query()
                ->whereBetween('atendido_at', [$start, $end])
                ->count();

            $rows[] = [
                'date' => $day->toDateString(),
                'label' => $day->locale($locale)->isoFormat('ddd D/M'),
                'count' => $count,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{metodo: string, count: int, total: float}>
     */
    private function ventasPorMetodoSemana(CarbonInterface $weekStart, CarbonInterface $weekEnd): array
    {
        $metodoExpr = "COALESCE(NULLIF(TRIM(metodo_pago), ''), 'sin_especificar')";

        /** @var Collection<int, object{metodo: ?string, aggregate: int, total: string}> $grouped */
        $grouped = $this->ventasPagadasEntre($weekStart, $weekEnd)
            ->select(
                DB::raw("{$metodoExpr} as metodo"),
                DB::raw('COUNT(*) as aggregate'),
                DB::raw('COALESCE(SUM(total), 0) as total'),
            )
            ->groupBy(DB::raw($metodoExpr))
            ->orderByDesc('total')
            ->get();

        return $grouped
            ->map(fn (object $row): array => [
                'metodo' => (string) $row->metodo,
                'count' => (int) $row->aggregate,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{estado: string, count: int}>
     */
    private function citasPorEstadoSemana(CarbonInterface $weekStart, CarbonInterface $weekEnd): array
    {
        /** @var Collection<int, object{estado: string, aggregate: int}> $grouped */
        $grouped = Cita::query()
            ->whereBetween('inicio_at', [$weekStart, $weekEnd])
            ->select('estado', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('estado')
            ->orderBy('estado')
            ->get();

        return $grouped
            ->map(fn (object $row): array => [
                'estado' => (string) $row->estado,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function proximasCitas(CarbonInterface $now): array
    {
        return Cita::query()
            ->with([
                'paciente:id,nombre',
                'veterinario:id,name',
                'sede:id,nombre,codigo',
            ])
            ->where('inicio_at', '>=', $now)
            ->whereIn('estado', [Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA])
            ->orderBy('inicio_at')
            ->limit(6)
            ->get()
            ->map(fn (Cita $cita): array => [
                'id' => $cita->id,
                'inicio_at' => $cita->inicio_at?->toIso8601String(),
                'estado' => $cita->estado,
                'motivo' => $cita->motivo,
                'paciente_nombre' => $cita->paciente?->nombre,
                'veterinario_nombre' => $cita->veterinario?->name,
                'sede_nombre' => $cita->sede?->nombre,
            ])
            ->all();
    }
}
