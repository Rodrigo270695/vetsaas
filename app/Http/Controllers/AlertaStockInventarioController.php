<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AlertaStockInventarioController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'nombre',
        'sku',
        'cantidad_stock',
        'stock_minimo',
        'tipo_alerta',
        'created_at',
    ];

    private const TIPO_ALERTA_OPTIONS = ['todos', 'agotado', 'bajo_minimo'];

    public function alertas(Request $request): Response
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'asc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);
        $directionSql = $directionValid ? $direction : 'asc';

        $tipoAlerta = (string) $request->string('tipo_alerta', 'todos');
        if (! in_array($tipoAlerta, self::TIPO_ALERTA_OPTIONS, true)) {
            $tipoAlerta = 'todos';
        }

        $sedesActivas = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        $sedeIds = $sedesActivas->pluck('id')->all();

        $sedeRequested = (string) $request->string('sede_id', '');
        $sedeId = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sedeRequested) === 1
            && in_array($sedeRequested, $sedeIds, true)
            ? $sedeRequested
            : (string) ($sedesActivas->first()?->id ?? '');

        if ($sedesActivas->isEmpty() || $sedeId === '') {
            $productos = Producto::query()->whereRaw('1 = 0')->paginate($perPage)->withQueryString();

            return Inertia::render('inventario/alertas/index', [
                'productos' => $productos,
                'filters' => [
                    'search' => $search,
                    'per_page' => $perPage,
                    'sort' => $sortValid ? $sort : null,
                    'direction' => $sortValid && $directionValid ? $direction : null,
                    'sede_id' => $sedeId,
                    'tipo_alerta' => $tipoAlerta,
                ],
                'stats' => [
                    'agotados' => 0,
                    'bajo_minimo' => 0,
                    'coincidencias' => 0,
                ],
                'sedeOptions' => $sedesActivas,
                'sinSedes' => $sedesActivas->isEmpty(),
            ]);
        }

        // Prioridad: con umbral de reposición, "bajo mínimo" incluye stock 0 (reposición planificada).
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

        if ($sortValid && $sort === 'cantidad_stock') {
            $query->orderByRaw('COALESCE(es.cantidad, 0) '.$directionSql);
            $query->orderBy('productos.nombre');
        } elseif ($sortValid && $sort === 'tipo_alerta') {
            $query->orderByRaw('('.$tipoExpr.') '.$directionSql);
            $query->orderByRaw('COALESCE(es.cantidad, 0) asc');
            $query->orderBy('productos.nombre');
        } elseif ($sortValid && $sort === 'stock_minimo') {
            $query->orderByRaw('productos.stock_minimo IS NULL, productos.stock_minimo '.$directionSql);
            $query->orderBy('productos.nombre');
        } elseif ($sortValid) {
            $query->orderBy('productos.'.$sort, $directionSql);
            $query->orderByDesc('productos.created_at');
        } else {
            $query->orderByRaw('CASE WHEN COALESCE(es.cantidad, 0) <= 0 THEN 0 ELSE 1 END');
            $query->orderByRaw('COALESCE(es.cantidad, 0) asc');
            $query->orderBy('productos.nombre');
        }

        $productos = $query->paginate($perPage)->withQueryString();

        return Inertia::render('inventario/alertas/index', [
            'productos' => $productos,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'sede_id' => $sedeId,
                'tipo_alerta' => $tipoAlerta,
            ],
            'stats' => [
                'agotados' => $agotados,
                'bajo_minimo' => $bajoMinimo,
                'coincidencias' => $productos->total(),
            ],
            'sedeOptions' => $sedesActivas,
            'sinSedes' => false,
        ]);
    }
}
