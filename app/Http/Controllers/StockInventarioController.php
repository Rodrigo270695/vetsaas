<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockInventarioAdjustRequest;
use App\Models\ExistenciaSede;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockInventarioController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'nombre',
        'sku',
        'unidad',
        'medicamento',
        'activo',
        'cantidad_stock',
        'created_at',
    ];

    public function index(Request $request): Response
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);
        $directionSql = $directionValid ? $direction : 'desc';

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

            return Inertia::render('inventario/stock/index', [
                'productos' => $productos,
                'filters' => [
                    'search' => $search,
                    'per_page' => $perPage,
                    'sort' => $sortValid ? $sort : null,
                    'direction' => $sortValid && $directionValid ? $direction : null,
                    'sede_id' => $sedeId,
                ],
                'stats' => [
                    'total' => Producto::count(),
                    'coincidencias' => 0,
                ],
                'sedeOptions' => $sedesActivas,
                'sinSedes' => $sedesActivas->isEmpty(),
            ]);
        }

        $query = Producto::query()
            ->with(['categoria:id,nombre,slug'])
            ->leftJoin('existencias_sede as es', function ($join) use ($sedeId): void {
                $join->on('es.producto_id', '=', 'productos.id')
                    ->where('es.sede_id', '=', $sedeId);
            })
            ->select('productos.*')
            ->addSelect('es.id as existencia_id', DB::raw('COALESCE(es.cantidad, 0) as cantidad_stock'));

        if ($sortValid && $sort === 'cantidad_stock') {
            $query->orderByRaw('COALESCE(es.cantidad, 0) '.$directionSql);
            $query->orderBy('productos.nombre');
        } elseif ($sortValid) {
            $query->orderBy('productos.'.$sort, $directionSql);
            $query->orderByDesc('productos.created_at');
        } else {
            $query->orderBy('productos.nombre');
            $query->orderByDesc('productos.created_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('productos.nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('productos.slug', 'ILIKE', "%{$search}%")
                    ->orWhere('productos.sku', 'ILIKE', "%{$search}%")
                    ->orWhere('productos.codigo_barras', 'ILIKE', "%{$search}%")
                    ->orWhere('productos.descripcion', 'ILIKE', "%{$search}%");
            });
        }

        $productos = $query->paginate($perPage)->withQueryString();

        return Inertia::render('inventario/stock/index', [
            'productos' => $productos,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'sede_id' => $sedeId,
            ],
            'stats' => [
                'total' => Producto::count(),
                'coincidencias' => $productos->total(),
            ],
            'sedeOptions' => $sedesActivas,
            'sinSedes' => false,
        ]);
    }

    public function adjust(StockInventarioAdjustRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $anterior = ExistenciaSede::query()
            ->where('producto_id', $data['producto_id'])
            ->where('sede_id', $data['sede_id'])
            ->value('cantidad');
        $anteriorF = round((float) (string) ($anterior ?? 0), 3);
        $nuevoF = round((float) (string) $data['cantidad'], 3);
        $delta = round($nuevoF - $anteriorF, 3);

        if (abs($delta) < 0.0000001) {
            ExistenciaSede::query()->updateOrCreate(
                [
                    'producto_id' => $data['producto_id'],
                    'sede_id' => $data['sede_id'],
                ],
                ['cantidad' => $nuevoF],
            );

            return back()->with('success', 'Stock actualizado correctamente.');
        }

        MovimientoInventario::aplicar(
            $data['producto_id'],
            $data['sede_id'],
            MovimientoInventario::TIPO_AJUSTE,
            (string) $delta,
            null,
            Auth::id() !== null ? (string) Auth::id() : null,
        );

        return back()->with('success', 'Stock actualizado correctamente.');
    }
}
