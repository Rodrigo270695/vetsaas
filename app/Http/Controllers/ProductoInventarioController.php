<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductoInventarioRequest;
use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Support\Inventario\UnidadMedidaOpciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $categoriaOptions = CategoriaProducto::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $unidadOptions = UnidadMedidaOpciones::forProductoForm();

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
        ]);
    }

    public function store(ProductoInventarioRequest $request): RedirectResponse
    {
        $userId = Auth::id();

        Producto::create([
            ...$request->validated(),
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return back()->with('success', 'Producto creado correctamente.');
    }

    public function update(ProductoInventarioRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update([
            ...$request->validated(),
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
