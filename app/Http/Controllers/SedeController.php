<?php

namespace App\Http\Controllers;

use App\Exports\SedesXlsxExport;
use App\Http\Requests\SedeRequest;
use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SedeController extends Controller
{
    /**
     * Tenant del host actual (clínica). Incluye modo soporte:
     * el superadmin tiene `users.tenant_id = null`, pero el subdominio
     * ya resolvió el tenant vía TenantManager.
     */
    private function tenantIdOrAbort(Request $request): string
    {
        $id = tenant_id() ?? $request->user()?->tenant_id;
        abort_if($id === null || $id === '', 403, 'Solo usuarios de clínica pueden gestionar sedes.');

        return (string) $id;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Sede>
     */
    private function sedesQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return Sede::query()->where('tenant_id', $this->tenantIdOrAbort($request));
    }

    /**
     * Tamaños de página permitidos en el selector del paginador.
     * Cualquier valor distinto se "normaliza" al más cercano superior.
     */
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    /**
     * Columnas que pueden usarse para ordenar desde el frontend.
     * Cualquier otro valor se descarta y se aplica el orden default.
     */
    private const SORTABLE_COLUMNS = [
        'codigo',
        'nombre',
        'distrito',
        'provincia',
        'departamento',
        'telefono',
        'activa',
        'created_at',
    ];

    /**
     * Valores aceptados para el filtro `estado`.
     * 'todas' = sin filtro (default).
     */
    private const ESTADO_OPTIONS = ['todas', 'activa', 'inactiva'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todas';
        }

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $tenantId = $this->tenantIdOrAbort($request);
        $query = Sede::query()->where('tenant_id', $tenantId);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            // Desempate determinístico para evitar saltos entre páginas si
            // varias filas comparten el valor de la columna ordenada.
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        // Solo carga audit trail si el usuario puede verlo.
        // Ahorra una query JOIN cuando el cliente (admin_clinica) lista sus sedes.
        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        // Eager load del distrito + cadena geográfica para mostrar
        // el path completo en la tabla y al editar.
        $query->with([
            'distritoModel:id,name,provincia_id',
            'distritoModel.provincia:id,name,departamento_id',
            'distritoModel.provincia.departamento:id,name',
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('codigo', 'ILIKE', "%{$search}%")
                    ->orWhere('direccion', 'ILIKE', "%{$search}%")
                    ->orWhere('distrito', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activa') {
            $query->where('activa', true);
        } elseif ($estado === 'inactiva') {
            $query->where('activa', false);
        }

        $sedes = $query->paginate($perPage)->withQueryString();

        // Catálogo de departamentos (25 filas, ~2KB) cargado inline:
        // se necesita siempre que se abra el modal de crear/editar.
        // Provincias y distritos se piden on-demand vía /geo/* para
        // no inflar el payload (1874 distritos sería excesivo).
        $departamentos = Departamento::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('configuracion/sedes/index', [
            'sedes' => $sedes,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Sede::where('tenant_id', $tenantId)->count(),
                'activas' => Sede::where('tenant_id', $tenantId)->where('activa', true)->count(),
                'inactivas' => Sede::where('tenant_id', $tenantId)->where('activa', false)->count(),
                // Cantidad de coincidencias con los filtros actuales (incluye
                // todas las páginas, no solo la visible). Más claro que "pantalla".
                'coincidencias' => $sedes->total(),
            ],
            'departamentos' => $departamentos,
        ]);
    }

    public function store(SedeRequest $request): RedirectResponse
    {
        $tenantId = $this->tenantIdOrAbort($request);
        $userId = Auth::id();
        $data = $this->hydrateLocationFromDistrito($request->validated());

        Sede::create([
            ...$data,
            'tenant_id' => $tenantId,
            'codigo' => Sede::generateNextCode($tenantId),
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return back()->with('success', 'Sede creada correctamente.');
    }

    public function update(SedeRequest $request, Sede $sede): RedirectResponse
    {
        $this->assertSedeBelongsToTenant($request, $sede);
        $data = $this->hydrateLocationFromDistrito($request->validated());

        $sede->update([
            ...$data,
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Sede actualizada correctamente.');
    }

    /**
     * Hidrata los campos denormalizados (`distrito`, `provincia`,
     * `departamento`) a partir de `distrito_id`.
     *
     * Si el usuario limpia el distrito (`distrito_id = null`), también
     * se limpian los strings. Esto mantiene siempre la consistencia
     * entre la FK y el cache de nombres.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateLocationFromDistrito(array $data): array
    {
        $distritoId = $data['distrito_id'] ?? null;

        if ($distritoId === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $distrito = Distrito::query()
            ->with('provincia.departamento')
            ->find($distritoId);

        if ($distrito === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $data['distrito'] = $distrito->name;
        $data['provincia'] = $distrito->provincia?->name;
        $data['departamento'] = $distrito->provincia?->departamento?->name;

        return $data;
    }

    public function destroy(Request $request, Sede $sede): RedirectResponse
    {
        $this->assertSedeBelongsToTenant($request, $sede);
        $sede->update(['updated_by_id' => Auth::id()]);
        $sede->delete();

        return back()->with('success', 'Sede eliminada correctamente.');
    }

    /**
     * Eliminación masiva de sedes (soft delete) por IDs.
     *
     * Se valida que sea un arreglo no vacío de UUIDs válidos y se
     * actualiza `updated_by_id` antes del delete para que el audit trail
     * registre quién hizo la operación.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantIdOrAbort($request);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['uuid'],
        ]);

        $userId = Auth::id();

        // Update + delete por separado para mantener el audit trail.
        Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $data['ids'])
            ->update(['updated_by_id' => $userId]);
        $count = Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $data['ids'])
            ->delete();

        return back()->with(
            'success',
            $count === 1
                ? '1 sede eliminada correctamente.'
                : "{$count} sedes eliminadas correctamente.",
        );
    }

    /**
     * Exporta las sedes a XLSX respetando los filtros vigentes en la URL.
     *
     * Output: archivo `.xlsx` con tabla nativa de Excel (estilo MEDIUM 2),
     * cabecera congelada, autofilter, alternancia de filas y autosize por
     * columna. La generación se delega a `SedesXlsxExport` para mantener
     * el controller delgado y la lógica testable de forma aislada.
     *
     * Usa `streamDownload` (sin output buffering previo) para que Symfony
     * envíe el archivo directamente al cliente sin saturar la memoria.
     */
    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));
        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todas';
        }

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->sedesQuery($request);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('codigo', 'ILIKE', "%{$search}%")
                    ->orWhere('direccion', 'ILIKE', "%{$search}%")
                    ->orWhere('distrito', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activa') {
            $query->where('activa', true);
        } elseif ($estado === 'inactiva') {
            $query->where('activa', false);
        }

        $filename = 'sedes-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new SedesXlsxExport();

        return response()->streamDownload(
            function () use ($exporter, $query) {
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

    private function assertSedeBelongsToTenant(Request $request, Sede $sede): void
    {
        abort_unless($sede->tenant_id === $this->tenantIdOrAbort($request), 404);
    }
}
