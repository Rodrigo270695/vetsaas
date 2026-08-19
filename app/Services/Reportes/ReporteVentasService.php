<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\ClinicSetting;
use App\Models\FelSerie;
use App\Models\Venta;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reportes de ventas por producto y por servicio (tratamientos, vacunas, grooming).
 *
 * Usa ventas pagadas con el mismo filtro FEL/comprobante que el análisis financiero.
 * Costos y precios salen del catálogo vigente (no snapshot histórico).
 */
final class ReporteVentasService
{
    /**
     * @return array{
     *     moneda: string,
     *     filtros: array{fecha_desde: string, fecha_hasta: string, periodo: string},
     *     totales: array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items_sin_costo: int},
     *     items: list<array<string, mixed>>
     * }
     */
    public function ventasPorProducto(?string $fechaDesde, ?string $fechaHasta, ?string $periodo = null): array
    {
        [$periodoKey, $start, $end] = $this->resolveRange($fechaDesde, $fechaHasta, $periodo);

        $empty = $this->emptyPayload($periodoKey, $start, $end);

        if (! Schema::hasTable('venta_lineas') || ! Schema::hasTable('productos')) {
            return $empty;
        }

        $q = DB::table('venta_lineas as vl')
            ->join('ventas as v', 'v.id', '=', 'vl.venta_id')
            ->join('productos as p', 'p.id', '=', 'vl.producto_id')
            ->whereNotNull('vl.producto_id');

        $this->applyVentasFilter($q, $start, $end);

        if (Schema::hasTable('categorias_productos')) {
            $q->leftJoin('categorias_productos as cp', 'cp.id', '=', 'p.categoria_id');
        }

        $select = [
            'p.id as id',
            'p.nombre as nombre',
            'p.precio_venta as precio_unit',
            'p.precio_compra as costo_unit',
            DB::raw('COALESCE(SUM(vl.cantidad), 0) as cantidad'),
            DB::raw('COUNT(DISTINCT vl.venta_id) as ventas'),
            DB::raw('COALESCE(SUM(vl.cantidad * p.precio_venta), 0) as ingreso'),
            DB::raw('COALESCE(SUM(vl.cantidad * p.precio_compra), 0) as costo'),
            DB::raw('MIN(COALESCE(v.fecha_pago, v.created_at)) as fecha_primera'),
            DB::raw('MAX(COALESCE(v.fecha_pago, v.created_at)) as fecha_ultima'),
        ];

        if (Schema::hasTable('categorias_productos')) {
            $select[] = 'cp.nombre as categoria';
        } else {
            $select[] = DB::raw('NULL as categoria');
        }

        $groupBy = ['p.id', 'p.nombre', 'p.precio_venta', 'p.precio_compra'];
        if (Schema::hasTable('categorias_productos')) {
            $groupBy[] = 'cp.nombre';
        }

        /** @var Collection<int, object> $rows */
        $rows = $q
            ->select($select)
            ->groupBy($groupBy)
            ->orderByDesc(DB::raw('COALESCE(SUM(vl.cantidad), 0)'))
            ->get();

        $items = [];
        $unidades = 0.0;
        $ingresos = 0.0;
        $costo = 0.0;
        $utilidadAcum = 0.0;
        $conCosto = 0;
        $sinCosto = 0;

        foreach ($rows as $row) {
            $item = $this->mapItemRow($row, 'producto');
            $items[] = $item;

            $unidades += $item['cantidad'];
            $ingresos += $item['ingreso'];

            if ($item['tiene_costo']) {
                $costo += (float) $item['costo'];
                $utilidadAcum += (float) $item['utilidad'];
                $conCosto++;
            } else {
                $sinCosto++;
            }
        }

        $ventasTotales = $this->countDistinctVentasProductos($start, $end);

        return [
            'moneda' => $this->resolveMoneda(),
            'filtros' => [
                'fecha_desde' => $start->toDateString(),
                'fecha_hasta' => $end->toDateString(),
                'periodo' => $periodoKey,
            ],
            'totales' => $this->buildTotales($unidades, $ventasTotales, $ingresos, $costo, $utilidadAcum, $conCosto, $sinCosto),
            'items' => $items,
        ];
    }

