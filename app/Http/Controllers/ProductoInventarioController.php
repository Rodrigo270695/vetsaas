<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductoInventarioQuickStoreRequest;
use App\Http\Requests\ProductoInventarioRequest;
use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Models\Sede;
use App\Services\Inventario\InventarioLoteService;
use App\Support\Inventario\UnidadMedidaOpciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductoInventarioController extends Controller
{
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

    public function update(ProductoInventarioRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update([
            ...Arr::except($request->validated(), [
                'stock_inicial_sede_id',
                'stock_inicial_cantidad',
                'numero_lote',
                'fecha_vencimiento',
            ]),
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->update(['updated_by_id' => Auth::id()]);
        $producto->delete();

        return back()->with('success', 'Producto eliminado correctamente.');
    }
}
