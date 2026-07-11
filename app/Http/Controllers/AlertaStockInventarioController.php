<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\Sede;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AlertaStockInventarioController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    /** Días hacia adelante para alertas de vencimiento próximo. */
    public const DIAS_ALERTA_VENCIMIENTO = 30;

    private const SORTABLE_COLUMNS_STOCK = [
        'nombre',
        'sku',
        'cantidad_stock',
        'stock_minimo',
        'tipo_alerta',
        'created_at',
    ];

    private const SORTABLE_COLUMNS_LOTES = [
        'nombre',
        'sku',
        'numero_lote',
        'fecha_vencimiento',
        'cantidad_lote',
        'dias_restantes',
    ];

    private const TIPO_ALERTA_STOCK = ['todos', 'agotado', 'bajo_minimo'];

    private const TIPO_ALERTA_LOTES = ['por_vencer', 'vencido'];

    private const TIPO_ALERTA_OPTIONS = ['todos', 'agotado', 'bajo_minimo', 'por_vencer', 'vencido'];

    public function alertas(Request $request): Response
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $ctx = $this->resolveListContext($request);

        $sedesActivas = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        if ($sedesActivas->isEmpty() || $ctx['sede_id'] === '') {
            return $this->renderEmpty($ctx, $sedesActivas, true);
        }

        if ($ctx['modo'] === 'lotes') {
            return $this->renderAlertasLotes($ctx, $sedesActivas);
        }

        return $this->renderAlertasStock($ctx, $sedesActivas);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function renderEmpty(array $ctx, $sedesActivas, bool $sinSedes): Response
    {
        $empty = Producto::query()->whereRaw('1 = 0')->paginate($ctx['per_page'])->withQueryString();
        $emptyLotes = ProductoLote::query()->whereRaw('1 = 0')->paginate($ctx['per_page'])->withQueryString();

        return Inertia::render('inventario/alertas/index', [
            'modo' => $ctx['modo'],
            'productos' => $empty,
            'lotes' => $emptyLotes,
            'filters' => $this->filtersPayload($ctx),
            'stats' => [
                'agotados' => 0,
                'bajo_minimo' => 0,
                'por_vencer' => 0,
                'vencidos' => 0,
                'coincidencias' => 0,
            ],
            'sedeOptions' => $sedesActivas,
            'sinSedes' => $sinSedes,
            'dias_alerta_vencimiento' => self::DIAS_ALERTA_VENCIMIENTO,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  \Illuminate\Support\Collection<int, Sede>  $sedesActivas
     */
    private function renderAlertasStock(array $ctx, $sedesActivas): Response
    {
        $sedeId = (string) $ctx['sede_id'];
        $search = (string) $ctx['search'];
        $tipoAlerta = (string) $ctx['tipo_alerta'];

        $tipoExpr = "CASE WHEN productos.stock_minimo IS NOT NULL AND productos.stock_minimo > 0 AND COALESCE(es.cantidad, 0) <= productos.stock_minimo THEN 'bajo_minimo' WHEN COALESCE(es.cantidad, 0) <= 0 THEN 'agotado' ELSE 'agotado' END";

        $base = Producto::query()
            ->with(['categoria:id,nombre,slug'])
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
            });

        if ($search !== '') {
            $base->where(function ($q) use ($search): void {
                $q->where('productos.nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('productos.slug', 'ILIKE', "%{$search}%")
                    ->orWhere('productos.sku', 'ILIKE', "%{$search}%")
                    ->orWhere('productos.codigo_barras', 'ILIKE', "%{$search}%")
                    ->orWhere('productos.descripcion', 'ILIKE', "%{$search}%");
            });
        }

        $agotados = (clone $base)->whereRaw('COALESCE(es.cantidad, 0) <= 0')
            ->where(function ($q): void {
                $q->whereNull('productos.stock_minimo')
                    ->orWhere('productos.stock_minimo', '<=', 0);
            })
            ->count();
        $bajoMinimo = (clone $base)->whereNotNull('productos.stock_minimo')
            ->where('productos.stock_minimo', '>', 0)
            ->whereRaw('COALESCE(es.cantidad, 0) <= productos.stock_minimo')
            ->count();

        $loteStats = $this->conteosAlertasLotes($sedeId);

        $query = (clone $base)
            ->select('productos.*')
            ->addSelect(
                'es.id as existencia_id',
                DB::raw('COALESCE(es.cantidad, 0) as cantidad_stock'),
                DB::raw('('.$tipoExpr.') as tipo_alerta'),
            );

        if ($tipoAlerta === 'agotado') {
            $query->whereRaw('COALESCE(es.cantidad, 0) <= 0')
                ->where(function ($q): void {
                    $q->whereNull('productos.stock_minimo')
                        ->orWhere('productos.stock_minimo', '<=', 0);
                });
        } elseif ($tipoAlerta === 'bajo_minimo') {
            $query->whereNotNull('productos.stock_minimo')
                ->where('productos.stock_minimo', '>', 0)
                ->whereRaw('COALESCE(es.cantidad, 0) <= productos.stock_minimo');
        }

        $this->applyStockSort($query, $ctx, $tipoExpr);

        $productos = $query->paginate($ctx['per_page'])->withQueryString();
        $emptyLotes = ProductoLote::query()->whereRaw('1 = 0')->paginate($ctx['per_page'])->withQueryString();

        return Inertia::render('inventario/alertas/index', [
            'modo' => 'stock',
            'productos' => $productos,
            'lotes' => $emptyLotes,
            'filters' => $this->filtersPayload($ctx),
            'stats' => [
                'agotados' => $agotados,
                'bajo_minimo' => $bajoMinimo,
                'por_vencer' => $loteStats['por_vencer'],
                'vencidos' => $loteStats['vencidos'],
                'coincidencias' => $productos->total(),
            ],
            'sedeOptions' => $sedesActivas,
            'sinSedes' => false,
            'dias_alerta_vencimiento' => self::DIAS_ALERTA_VENCIMIENTO,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  \Illuminate\Support\Collection<int, Sede>  $sedesActivas
     */
    private function renderAlertasLotes(array $ctx, $sedesActivas): Response
    {
        $sedeId = (string) $ctx['sede_id'];
        $search = (string) $ctx['search'];
        $tipoAlerta = (string) $ctx['tipo_alerta'];
        $hoy = Carbon::today();
        $limite = $hoy->copy()->addDays(self::DIAS_ALERTA_VENCIMIENTO);

        $base = ProductoLote::query()
            ->join('productos', function ($join): void {
                $join->on('productos.id', '=', 'producto_lotes.producto_id')
                    ->whereNull('productos.deleted_at')
                    ->where('productos.activo', true);
            })
            ->where('producto_lotes.sede_id', $sedeId)
            ->where('producto_lotes.cantidad', '>', 0)
            ->whereNotNull('producto_lotes.fecha_vencimiento');

        if ($search !== '') {
            $base->where(function ($q) use ($search): void {
                $q->where('productos.nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('productos.sku', 'ILIKE', "%{$search}%")
                    ->orWhere('producto_lotes.numero_lote', 'ILIKE', "%{$search}%");
            });
        }

        $loteStats = $this->conteosAlertasLotes($sedeId);

        $query = (clone $base)
            ->with(['producto.categoria:id,nombre,slug'])
            ->select('producto_lotes.*')
            ->addSelect([
                'productos.nombre as producto_nombre',
                'productos.sku as producto_sku',
                'productos.slug as producto_slug',
            ])
            ->addSelect(DB::raw('(producto_lotes.fecha_vencimiento - CURRENT_DATE) as dias_restantes'));

        if ($tipoAlerta === 'por_vencer') {
            $query->whereDate('producto_lotes.fecha_vencimiento', '>=', $hoy->toDateString())
                ->whereDate('producto_lotes.fecha_vencimiento', '<=', $limite->toDateString());
        } elseif ($tipoAlerta === 'vencido') {
            $query->whereDate('producto_lotes.fecha_vencimiento', '<', $hoy->toDateString());
        }

        $this->applyLoteSort($query, $ctx);

        $lotes = $query->paginate($ctx['per_page'])->withQueryString();

        $lotes->getCollection()->transform(function (ProductoLote $lote): ProductoLote {
            $dias = (int) ($lote->getAttribute('dias_restantes') ?? 0);
            $lote->setAttribute('tipo_alerta', $dias < 0 ? 'vencido' : 'por_vencer');
            $lote->setAttribute('producto_nombre', $lote->getAttribute('producto_nombre') ?? $lote->producto?->nombre);
            $lote->setAttribute('producto_sku', $lote->getAttribute('producto_sku') ?? $lote->producto?->sku);
            $lote->setAttribute('producto_slug', $lote->getAttribute('producto_slug') ?? $lote->producto?->slug);
            $lote->setAttribute('cantidad_lote', (string) $lote->cantidad);
            $lote->setAttribute('categoria', $lote->producto?->categoria);

            return $lote;
        });

        $emptyProductos = Producto::query()->whereRaw('1 = 0')->paginate($ctx['per_page'])->withQueryString();

        // Conteos de stock para badges aunque la vista sea por lotes
        $stockStats = $this->conteosAlertasStock($sedeId);

        return Inertia::render('inventario/alertas/index', [
            'modo' => 'lotes',
            'productos' => $emptyProductos,
            'lotes' => $lotes,
            'filters' => $this->filtersPayload($ctx),
            'stats' => [
                'agotados' => $stockStats['agotados'],
                'bajo_minimo' => $stockStats['bajo_minimo'],
                'por_vencer' => $loteStats['por_vencer'],
                'vencidos' => $loteStats['vencidos'],
                'coincidencias' => $lotes->total(),
            ],
            'sedeOptions' => $sedesActivas,
            'sinSedes' => false,
            'dias_alerta_vencimiento' => self::DIAS_ALERTA_VENCIMIENTO,
        ]);
    }

    /**
     * @return array{por_vencer: int, vencidos: int}
     */
    private function conteosAlertasLotes(string $sedeId): array
    {
        $hoy = Carbon::today()->toDateString();
        $limite = Carbon::today()->addDays(self::DIAS_ALERTA_VENCIMIENTO)->toDateString();

        $base = ProductoLote::query()
            ->join('productos', function ($join): void {
                $join->on('productos.id', '=', 'producto_lotes.producto_id')
                    ->whereNull('productos.deleted_at')
                    ->where('productos.activo', true);
            })
            ->where('producto_lotes.sede_id', $sedeId)
            ->where('producto_lotes.cantidad', '>', 0)
            ->whereNotNull('producto_lotes.fecha_vencimiento');

        $porVencer = (clone $base)
            ->whereDate('producto_lotes.fecha_vencimiento', '>=', $hoy)
            ->whereDate('producto_lotes.fecha_vencimiento', '<=', $limite)
            ->count();

        $vencidos = (clone $base)
            ->whereDate('producto_lotes.fecha_vencimiento', '<', $hoy)
            ->count();

        return [
            'por_vencer' => $porVencer,
            'vencidos' => $vencidos,
        ];
    }

    /**
     * @return array{agotados: int, bajo_minimo: int}
     */
    private function conteosAlertasStock(string $sedeId): array
    {
        $base = Producto::query()
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
            });

        $agotados = (clone $base)->whereRaw('COALESCE(es.cantidad, 0) <= 0')
            ->where(function ($q): void {
                $q->whereNull('productos.stock_minimo')
                    ->orWhere('productos.stock_minimo', '<=', 0);
            })
            ->count();

        $bajoMinimo = (clone $base)->whereNotNull('productos.stock_minimo')
            ->where('productos.stock_minimo', '>', 0)
            ->whereRaw('COALESCE(es.cantidad, 0) <= productos.stock_minimo')
            ->count();

        return [
            'agotados' => $agotados,
            'bajo_minimo' => $bajoMinimo,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Producto>  $query
     * @param  array<string, mixed>  $ctx
     */
    private function applyStockSort($query, array $ctx, string $tipoExpr): void
    {
        $sort = (string) $ctx['sort'];
        $sortValid = (bool) $ctx['sort_valid'];
        $directionSql = (string) $ctx['direction_sql'];

        if ($sortValid && $sort === 'cantidad_stock') {
            $query->orderByRaw('COALESCE(es.cantidad, 0) '.$directionSql);
            $query->orderBy('productos.nombre');

            return;
        }

        if ($sortValid && $sort === 'tipo_alerta') {
            $query->orderByRaw('('.$tipoExpr.') '.$directionSql);
            $query->orderByRaw('COALESCE(es.cantidad, 0) asc');
            $query->orderBy('productos.nombre');

            return;
        }

        if ($sortValid && $sort === 'stock_minimo') {
            $query->orderByRaw('productos.stock_minimo IS NULL, productos.stock_minimo '.$directionSql);
            $query->orderBy('productos.nombre');

            return;
        }

        if ($sortValid && in_array($sort, self::SORTABLE_COLUMNS_STOCK, true)) {
            $query->orderBy('productos.'.$sort, $directionSql);
            $query->orderByDesc('productos.created_at');

            return;
        }

        $query->orderByRaw('CASE WHEN COALESCE(es.cantidad, 0) <= 0 THEN 0 ELSE 1 END');
        $query->orderByRaw('COALESCE(es.cantidad, 0) asc');
        $query->orderBy('productos.nombre');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ProductoLote>  $query
     * @param  array<string, mixed>  $ctx
     */
    private function applyLoteSort($query, array $ctx): void
    {
        $sort = (string) $ctx['sort'];
        $sortValid = (bool) $ctx['sort_valid'];
        $directionSql = (string) $ctx['direction_sql'];

        if ($sortValid && $sort === 'nombre') {
            $query->orderBy('productos.nombre', $directionSql);
            $query->orderBy('producto_lotes.fecha_vencimiento');

            return;
        }

        if ($sortValid && $sort === 'sku') {
            $query->orderBy('productos.sku', $directionSql);
            $query->orderBy('producto_lotes.fecha_vencimiento');

            return;
        }

        if ($sortValid && $sort === 'numero_lote') {
            $query->orderBy('producto_lotes.numero_lote', $directionSql);

            return;
        }

        if ($sortValid && $sort === 'fecha_vencimiento') {
            $query->orderBy('producto_lotes.fecha_vencimiento', $directionSql);
            $query->orderBy('productos.nombre');

            return;
        }

        if ($sortValid && $sort === 'cantidad_lote') {
            $query->orderBy('producto_lotes.cantidad', $directionSql);

            return;
        }

        if ($sortValid && $sort === 'dias_restantes') {
            $query->orderByRaw('(producto_lotes.fecha_vencimiento - CURRENT_DATE) '.$directionSql);
            $query->orderBy('productos.nombre');

            return;
        }

        $query->orderBy('producto_lotes.fecha_vencimiento');
        $query->orderBy('productos.nombre');
    }

    /**
     * @return array{
     *     search: string,
     *     per_page: int,
     *     sort: string,
     *     direction: string,
     *     sort_valid: bool,
     *     direction_valid: bool,
     *     direction_sql: string,
     *     sede_id: string,
     *     tipo_alerta: string,
     *     modo: 'stock'|'lotes',
     * }
     */
    private function resolveListContext(Request $request): array
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'asc'));
        $directionValid = in_array($direction, ['asc', 'desc'], true);
        $directionSql = $directionValid ? $direction : 'asc';

        $tipoAlerta = (string) $request->string('tipo_alerta', 'todos');
        if (! in_array($tipoAlerta, self::TIPO_ALERTA_OPTIONS, true)) {
            $tipoAlerta = 'todos';
        }

        $modo = in_array($tipoAlerta, self::TIPO_ALERTA_LOTES, true) ? 'lotes' : 'stock';
        $sortable = $modo === 'lotes' ? self::SORTABLE_COLUMNS_LOTES : self::SORTABLE_COLUMNS_STOCK;
        $sortValid = in_array($sort, $sortable, true);

        if ($modo === 'lotes' && ! $sortValid && $sort === '') {
            $sort = 'fecha_vencimiento';
            $sortValid = true;
            $direction = 'asc';
            $directionValid = true;
            $directionSql = 'asc';
        }

        $tenantId = $request->user()?->tenant_id;
        $sedesActivas = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('id')
            ->all();

        $sedeRequested = (string) $request->string('sede_id', '');
        $sedeId = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sedeRequested) === 1
            && in_array($sedeRequested, $sedesActivas, true)
            ? $sedeRequested
            : (string) ($sedesActivas[0] ?? '');

        return [
            'search' => $search,
            'per_page' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'sort_valid' => $sortValid,
            'direction_valid' => $directionValid,
            'direction_sql' => $directionSql,
            'sede_id' => $sedeId,
            'tipo_alerta' => $tipoAlerta,
            'modo' => $modo,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function filtersPayload(array $ctx): array
    {
        return [
            'search' => $ctx['search'],
            'per_page' => $ctx['per_page'],
            'sort' => $ctx['sort_valid'] ? $ctx['sort'] : null,
            'direction' => $ctx['sort_valid'] && $ctx['direction_valid'] ? $ctx['direction'] : null,
            'sede_id' => $ctx['sede_id'],
            'tipo_alerta' => $ctx['tipo_alerta'],
        ];
    }
}