    /**
     * @param  'todos'|'tratamiento'|'vacuna'|'grooming'  $tipo
     * @return array{
     *     moneda: string,
     *     filtros: array{fecha_desde: string, fecha_hasta: string, periodo: string, tipo: string},
     *     totales: array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items_sin_costo: int},
     *     resumen: array{tratamiento: array, vacuna: array, grooming: array},
     *     items: list<array<string, mixed>>
     * }
     */
    public function ventasPorServicio(
        string $tipo,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?string $periodo = null,
        bool $includeGrooming = true,
    ): array {
        $tipo = in_array($tipo, ['todos', 'tratamiento', 'vacuna', 'grooming'], true) ? $tipo : 'todos';
        if (! $includeGrooming && $tipo === 'grooming') {
            $tipo = 'todos';
        }

        [$periodoKey, $start, $end] = $this->resolveRange($fechaDesde, $fechaHasta, $periodo);

        $allItems = $this->collectServicioItems($start, $end, $includeGrooming);
        $resumen = $this->buildResumenFromItems($allItems);

        $items = match ($tipo) {
            'tratamiento', 'vacuna', 'grooming' => array_values(array_filter(
                $allItems,
                static fn (array $item): bool => $item['tipo'] === $tipo,
            )),
            default => $allItems,
        };

        usort($items, static fn (array $a, array $b): int => $b['cantidad'] <=> $a['cantidad']);

        $unidades = 0.0;
        $ingresos = 0.0;
        $costo = 0.0;
        $utilidadAcum = 0.0;
        $conCosto = 0;
        $sinCosto = 0;
        $ventaIds = [];

        foreach ($items as $item) {
            $unidades += $item['cantidad'];
            $ingresos += $item['ingreso'];

            if ($item['tiene_costo']) {
                $costo += (float) $item['costo'];
                $utilidadAcum += (float) $item['utilidad'];
                $conCosto++;
            } else {
                $sinCosto++;
            }

            foreach ($item['_venta_ids'] ?? [] as $ventaId => $_) {
                $ventaIds[(string) $ventaId] = true;
            }
        }

        // Limpiar campo interno antes de enviar a Inertia.
        $itemsPublicos = array_map(static function (array $item): array {
            unset($item['_venta_ids']);

            return $item;
        }, $items);

        return [
            'moneda' => $this->resolveMoneda(),
            'filtros' => [
                'fecha_desde' => $start->toDateString(),
                'fecha_hasta' => $end->toDateString(),
                'periodo' => $periodoKey,
                'tipo' => $tipo,
            ],
            'totales' => $this->buildTotales($unidades, count($ventaIds), $ingresos, $costo, $utilidadAcum, $conCosto, $sinCosto),
            'resumen' => $resumen,
            'items' => $itemsPublicos,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectServicioItems(CarbonInterface $start, CarbonInterface $end, bool $includeGrooming = true): array
    {
        if (! Schema::hasTable('venta_lineas')) {
            return [];
        }

        $clinicos = $this->catalogoClinico();
        $grooming = $includeGrooming ? $this->catalogoGrooming() : [];

        $lineas = DB::table('venta_lineas as vl')
            ->join('ventas as v', 'v.id', '=', 'vl.venta_id')
            ->whereNull('vl.producto_id');

        $this->applyVentasFilter($lineas, $start, $end);

        /** @var Collection<int, object{descripcion: string, cantidad: string, venta_id: string, fecha: ?string}> $lineas */
        $lineas = $lineas
            ->select(
                'vl.descripcion_snapshot as descripcion',
                'vl.cantidad',
                'vl.venta_id',
                DB::raw('COALESCE(v.fecha_pago, v.created_at) as fecha'),
            )
            ->get();

        /** @var array<string, array<string, mixed>> $acumulado */
        $acumulado = [];

        foreach ($lineas as $linea) {
            $descripcion = (string) $linea->descripcion;
            $cantidad = (float) $linea->cantidad;
            $ventaId = (string) $linea->venta_id;

            $matched = null;
            $tipo = null;

            $groomKey = $this->normalizeGroomingName($descripcion);
            if (isset($grooming[$groomKey])) {
                $matched = $grooming[$groomKey];
                $tipo = 'grooming';
            } else {
                if (preg_match('/^\s*grooming\s*[·\-:]/iu', $descripcion) === 1) {
                    continue;
                }

                $clinKey = $this->normalizeCatalogName($descripcion);
                if (isset($clinicos[$clinKey])) {
                    $matched = $clinicos[$clinKey];
                    $tipo = $this->isVacuna($matched['nombre'], $matched['categoria'] ?? null)
                        ? 'vacuna'
                        : 'tratamiento';
                }
            }

            if ($matched === null || $tipo === null) {
                continue;
            }

            $id = (string) $matched['id'];
            $bucketKey = $tipo.':'.$id;

            if (! isset($acumulado[$bucketKey])) {
                $acumulado[$bucketKey] = [
                    'id' => $id,
                    'nombre' => $matched['nombre'],
                    'categoria' => $matched['categoria'] ?? null,
                    'tipo' => $tipo,
                    'precio_unit' => $matched['precio'],
                    'costo_unit' => $matched['costo_unit'],
                    'cantidad' => 0.0,
                    'venta_ids' => [],
                    'ingreso' => 0.0,
                    'costo' => 0.0,
                    'fecha_primera' => null,
                    'fecha_ultima' => null,
                ];
            }

            $acumulado[$bucketKey]['cantidad'] += $cantidad;
            $acumulado[$bucketKey]['ingreso'] += $cantidad * $matched['precio'];
            if ($matched['costo_unit'] !== null) {
                $acumulado[$bucketKey]['costo'] += $cantidad * $matched['costo_unit'];
            }
            $acumulado[$bucketKey]['venta_ids'][$ventaId] = true;

            $fechaRaw = isset($linea->fecha) ? (string) $linea->fecha : '';
            if ($fechaRaw !== '') {
                try {
                    $fechaCarbon = \Carbon\Carbon::parse($fechaRaw);
                    $fechaIso = $fechaCarbon->toIso8601String();
                    $prevPrimera = $acumulado[$bucketKey]['fecha_primera'];
                    $prevUltima = $acumulado[$bucketKey]['fecha_ultima'];
                    if ($prevPrimera === null || $fechaIso < (string) $prevPrimera) {
                        $acumulado[$bucketKey]['fecha_primera'] = $fechaIso;
                    }
                    if ($prevUltima === null || $fechaIso > (string) $prevUltima) {
                        $acumulado[$bucketKey]['fecha_ultima'] = $fechaIso;
                    }
                } catch (\Throwable) {
                    // Ignorar fechas inválidas en líneas legacy.
                }
            }
        }

        $items = [];
        foreach ($acumulado as $row) {
            $items[] = $this->mapAccumulatedItem($row);
        }

        return $items;
    }

    /**
     * @return array<string, array{id: string, nombre: string, categoria: ?string, precio: float, costo_unit: ?float}>
     */
    private function catalogoClinico(): array
    {
        if (! Schema::hasTable('servicios_clinicos')) {
            return [];
        }

        $hasCosto = Schema::hasColumn('servicios_clinicos', 'precio_costo');
        $hasCategorias = Schema::hasTable('categorias_servicio_clinico');

        $q = DB::table('servicios_clinicos as sc');
        if ($hasCategorias) {
            $q->leftJoin('categorias_servicio_clinico as csc', 'csc.id', '=', 'sc.categoria_id');
        }

        $cols = ['sc.id', 'sc.nombre', 'sc.precio_lista'];
        if ($hasCosto) {
            $cols[] = 'sc.precio_costo';
        }
        if ($hasCategorias) {
            $cols[] = 'csc.nombre as categoria';
        }

        $porNombre = [];
        foreach ($q->get($cols) as $servicio) {
            $key = $this->normalizeCatalogName((string) $servicio->nombre);
            $porNombre[$key] = [
                'id' => (string) $servicio->id,
                'nombre' => (string) $servicio->nombre,
                'categoria' => isset($servicio->categoria) && is_string($servicio->categoria)
                    ? $servicio->categoria
                    : null,
                'precio' => (float) $servicio->precio_lista,
                'costo_unit' => ($hasCosto && $servicio->precio_costo !== null && $servicio->precio_costo !== '')
                    ? (float) $servicio->precio_costo
                    : null,
            ];
        }

        return $porNombre;
    }

    /**
     * @return array<string, array{id: string, nombre: string, categoria: ?string, precio: float, costo_unit: ?float}>
     */
    private function catalogoGrooming(): array
    {
        if (! Schema::hasTable('grooming_servicios')) {
            return [];
        }

        $servicios = DB::table('grooming_servicios')->get(['id', 'nombre', 'precio_lista', 'categoria_id']);

        $costos = collect();
        if (Schema::hasTable('grooming_servicio_insumo')) {
            $costos = DB::table('grooming_servicio_insumo')
                ->groupBy('grooming_servicio_id')
                ->select('grooming_servicio_id', DB::raw('SUM(precio) as costo'))
                ->pluck('costo', 'grooming_servicio_id');
        }

        $categorias = collect();
        if (Schema::hasTable('categorias_grooming')) {
            $categorias = DB::table('categorias_grooming')->pluck('nombre', 'id');
        }

        $porNombre = [];
        foreach ($servicios as $servicio) {
            $key = $this->normalizeGroomingName((string) $servicio->nombre);
            $categoriaId = $servicio->categoria_id ?? null;
            $porNombre[$key] = [
                'id' => (string) $servicio->id,
                'nombre' => (string) $servicio->nombre,
                'categoria' => $categoriaId !== null && isset($categorias[$categoriaId])
                    ? (string) $categorias[$categoriaId]
                    : null,
                'precio' => (float) $servicio->precio_lista,
                'costo_unit' => isset($costos[$servicio->id]) ? (float) $costos[$servicio->id] : null,
            ];
        }

        return $porNombre;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *     tratamiento: array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items: int, items_sin_costo: int},
     *     vacuna: array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items: int, items_sin_costo: int},
     *     grooming: array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items: int, items_sin_costo: int}
     * }
     */
    private function buildResumenFromItems(array $items): array
    {
        $buckets = [
            'tratamiento' => $this->emptyResumenSlice(),
            'vacuna' => $this->emptyResumenSlice(),
            'grooming' => $this->emptyResumenSlice(),
        ];

        foreach ($items as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            if (! isset($buckets[$tipo])) {
                continue;
            }

            $buckets[$tipo]['unidades'] += (float) $item['cantidad'];
            $buckets[$tipo]['ingresos'] += (float) $item['ingreso'];
            $buckets[$tipo]['items']++;

            foreach ($item['_venta_ids'] ?? [] as $ventaId => $_) {
                $buckets[$tipo]['venta_ids'][(string) $ventaId] = true;
            }

            if ($item['tiene_costo']) {
                $buckets[$tipo]['costo'] += (float) $item['costo'];
                $buckets[$tipo]['utilidad_acum'] += (float) $item['utilidad'];
                $buckets[$tipo]['con_costo']++;
            } else {
                $buckets[$tipo]['items_sin_costo']++;
            }
        }

        foreach ($buckets as $key => $slice) {
            $ingresos = round($slice['ingresos'], 2);
            $costo = round($slice['costo'], 2);
            $utilidad = $slice['con_costo'] > 0 ? round($slice['utilidad_acum'], 2) : null;

            $buckets[$key] = [
                'unidades' => round($slice['unidades'], 2),
                'ventas' => count($slice['venta_ids']),
                'ingresos' => $ingresos,
                'costo' => $costo,
                'utilidad' => $utilidad,
                'margen_pct' => $utilidad !== null ? $this->calcMargenPct($ingresos, $utilidad) : null,
                'items' => (int) $slice['items'],
                'items_sin_costo' => (int) $slice['items_sin_costo'],
            ];
        }

        return $buckets;
    }

    /**
     * @return array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad_acum: float, con_costo: int, items: int, items_sin_costo: int, venta_ids: array<string, true>}
     */
    private function emptyResumenSlice(): array
    {
        return [
            'unidades' => 0.0,
            'ventas' => 0,
            'ingresos' => 0.0,
            'costo' => 0.0,
            'utilidad_acum' => 0.0,
            'con_costo' => 0,
            'items' => 0,
            'items_sin_costo' => 0,
            'venta_ids' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapAccumulatedItem(array $row): array
    {
        $cantidad = round((float) $row['cantidad'], 2);
        $precioUnit = round((float) $row['precio_unit'], 2);
        $costoUnit = $row['costo_unit'] !== null ? round((float) $row['costo_unit'], 2) : null;
        $ingreso = round((float) $row['ingreso'], 2);
        $tieneCosto = $costoUnit !== null;
        $costo = $tieneCosto ? round((float) $row['costo'], 2) : null;
        $utilidad = $tieneCosto ? round($ingreso - (float) $costo, 2) : null;

        return [
            'id' => (string) $row['id'],
            'nombre' => (string) $row['nombre'],
            'categoria' => $row['categoria'] ?? null,
            'tipo' => (string) $row['tipo'],
            'cantidad' => $cantidad,
            'ventas' => count($row['venta_ids'] ?? []),
            'precio_unit' => $precioUnit,
            'costo_unit' => $costoUnit,
            'ingreso' => $ingreso,
            'costo' => $costo,
            'utilidad' => $utilidad,
            'margen_pct' => $utilidad !== null ? $this->calcMargenPct($ingreso, $utilidad) : null,
            'tiene_costo' => $tieneCosto,
            'fecha_primera' => $this->normalizeFechaReporte($row['fecha_primera'] ?? null),
            'fecha_ultima' => $this->normalizeFechaReporte($row['fecha_ultima'] ?? null),
            '_venta_ids' => $row['venta_ids'] ?? [],
        ];
    }

    private function countDistinctVentasProductos(CarbonInterface $start, CarbonInterface $end): int
    {
        $q = DB::table('venta_lineas as vl')
            ->join('ventas as v', 'v.id', '=', 'vl.venta_id')
            ->whereNotNull('vl.producto_id');

        $this->applyVentasFilter($q, $start, $end);

        return (int) $q->distinct()->count('vl.venta_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapItemRow(object $row, string $tipo): array
    {
        $cantidad = round((float) ($row->cantidad ?? 0), 2);
        $ventas = (int) ($row->ventas ?? 0);
        $precioUnit = $row->precio_unit !== null && $row->precio_unit !== ''
            ? round((float) $row->precio_unit, 2)
            : null;
        $costoUnit = $row->costo_unit !== null && $row->costo_unit !== ''
            ? round((float) $row->costo_unit, 2)
            : null;
        $tieneCosto = $precioUnit !== null && $costoUnit !== null;
        $ingreso = $precioUnit !== null
            ? round((float) ($row->ingreso ?? ($cantidad * $precioUnit)), 2)
            : round((float) ($row->ingreso ?? 0), 2);
        $costo = $tieneCosto ? round((float) ($row->costo ?? ($cantidad * $costoUnit)), 2) : null;
        $utilidad = $tieneCosto ? round($ingreso - (float) $costo, 2) : null;

        return [
            'id' => (string) ($row->id ?? ''),
            'nombre' => (string) ($row->nombre ?? ''),
            'categoria' => isset($row->categoria) && is_string($row->categoria) ? $row->categoria : null,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'ventas' => $ventas,
            'precio_unit' => $precioUnit,
            'costo_unit' => $costoUnit,
            'ingreso' => $ingreso,
            'costo' => $costo,
            'utilidad' => $utilidad,
            'margen_pct' => $utilidad !== null ? $this->calcMargenPct($ingreso, $utilidad) : null,
            'tiene_costo' => $tieneCosto,
            'fecha_primera' => $this->normalizeFechaReporte($row->fecha_primera ?? null),
            'fecha_ultima' => $this->normalizeFechaReporte($row->fecha_ultima ?? null),
        ];
    }

    private function normalizeFechaReporte(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items_sin_costo: int}
     */
    private function buildTotales(
        float $unidades,
        int $ventas,
        float $ingresos,
        float $costo,
        float $utilidadAcum,
        int $conCosto,
        int $sinCosto,
    ): array {
        $ingresos = round($ingresos, 2);
        $costo = round($costo, 2);
        $utilidad = $conCosto > 0 ? round($utilidadAcum, 2) : null;

        return [
            'unidades' => round($unidades, 2),
            'ventas' => $ventas,
            'ingresos' => $ingresos,
            'costo' => $costo,
            'utilidad' => $utilidad,
            'margen_pct' => $utilidad !== null ? $this->calcMargenPct($ingresos, $utilidad) : null,
            'items_sin_costo' => $sinCosto,
        ];
    }

    /**
     * @return array{
     *     moneda: string,
     *     filtros: array{fecha_desde: string, fecha_hasta: string, periodo: string},
     *     totales: array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items_sin_costo: int},
     *     items: list<array<string, mixed>>
     * }
     */
    private function emptyPayload(string $periodo, CarbonInterface $start, CarbonInterface $end): array
    {
        return [
            'moneda' => $this->resolveMoneda(),
            'filtros' => [
                'fecha_desde' => $start->toDateString(),
                'fecha_hasta' => $end->toDateString(),
                'periodo' => $periodo,
            ],
            'totales' => [
                'unidades' => 0.0,
                'ventas' => 0,
                'ingresos' => 0.0,
                'costo' => 0.0,
                'utilidad' => null,
                'margen_pct' => null,
                'items_sin_costo' => 0,
            ],
            'items' => [],
        ];
    }

    private function isVacuna(string $nombre, ?string $categoria): bool
    {
        $haystack = mb_strtolower(trim(($categoria ?? '').' '.$nombre));

        return str_contains($haystack, 'vacuna')
            || str_contains($haystack, 'vacunación')
            || str_contains($haystack, 'vacunacion');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $q
     */
    private function applyVentasFilter($q, CarbonInterface $start, CarbonInterface $end): void
    {
        $q->where('v.estado', Venta::ESTADO_PAGADO)
            ->whereNull('v.deleted_at')
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('v.fecha_pago', [$start, $end])
                    ->orWhere(function ($inner) use ($start, $end): void {
                        $inner->whereNull('v.fecha_pago')
                            ->whereBetween('v.created_at', [$start, $end]);
                    });
            })
            ->where(function ($query): void {
                $query->where(function ($ticket): void {
                    $ticket->where(function ($tipo): void {
                        $tipo->whereNull('v.tipo_comprobante_sunat')
                            ->orWhere('v.tipo_comprobante_sunat', FelSerie::TIPO_TICKET);
                    })->where('v.fel_estado', Venta::FEL_SIN_CPE);
                })->orWhere(function ($sunat): void {
                    $sunat->whereIn('v.tipo_comprobante_sunat', [
                        FelSerie::TIPO_FACTURA,
                        FelSerie::TIPO_BOLETA,
                    ])->where('v.fel_estado', Venta::FEL_EMITIDO);
                });
            });
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

    private function calcMargenPct(float $ingreso, float $ganancia): ?float
    {
        if ($ingreso <= 0) {
            return null;
        }

        $pct = ($ganancia / $ingreso) * 100;

        return round(max(-999.9, min(999.9, $pct)), 1);
    }

    private function normalizeGroomingName(string $name): string
    {
        $clean = preg_replace('/^\s*grooming\s*[·\-:]\s*/iu', '', trim($name)) ?? $name;

        return $this->normalizeCatalogName($clean);
    }

    private function normalizeCatalogName(string $name): string
    {
        $clean = preg_replace('/\s+/u', ' ', trim($name)) ?? $name;

        return mb_strtolower(trim($clean));
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
