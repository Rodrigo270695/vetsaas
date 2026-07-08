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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

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

        try {
            return $this->buildPayload($user, $capabilities);
        } catch (Throwable $e) {
            report($e);

            return $this->emptyPayload();
        }
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<string, mixed>
     */
    private function buildPayload(User $user, array $capabilities): array
    {
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

        $monthEnd = $now->copy()->endOfMonth();
        $prevMonthStart = $now->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $now->copy()->subMonth()->endOfMonth();

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
            'ingresos_mensuales' => ($capabilities['ventas'] ?? false)
                ? $this->ingresosMensuales($now)
                : [],
            'comparacion_ingresos_mes' => ($capabilities['ventas'] ?? false)
                ? $this->comparacionIngresosMes($monthStart, $monthEnd, $prevMonthStart, $prevMonthEnd)
                : null,
            'top_productos_mes' => ($capabilities['ventas'] ?? false)
                ? $this->topProductosMes($monthStart, $monthEnd)
                : [],
            'rentabilidad' => (($capabilities['ventas'] ?? false) && ($capabilities['productos'] ?? false))
                ? $this->rentabilidad('mes_actual')
                : null,
            'rentabilidad_grooming' => ($capabilities['grooming'] ?? false)
                ? $this->rentabilidadGrooming('mes_actual')
                : null,
            'fel_estado_mes' => ($capabilities['ventas'] ?? false)
                ? $this->felEstadoMes($monthStart, $monthEnd)
                : [],
            'vacunaciones_por_dia' => ($capabilities['vacunaciones'] ?? false)
                ? $this->vacunacionesPorDia($now)
                : [],
            'nuevos_clientes_mensuales' => (($capabilities['pacientes'] ?? false) || ($capabilities['propietarios'] ?? false))
                ? $this->nuevosClientesMensuales($now, $capabilities)
                : [],
            'citas_asistencia_mes' => ($capabilities['citas'] ?? false)
                ? $this->citasAsistenciaMes($monthStart, $monthEnd)
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'moneda' => 'PEN',
            'kpis' => [
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
            ],
            'ventas_por_dia' => [],
            'consultas_por_dia' => [],
            'ventas_por_metodo' => [],
            'citas_por_estado' => [],
            'proximas_citas' => [],
            'ingresos_mensuales' => [],
            'comparacion_ingresos_mes' => null,
            'top_productos_mes' => [],
            'rentabilidad' => null,
            'rentabilidad_grooming' => null,
            'fel_estado_mes' => [],
            'vacunaciones_por_dia' => [],
            'nuevos_clientes_mensuales' => [],
            'citas_asistencia_mes' => [],
        ];
    }

    private function resolveMoneda(): string
    {
        $moneda = ClinicSetting::query()->value('moneda');

        return is_string($moneda) && $moneda !== '' ? strtoupper($moneda) : 'PEN';
    }

    /** @return Builder<Venta> */
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
     * @return list<array{month: string, label: string, total: float, count: int, is_current: bool}>
     */
    private function ingresosMensuales(CarbonInterface $now): array
    {
        $locale = app()->getLocale();
        $rows = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $agg = $this->ventasPagadasEntre($start, $end)
                ->selectRaw('COUNT(*) as ventas_count, COALESCE(SUM(total), 0) as ventas_total')
                ->first();

            $rows[] = [
                'month' => $month->format('Y-m'),
                'label' => $month->locale($locale)->isoFormat('MMM YY'),
                'total' => round((float) ($agg->ventas_total ?? 0), 2),
                'count' => (int) ($agg->ventas_count ?? 0),
                'is_current' => $i === 0,
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     mes_actual_total: float,
     *     mes_anterior_total: float,
     *     variacion_pct: ?float,
     *     mes_actual_count: int,
     *     mes_anterior_count: int,
     *     ticket_promedio_actual: float,
     *     ticket_promedio_anterior: float
     * }
     */
    private function comparacionIngresosMes(
        CarbonInterface $monthStart,
        CarbonInterface $monthEnd,
        CarbonInterface $prevMonthStart,
        CarbonInterface $prevMonthEnd,
    ): array {
        $actualAgg = $this->ventasPagadasEntre($monthStart, $monthEnd)
            ->selectRaw('COUNT(*) as ventas_count, COALESCE(SUM(total), 0) as ventas_total')
            ->first();

        $prevAgg = $this->ventasPagadasEntre($prevMonthStart, $prevMonthEnd)
            ->selectRaw('COUNT(*) as ventas_count, COALESCE(SUM(total), 0) as ventas_total')
            ->first();

        $actualTotal = round((float) ($actualAgg->ventas_total ?? 0), 2);
        $prevTotal = round((float) ($prevAgg->ventas_total ?? 0), 2);
        $actualCount = (int) ($actualAgg->ventas_count ?? 0);
        $prevCount = (int) ($prevAgg->ventas_count ?? 0);

        $variacionPct = null;
        if ($prevTotal > 0) {
            $variacionPct = round((($actualTotal - $prevTotal) / $prevTotal) * 100, 1);
        }

        return [
            'mes_actual_total' => $actualTotal,
            'mes_anterior_total' => $prevTotal,
            'variacion_pct' => $variacionPct,
            'mes_actual_count' => $actualCount,
            'mes_anterior_count' => $prevCount,
            'ticket_promedio_actual' => $actualCount > 0
                ? round($actualTotal / $actualCount, 2)
                : 0.0,
            'ticket_promedio_anterior' => $prevCount > 0
                ? round($prevTotal / $prevCount, 2)
                : 0.0,
        ];
    }

    /**
     * @return list<array{nombre: string, total: float, cantidad: float}>
     */
    private function topProductosMes(CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        /** @var Collection<int, object{nombre: string, total: string, cantidad: string}> $rows */
        $rows = DB::table('venta_lineas')
            ->join('ventas', 'ventas.id', '=', 'venta_lineas.venta_id')
            ->where('ventas.estado', Venta::ESTADO_PAGADO)
            ->where(function ($q) use ($monthStart, $monthEnd): void {
                $q->whereBetween('ventas.fecha_pago', [$monthStart, $monthEnd])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd): void {
                        $q2->whereNull('ventas.fecha_pago')
                            ->whereBetween('ventas.created_at', [$monthStart, $monthEnd]);
                    });
            })
            ->whereNull('ventas.deleted_at')
            ->select(
                'venta_lineas.descripcion_snapshot as nombre',
                DB::raw('COALESCE(SUM(venta_lineas.subtotal), 0) as total'),
                DB::raw('COALESCE(SUM(venta_lineas.cantidad), 0) as cantidad'),
            )
            ->groupBy('venta_lineas.descripcion_snapshot')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return $rows
            ->map(fn (object $row): array => [
                'nombre' => (string) $row->nombre,
                'total' => round((float) $row->total, 2),
                'cantidad' => round((float) $row->cantidad, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Rentabilidad (margen de ganancia) de productos vendidos en un periodo.
     *
     * Margen = precio de venta (subtotal sin IGV de la línea) − costo de compra.
     * El costo se toma de `productos.precio_compra` (valor ACTUAL, no snapshot
     * histórico: el sistema no guarda el costo al momento de la venta).
     * Solo se consideran líneas con `producto_id` y `precio_compra` definidos;
     * las líneas de servicio y los productos sin costo quedan fuera del margen.
     *
     * @return array{
     *     periodo: string,
     *     desde: string,
     *     hasta: string,
     *     ingresos: float,
     *     costo: float,
     *     ganancia: float,
     *     margen_pct: ?float,
     *     unidades: float,
     *     productos_sin_costo: int,
     *     items: list<array{nombre: string, ingreso: float, costo: float, ganancia: float, cantidad: float, margen_pct: ?float}>
     * }
     */
    public function rentabilidad(string $periodo = 'mes_actual'): array
    {
        $tz = (string) config('app.timezone');
        [$periodo, $start, $end] = $this->resolveRentabilidadRange($periodo, now($tz));

        $base = fn () => DB::table('venta_lineas')
            ->join('ventas', 'ventas.id', '=', 'venta_lineas.venta_id')
            ->join('productos', 'productos.id', '=', 'venta_lineas.producto_id')
            ->where('ventas.estado', Venta::ESTADO_PAGADO)
            ->whereNull('ventas.deleted_at')
            ->whereNotNull('venta_lineas.producto_id')
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('ventas.fecha_pago', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end): void {
                        $q2->whereNull('ventas.fecha_pago')
                            ->whereBetween('ventas.created_at', [$start, $end]);
                    });
            });

        $agg = $base()
            ->whereNotNull('productos.precio_compra')
            ->selectRaw('
                COALESCE(SUM(venta_lineas.subtotal), 0) as ingresos,
                COALESCE(SUM(venta_lineas.cantidad * productos.precio_compra), 0) as costo,
                COALESCE(SUM(venta_lineas.cantidad), 0) as unidades
            ')
            ->first();

        $ingresos = round((float) ($agg->ingresos ?? 0), 2);
        $costo = round((float) ($agg->costo ?? 0), 2);
        $ganancia = round($ingresos - $costo, 2);
        $unidades = round((float) ($agg->unidades ?? 0), 2);

        $productosSinCosto = (int) $base()
            ->whereNull('productos.precio_compra')
            ->distinct()
            ->count('productos.id');

        /** @var Collection<int, object{nombre: string, ingreso: string, costo: string, cantidad: string}> $topRows */
        $topRows = $base()
            ->whereNotNull('productos.precio_compra')
            ->select(
                'productos.nombre as nombre',
                DB::raw('COALESCE(SUM(venta_lineas.subtotal), 0) as ingreso'),
                DB::raw('COALESCE(SUM(venta_lineas.cantidad * productos.precio_compra), 0) as costo'),
                DB::raw('COALESCE(SUM(venta_lineas.cantidad), 0) as cantidad'),
            )
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc(DB::raw('COALESCE(SUM(venta_lineas.subtotal), 0) - COALESCE(SUM(venta_lineas.cantidad * productos.precio_compra), 0)'))
            ->limit(6)
            ->get();

        $items = $topRows
            ->map(function (object $row): array {
                $ingreso = round((float) $row->ingreso, 2);
                $costoItem = round((float) $row->costo, 2);
                $gananciaItem = round($ingreso - $costoItem, 2);

                return [
                    'nombre' => (string) $row->nombre,
                    'ingreso' => $ingreso,
                    'costo' => $costoItem,
                    'ganancia' => $gananciaItem,
                    'cantidad' => round((float) $row->cantidad, 2),
                    'margen_pct' => $ingreso > 0 ? round(($gananciaItem / $ingreso) * 100, 1) : null,
                ];
            })
            ->values()
            ->all();

        return [
            'periodo' => $periodo,
            'desde' => $start->toDateString(),
            'hasta' => $end->toDateString(),
            'ingresos' => $ingresos,
            'costo' => $costo,
            'ganancia' => $ganancia,
            'margen_pct' => $ingresos > 0 ? round(($ganancia / $ingresos) * 100, 1) : null,
            'unidades' => $unidades,
            'productos_sin_costo' => $productosSinCosto,
            'items' => $items,
        ];
    }

    /**
     * Rentabilidad de grooming: precio del servicio menos el costo de los
     * insumos que consume, sobre los turnos completados en el periodo.
     *
     * @return array{
     *     periodo: string,
     *     desde: string,
     *     hasta: string,
     *     ingresos: float,
     *     costo: float,
     *     ganancia: float,
     *     margen_pct: ?float,
     *     unidades: float,
     *     servicios_sin_insumos: int,
     *     items: list<array{nombre: string, ingreso: float, costo: float, ganancia: float, cantidad: float, margen_pct: ?float}>
     * }
     */
    public function rentabilidadGrooming(string $periodo = 'mes_actual'): array
    {
        $tz = (string) config('app.timezone');
        [$periodo, $start, $end] = $this->resolveRentabilidadRange($periodo, now($tz));

        $empty = [
            'periodo' => $periodo,
            'desde' => $start->toDateString(),
            'hasta' => $end->toDateString(),
            'ingresos' => 0.0,
            'costo' => 0.0,
            'ganancia' => 0.0,
            'margen_pct' => null,
            'unidades' => 0.0,
            'servicios_sin_insumos' => 0,
            'items' => [],
        ];

        if (! Schema::hasTable('grooming_servicios')
            || ! Schema::hasTable('grooming_servicio_insumo')
            || ! Schema::hasTable('venta_lineas')) {
            return $empty;
        }

        /** @var Collection<int, object{id: string, nombre: string, precio_lista: string}> $servicios */
        $servicios = DB::table('grooming_servicios')->get(['id', 'nombre', 'precio_lista']);

        if ($servicios->isEmpty()) {
            return $empty;
        }

        /** @var Collection<string, string> $costos costo de insumos por servicio (id => suma) */
        $costos = DB::table('grooming_servicio_insumo')
            ->groupBy('grooming_servicio_id')
            ->select('grooming_servicio_id', DB::raw('SUM(precio) as costo'))
            ->pluck('costo', 'grooming_servicio_id');

        // Índice por nombre normalizado para emparejar la descripción de la línea
        // de venta (que puede venir como "Grooming · X" desde el cobro de un turno,
        // o como el nombre tal cual cuando se agrega como servicio manual en caja).
        $porNombre = [];
        foreach ($servicios as $servicio) {
            $porNombre[$this->normalizeGroomingName((string) $servicio->nombre)] = [
                'id' => (string) $servicio->id,
                'nombre' => (string) $servicio->nombre,
                'precio' => (float) $servicio->precio_lista,
                'costo_unit' => isset($costos[$servicio->id]) ? (float) $costos[$servicio->id] : null,
            ];
        }

        /** @var Collection<int, object{descripcion: string, subtotal: string, cantidad: string}> $lineas */
        $lineas = DB::table('venta_lineas as vl')
            ->join('ventas as v', 'v.id', '=', 'vl.venta_id')
            ->where('v.estado', Venta::ESTADO_PAGADO)
            ->whereNull('v.deleted_at')
            ->whereNull('vl.producto_id')
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('v.fecha_pago', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end): void {
                        $q2->whereNull('v.fecha_pago')
                            ->whereBetween('v.created_at', [$start, $end]);
                    });
            })
            ->select('vl.descripcion_snapshot as descripcion', 'vl.subtotal', 'vl.cantidad')
            ->get();

        $acumulado = [];
        $sinInsumos = [];

        foreach ($lineas as $linea) {
            $key = $this->normalizeGroomingName((string) $linea->descripcion);
            if (! isset($porNombre[$key])) {
                continue;
            }

            $servicio = $porNombre[$key];

            if ($servicio['costo_unit'] === null) {
                $sinInsumos[$servicio['id']] = true;

                continue;
            }

            $id = $servicio['id'];
            $acumulado[$id] ??= [
                'nombre' => $servicio['nombre'],
                'ingreso' => 0.0,
                'costo' => 0.0,
                'cantidad' => 0.0,
            ];

            $cantidad = (float) $linea->cantidad;
            $acumulado[$id]['ingreso'] += (float) $linea->subtotal;
            $acumulado[$id]['costo'] += $cantidad * $servicio['costo_unit'];
            $acumulado[$id]['cantidad'] += $cantidad;
        }

        $ingresos = 0.0;
        $costo = 0.0;
        $unidades = 0.0;
        $items = [];

        foreach ($acumulado as $row) {
            $ingresoItem = round($row['ingreso'], 2);
            $costoItem = round($row['costo'], 2);
            $gananciaItem = round($ingresoItem - $costoItem, 2);

            $ingresos += $ingresoItem;
            $costo += $costoItem;
            $unidades += $row['cantidad'];

            $items[] = [
                'nombre' => $row['nombre'],
                'ingreso' => $ingresoItem,
                'costo' => $costoItem,
                'ganancia' => $gananciaItem,
                'cantidad' => round($row['cantidad'], 2),
                'margen_pct' => $ingresoItem > 0 ? round(($gananciaItem / $ingresoItem) * 100, 1) : null,
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['ganancia'] <=> $a['ganancia']);
        $items = array_slice($items, 0, 6);

        $ingresos = round($ingresos, 2);
        $costo = round($costo, 2);
        $ganancia = round($ingresos - $costo, 2);

        return [
            'periodo' => $periodo,
            'desde' => $start->toDateString(),
            'hasta' => $end->toDateString(),
            'ingresos' => $ingresos,
            'costo' => $costo,
            'ganancia' => $ganancia,
            'margen_pct' => $ingresos > 0 ? round(($ganancia / $ingresos) * 100, 1) : null,
            'unidades' => round($unidades, 2),
            'servicios_sin_insumos' => count($sinInsumos),
            'items' => $items,
        ];
    }

    /**
     * Normaliza un nombre de servicio para emparejar la descripción de venta
     * con el catálogo: quita el prefijo "Grooming ·/-/:", espacios repetidos y
     * pasa a minúsculas.
     */
    private function normalizeGroomingName(string $name): string
    {
        $clean = preg_replace('/^\s*grooming\s*[·\-:]\s*/iu', '', trim($name)) ?? $name;
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

        return mb_strtolower(trim($clean));
    }

    /**
     * @return array{0: string, 1: CarbonInterface, 2: CarbonInterface}
     */
    private function resolveRentabilidadRange(string $periodo, CarbonInterface $now): array
    {
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

    /**
     * @return list<array{estado: string, count: int}>
     */
    private function felEstadoMes(CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        /** @var Collection<int, object{fel_estado: string, aggregate: int}> $grouped */
        $grouped = $this->ventasPagadasEntre($monthStart, $monthEnd)
            ->select('fel_estado as estado', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('fel_estado')
            ->orderByDesc('aggregate')
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
     * @return list<array{date: string, label: string, count: int}>
     */
    private function vacunacionesPorDia(CarbonInterface $now): array
    {
        $locale = app()->getLocale();
        $rows = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $start = $day->copy()->startOfDay();
            $end = $day->copy()->endOfDay();

            $count = VacunaAplicada::query()
                ->whereBetween('aplicada_at', [$start, $end])
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
     * @param  array<string, bool>  $capabilities
     * @return list<array{month: string, label: string, pacientes: int, propietarios: int, is_current: bool}>
     */
    private function nuevosClientesMensuales(CarbonInterface $now, array $capabilities): array
    {
        $locale = app()->getLocale();
        $rows = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $pacientes = ($capabilities['pacientes'] ?? false)
                ? Paciente::query()->whereBetween('created_at', [$start, $end])->count()
                : 0;

            $propietarios = ($capabilities['propietarios'] ?? false)
                ? Propietario::query()->whereBetween('created_at', [$start, $end])->count()
                : 0;

            $rows[] = [
                'month' => $month->format('Y-m'),
                'label' => $month->locale($locale)->isoFormat('MMM YY'),
                'pacientes' => $pacientes,
                'propietarios' => $propietarios,
                'is_current' => $i === 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{estado: string, count: int}>
     */
    private function citasAsistenciaMes(CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        /** @var Collection<int, object{estado: string, aggregate: int}> $grouped */
        $grouped = Cita::query()
            ->whereBetween('inicio_at', [$monthStart, $monthEnd])
            ->whereIn('estado', [
                Cita::ESTADO_COMPLETADA,
                Cita::ESTADO_NO_ASISTIO,
                Cita::ESTADO_CANCELADA,
            ])
            ->select('estado', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('estado')
            ->orderByDesc('aggregate')
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
