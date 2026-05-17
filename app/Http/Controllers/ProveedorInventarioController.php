<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProveedorInventarioRequest;
use App\Models\Proveedor;
use App\Services\Integrations\ApiPeruRucService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ProveedorInventarioController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = ['ruc', 'razon_social', 'activo', 'created_at'];

    private const ESTADO_OPTIONS = ['todas', 'activa', 'inactiva'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'asc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todas';
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = Proveedor::query();

        if ($canAudit) {
            $query->with(['creadoPor:id,name,email', 'actualizadoPor:id,name,email']);
        }

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('razon_social');
            $query->orderByDesc('created_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('ruc', 'ILIKE', "%{$search}%")
                    ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('telefono', 'ILIKE', "%{$search}%")
                    ->orWhere('notas', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activa') {
            $query->where('activo', true);
        } elseif ($estado === 'inactiva') {
            $query->where('activo', false);
        }

        $proveedores = $query->paginate($perPage)->withQueryString();

        return Inertia::render('inventario/proveedores/index', [
            'proveedores' => $proveedores,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Proveedor::count(),
                'activos' => Proveedor::where('activo', true)->count(),
                'inactivos' => Proveedor::where('activo', false)->count(),
                'coincidencias' => $proveedores->total(),
            ],
        ]);
    }

    /**
     * Consulta RUC en SUNAT (apiperu.dev) desde el servidor.
     */
    public function consultaRuc(Request $request, ApiPeruRucService $apiPeru): JsonResponse
    {
        $ruc = preg_replace('/\D+/', '', (string) $request->query('ruc', ''));
        $request->merge(['ruc' => $ruc]);

        $validated = $request->validate([
            'ruc' => ['required', 'string', 'regex:/^[0-9]{11}$/'],
        ]);

        try {
            $data = $apiPeru->consultar($validated['ruc']);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo completar la consulta RUC. Intente de nuevo.',
            ], 503);
        }
    }

    public function store(ProveedorInventarioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = Auth::id();

        Proveedor::create([
            ...$data,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return back()->with('success', 'Proveedor creado correctamente.');
    }

    public function update(ProveedorInventarioRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update([
            ...$request->validated(),
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update(['updated_by_id' => Auth::id()]);
        $proveedor->delete();

        return back()->with('success', 'Proveedor eliminado correctamente.');
    }
}
