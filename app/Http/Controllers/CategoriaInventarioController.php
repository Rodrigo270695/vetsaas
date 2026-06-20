<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaProductoRequest;
use App\Models\CategoriaProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaInventarioController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = ['nombre', 'slug', 'orden', 'activo', 'created_at'];

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

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = CategoriaProducto::query()
            ->with(['parent:id,nombre,slug']);

        if ($canAudit) {
            $query->with(['creadoPor:id,name,email', 'actualizadoPor:id,name,email']);
        }

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('orden');
            $query->orderBy('nombre');
            $query->orderByDesc('created_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('slug', 'ILIKE', "%{$search}%")
                    ->orWhere('descripcion', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activa') {
            $query->where('activo', true);
        } elseif ($estado === 'inactiva') {
            $query->where('activo', false);
        }

        $categorias = $query->paginate($perPage)->withQueryString();

        $parentOptions = CategoriaProducto::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('inventario/categorias/index', [
            'categorias' => $categorias,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => CategoriaProducto::count(),
                'activas' => CategoriaProducto::where('activo', true)->count(),
                'inactivas' => CategoriaProducto::where('activo', false)->count(),
                'coincidencias' => $categorias->total(),
            ],
            'parentOptions' => $parentOptions,
        ]);
    }

    public function store(CategoriaProductoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = Auth::id();

        CategoriaProducto::create([
            ...$data,
            'orden' => CategoriaProducto::generateNextOrden($data['parent_id'] ?? null),
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(CategoriaProductoRequest $request, CategoriaProducto $categoria): RedirectResponse
    {
        $categoria->update([
            ...$request->validated(),
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(CategoriaProducto $categoria): RedirectResponse
    {
        $categoria->update(['updated_by_id' => Auth::id()]);
        $categoria->delete();

        return back()->with('success', 'Categoría eliminada correctamente.');
    }
}
