<?php

namespace App\Http\Controllers;

use App\Exports\StockInventarioImportTemplateXlsx;
use App\Exports\StockInventarioXlsxExport;
use App\Http\Requests\StockInventarioAdjustRequest;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\Sede;
use App\Services\Inventario\InventarioLoteService;
use App\Services\Inventario\StockInventarioImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function __construct(
        private readonly InventarioLoteService $lotes,
    ) {}

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

        $productoIds = $productos->getCollection()->pluck('id')->all();
        $lotesPorProducto = ProductoLote::query()
            ->where('sede_id', $sedeId)
            ->whereIn('producto_id', $productoIds)
            ->where('cantidad', '>', 0)
            ->orderByRaw('fecha_vencimiento ASC NULLS LAST')
            ->orderBy('created_at')
            ->get(['id', 'producto_id', 'numero_lote', 'fecha_vencimiento', 'cantidad'])
            ->groupBy('producto_id');

        $productos->getCollection()->transform(function (Producto $producto) use ($lotesPorProducto): Producto {
            $lotes = $lotesPorProducto->get($producto->id, collect())
                ->map(static function (ProductoLote $lote): array {
                    $numero = $lote->numero_lote;
                    if ($numero === InventarioLoteService::LOTE_SIN_ESPECIFICAR) {
                        $numero = null;
                    }

                    return [
                        'id' => $lote->id,
                        'numero_lote' => $numero,
                        'fecha_vencimiento' => $lote->fecha_vencimiento?->format('Y-m-d'),
                        'cantidad' => (string) $lote->cantidad,
                    ];
                })
                ->values()
                ->all();

            $producto->setAttribute('lotes', $lotes);

            return $producto;
        });

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

        DB::transaction(function () use ($data): void {
            $this->lotes->ajustarACantidad(
                $data['producto_id'],
                $data['sede_id'],
                (string) $data['cantidad'],
                'Ajuste de stock (panel)',
                Auth::id() !== null ? (string) Auth::id() : null,
            );
        });

        return back()->with('success', 'Stock actualizado correctamente.');
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        $filename = 'plantilla_stock_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function (): void {
            (new StockInventarioImportTemplateXlsx)->streamTo('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('stock.view'), 403);

        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $resolved = $this->resolveSedeForExport($request, $tenantId);
        abort_if($resolved === null, 422, 'No hay sedes activas para exportar stock.');

        [$sedeId, $sedeNombre, $sedeCodigo] = $resolved;

        $search = trim((string) $request->string('search', ''));
        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);
        $directionSql = $directionValid ? $direction : 'desc';

        $query = $this->stockQuery($sedeId, $search, $sortValid ? $sort : '', $directionSql);

        $filename = 'stock_'.$sedeCodigo.'_'.now()->format('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($query, $sedeNombre, $sedeCodigo): void {
            (new StockInventarioXlsxExport($sedeNombre, $sedeCodigo))->streamTo($query);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importExcel(Request $request, StockInventarioImportService $importService): JsonResponse
    {
        abort_unless($request->user()?->can('stock.adjust'), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $uploaded = $request->file('file');
        if ($uploaded === null) {
            return response()->json([
                'ok' => false,
                'error' => 'No se recibió el archivo.',
                'imported' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
            ], 422);
        }

        $extension = strtolower($uploaded->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return response()->json([
                'ok' => false,
                'error' => 'El archivo debe ser .xlsx',
                'imported' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
            ], 422);
        }

        $result = $importService->import($uploaded);
        $status = ($result['ok'] ?? false) ? 200 : 422;

        return response()->json($result, $status);
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function resolveSedeForExport(Request $request, string $tenantId): ?array
    {
        $sedesActivas = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        if ($sedesActivas->isEmpty()) {
            return null;
        }

        $sedeIds = $sedesActivas->pluck('id')->all();
        $sedeRequested = (string) $request->string('sede_id', '');
        $sede = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sedeRequested) === 1
            && in_array($sedeRequested, $sedeIds, true)
            ? $sedesActivas->firstWhere('id', $sedeRequested)
            : $sedesActivas->first();

        if ($sede === null) {
            return null;
        }

        return [(string) $sede->id, (string) $sede->nombre, (string) $sede->codigo];
    }

    /**
     * @return Builder<Producto>
     */
    private function stockQuery(string $sedeId, string $search, string $sort, string $directionSql): Builder
    {
        $query = Producto::query()
            ->with(['categoria:id,nombre'])
            ->leftJoin('existencias_sede as es', function ($join) use ($sedeId): void {
                $join->on('es.producto_id', '=', 'productos.id')
                    ->where('es.sede_id', '=', $sedeId);
            })
            ->select('productos.*')
            ->addSelect(DB::raw('COALESCE(es.cantidad, 0) as cantidad_stock'));

        if ($sort === 'cantidad_stock') {
            $query->orderByRaw('COALESCE(es.cantidad, 0) '.$directionSql);
            $query->orderBy('productos.nombre');
        } elseif ($sort !== '' && in_array($sort, self::SORTABLE_COLUMNS, true)) {
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

        return $query;
    }
}
