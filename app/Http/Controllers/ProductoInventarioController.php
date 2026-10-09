<?php

namespace App\Http\Controllers;

use App\Exports\ProductosInventarioImportTemplateXlsx;
use App\Exports\ProductosInventarioXlsxExport;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Http\Requests\ProductoInventarioQuickStoreRequest;
use App\Http\Requests\ProductoInventarioRequest;
use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\Sede;
use App\Services\Inventario\InventarioLoteService;
use App\Services\Inventario\ProductoInventarioImportService;
use App\Support\Inventario\UnidadMedidaOpciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductoInventarioController extends Controller
{
    use LogsAuditExports;
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'nombre',
        'sku',
        'unidad',
        'precio_venta',
        'activo',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todas', 'activa', 'inactiva'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todas';
        }

        $categoriaFiltro = (string) $request->string('categoria_id', '');
        $categoriaFiltroUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $categoriaFiltro) === 1
            ? $categoriaFiltro
            : null;

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Producto::query()->with(['categoria:id,nombre,slug']);

        if ($canAudit) {
            $query->with(['creadoPor:id,name,email', 'actualizadoPor:id,name,email']);
        }

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('nombre');
            $query->orderByDesc('created_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('slug', 'ILIKE', "%{$search}%")
                    ->orWhere('sku', 'ILIKE', "%{$search}%")
                    ->orWhere('codigo_barras', 'ILIKE', "%{$search}%")
                    ->orWhere('descripcion', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activa') {
            $query->where('activo', true);
        } elseif ($estado === 'inactiva') {
            $query->where('activo', false);
        }

        if ($categoriaFiltroUuid !== null) {
            $query->where('categoria_id', $categoriaFiltroUuid);
        }

        $productos = $query->paginate($perPage)->withQueryString();
        $this->appendLoteProximo($productos->getCollection());

        $categoriaQuery = CategoriaProducto::query()->where('activo', true);
        if (Schema::hasColumn('categorias_productos', 'orden')) {
            $categoriaQuery->orderBy('orden');
        }
        $categoriaOptions = $categoriaQuery
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $unidadOptions = UnidadMedidaOpciones::forProductoForm();

        $sedesActivas = Sede::query()
            ->where('tenant_id', tenant_id())
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        return Inertia::render('inventario/productos/index', [
            'productos' => $productos,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
                'categoria_id' => $categoriaFiltroUuid ?? '',
            ],
            'stats' => [
                'total' => Producto::count(),
                'activos' => Producto::where('activo', true)->count(),
                'inactivos' => Producto::where('activo', false)->count(),
                'coincidencias' => $productos->total(),
            ],
            'categoriaOptions' => $categoriaOptions,
            'unidadOptions' => $unidadOptions,
            'sedeOptions' => $sedesActivas,
        ]);
    }

    public function store(ProductoInventarioRequest $request, InventarioLoteService $inventarioLoteService): RedirectResponse
    {
        $userId = Auth::id();
        $validated = $request->validated();
        $productoData = Arr::except($validated, [
            'stock_inicial_sede_id',
            'stock_inicial_cantidad',
            'numero_lote',
            'fecha_vencimiento',
        ]);
        $stockSedeId = $validated['stock_inicial_sede_id'] ?? null;
        $stockCantidad = $validated['stock_inicial_cantidad'] ?? null;
        $numeroLote = $validated['numero_lote'] ?? null;
        $fechaVencimiento = $validated['fecha_vencimiento'] ?? null;

        DB::transaction(function () use (
            $productoData,
            $userId,
            $stockSedeId,
            $stockCantidad,
            $numeroLote,
            $fechaVencimiento,
            $inventarioLoteService,
        ): void {
            $producto = Producto::create([
                ...$productoData,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            if ($stockSedeId !== null && $stockCantidad !== null) {
                $inventarioLoteService->registrarEntrada(
                    (string) $producto->id,
                    (string) $stockSedeId,
                    (string) $stockCantidad,
                    is_string($numeroLote) ? $numeroLote : null,
                    is_string($fechaVencimiento) ? $fechaVencimiento : null,
                    'Stock inicial al crear producto',
                    $userId !== null ? (string) $userId : null,
                );
            }
        });

        return back()->with('success', 'Producto creado correctamente.');
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        $filename = 'plantilla_productos_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function (): void {
            (new ProductosInventarioImportTemplateXlsx)->streamTo('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('productos.view'), 403);

        $search = trim((string) $request->string('search', ''));
        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todas';
        }

        $categoriaFiltro = (string) $request->string('categoria_id', '');
        $categoriaFiltroUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $categoriaFiltro) === 1
            ? $categoriaFiltro
            : null;

        $query = Producto::query()->with(['categoria:id,nombre']);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('nombre');
            $query->orderByDesc('created_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('slug', 'ILIKE', "%{$search}%")
                    ->orWhere('sku', 'ILIKE', "%{$search}%")
                    ->orWhere('codigo_barras', 'ILIKE', "%{$search}%")
                    ->orWhere('descripcion', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activa') {
            $query->where('activo', true);
        } elseif ($estado === 'inactiva') {
            $query->where('activo', false);
        }

        if ($categoriaFiltroUuid !== null) {
            $query->where('categoria_id', $categoriaFiltroUuid);
        }

        $filename = 'productos-inventario-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new ProductosInventarioXlsxExport;
        $this->auditExport('productos', $filename);

        return response()->streamDownload(
            function () use ($exporter, $query): void {
                $exporter->streamTo($query);
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
        );
    }

    public function importExcel(Request $request, ProductoInventarioImportService $importService): JsonResponse
    {
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

    public function storeQuick(ProductoInventarioQuickStoreRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $validated = $request->validated();

        $producto = Producto::query()->create([
            'categoria_id' => null,
            'nombre' => $validated['nombre'],
            'slug' => $this->generarSlugUnico((string) $validated['nombre']),
            'descripcion' => null,
            'sku' => $validated['sku'] ?? null,
            'codigo_barras' => null,
            'unidad' => $validated['unidad'] ?? 'UN',
            'precio_venta' => $validated['precio_venta'] ?? null,
            'precio_compra' => $validated['precio_compra'] ?? null,
            'stock_minimo' => null,
            'medicamento' => (bool) ($validated['medicamento'] ?? false),
            'activo' => true,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return response()->json([
            'data' => [
                'id' => (string) $producto->id,
                'nombre' => (string) $producto->nombre,
                'sku' => $producto->sku,
            ],
        ], 201);
    }

    private function generarSlugUnico(string $nombre): ?string
    {
        $base = Str::slug($nombre);
        if ($base === '') {
            return null;
        }

        $base = mb_substr($base, 0, 150);
        $slug = $base;
        $i = 0;

        while (Producto::query()->where('slug', $slug)->exists()) {
            $i++;
            $slug = mb_substr($base.'-'.$i, 0, 160);
        }

        return $slug;
    }

    public function update(
        ProductoInventarioRequest $request,
        Producto $producto,
        InventarioLoteService $inventarioLoteService,
    ): RedirectResponse {
        $validated = $request->validated();
        $lotes = is_array($validated['lotes'] ?? null) ? $validated['lotes'] : [];
        $stockSedeId = $validated['stock_inicial_sede_id'] ?? null;
        $stockCantidad = $validated['stock_inicial_cantidad'] ?? null;
        $numeroLote = $validated['numero_lote'] ?? null;
        $fechaVencimiento = $validated['fecha_vencimiento'] ?? null;
        $userId = Auth::id();

        DB::transaction(function () use (
            $producto,
            $validated,
            $lotes,
            $stockSedeId,
            $stockCantidad,
            $numeroLote,
            $fechaVencimiento,
            $userId,
            $inventarioLoteService,
        ): void {
            $producto->update([
                ...Arr::except($validated, [
                    'stock_inicial_sede_id',
                    'stock_inicial_cantidad',
                    'numero_lote',
                    'fecha_vencimiento',
                    'lotes',
                ]),
                'updated_by_id' => $userId,
            ]);

            $this->corregirLotes($producto, $lotes);

            $hayDatoLote = (is_string($numeroLote) && $numeroLote !== '')
                || (is_string($fechaVencimiento) && $fechaVencimiento !== '');

            if ($stockSedeId !== null && $stockCantidad !== null) {
                $inventarioLoteService->registrarEntrada(
                    (string) $producto->id,
                    (string) $stockSedeId,
                    (string) $stockCantidad,
                    is_string($numeroLote) ? $numeroLote : null,
                    is_string($fechaVencimiento) ? $fechaVencimiento : null,
                    'Stock inicial al editar producto',
                    $userId !== null ? (string) $userId : null,
                );
            } elseif ($lotes === [] && $hayDatoLote) {
                if (! is_string($stockSedeId) || $stockSedeId === '') {
                    throw ValidationException::withMessages([
                        'stock_inicial_sede_id' => 'Elige la sede del lote.',
                    ]);
                }

                ProductoLote::query()->create([
                    'producto_id' => $producto->id,
                    'sede_id' => $stockSedeId,
                    'numero_lote' => InventarioLoteService::normalizarNumeroLote(
                        is_string($numeroLote) ? $numeroLote : null,
                    ),
                    'fecha_vencimiento' => is_string($fechaVencimiento) && $fechaVencimiento !== ''
                        ? $fechaVencimiento
                        : null,
                    'cantidad' => 0,
                ]);
            }
        });

        return back()->with('success', 'Producto actualizado correctamente.');
    }

    /**
     * Corrige número y vencimiento de lotes ya existentes. No mueve cantidades.
     *
     * @param  list<array{id?: mixed, numero_lote?: mixed, fecha_vencimiento?: mixed}>  $lotes
     */
    private function corregirLotes(Producto $producto, array $lotes): void
    {
        foreach ($lotes as $index => $row) {
            $id = is_string($row['id'] ?? null) ? $row['id'] : '';
            $lote = ProductoLote::query()
                ->whereKey($id)
                ->where('producto_id', $producto->id)
                ->lockForUpdate()
                ->first();

            if ($lote === null) {
                throw ValidationException::withMessages([
                    "lotes.{$index}.id" => 'Ese lote ya no pertenece a este producto.',
                ]);
            }

            $numero = InventarioLoteService::normalizarNumeroLote(
                is_string($row['numero_lote'] ?? null) ? $row['numero_lote'] : null,
            );
            $vencimiento = is_string($row['fecha_vencimiento'] ?? null) && $row['fecha_vencimiento'] !== ''
                ? $row['fecha_vencimiento']
                : null;

            $duplicado = ProductoLote::query()
                ->where('producto_id', $producto->id)
                ->where('sede_id', $lote->sede_id)
                ->where('numero_lote', $numero)
                ->whereKeyNot($lote->id)
                ->where(function ($q) use ($vencimiento): void {
                    if ($vencimiento === null) {
                        $q->whereNull('fecha_vencimiento');
                    } else {
                        $q->whereDate('fecha_vencimiento', $vencimiento);
                    }
                })
                ->exists();

            if ($duplicado) {
                throw ValidationException::withMessages([
                    "lotes.{$index}.fecha_vencimiento" => 'Ya hay otro lote con ese número y vencimiento en la misma sede.',
                ]);
            }

            $lote->update([
                'numero_lote' => $numero,
                'fecha_vencimiento' => $vencimiento,
            ]);
        }
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->update(['updated_by_id' => Auth::id()]);
        $producto->delete();

        return back()->with('success', 'Producto eliminado correctamente.');
    }

    /**
     * Adjunta el lote FEFO próximo y el listado completo de lotes con stock (> 0).
     *
     * @param  Collection<int, Producto>  $productos
     */
    private function appendLoteProximo(Collection $productos): void
    {
        if ($productos->isEmpty()) {
            return;
        }

        $ids = $productos->pluck('id')->all();

        $lotes = ProductoLote::query()
            ->whereIn('producto_id', $ids)
            ->where(function ($q): void {
                $q->where('cantidad', '>', 0)->orWhereNotNull('fecha_vencimiento');
            })
            ->orderByRaw('fecha_vencimiento ASC NULLS LAST')
            ->orderBy('created_at')
            ->get(['id', 'producto_id', 'sede_id', 'numero_lote', 'fecha_vencimiento', 'cantidad']);

        $sedes = Sede::query()
            ->whereIn('id', $lotes->pluck('sede_id')->unique()->filter()->all())
            ->get(['id', 'nombre', 'codigo'])
            ->keyBy('id');

        $byProducto = $lotes->groupBy('producto_id');

        foreach ($productos as $producto) {
            $grupo = $byProducto->get((string) $producto->id) ?? collect();

            $conStock = $grupo->filter(static fn (ProductoLote $item): bool => (float) (string) $item->cantidad > 0)->values();

            /** @var ProductoLote|null $lote */
            $lote = $conStock->first() ?? $grupo->first();
            $numero = $lote !== null ? (string) $lote->numero_lote : null;
            if ($numero === InventarioLoteService::LOTE_SIN_ESPECIFICAR) {
                $numero = null;
            }

            $producto->setAttribute('lote_numero', $numero);
            $producto->setAttribute(
                'lote_vencimiento',
                $lote?->fecha_vencimiento?->format('Y-m-d'),
            );

            $filas = $grupo->map(static function (ProductoLote $item) use ($sedes): array {
                    $num = (string) $item->numero_lote;
                    if ($num === InventarioLoteService::LOTE_SIN_ESPECIFICAR) {
                        $num = null;
                    }
                    $sede = $sedes->get($item->sede_id);

                    return [
                        'id' => $item->id,
                        'numero_lote' => $num,
                        'fecha_vencimiento' => $item->fecha_vencimiento?->format('Y-m-d'),
                        'cantidad' => (string) $item->cantidad,
                        'sede_id' => $item->sede_id,
                        'sede_nombre' => $sede?->nombre,
                        'sede_codigo' => $sede?->codigo,
                    ];
                })->values()->all();

            // `lotes` choca con la relación del modelo; `lotes_stock` es el que usa el formulario.
            $producto->setAttribute('lotes', $filas);
            $producto->setAttribute('lotes_stock', $filas);
        }
    }
}
