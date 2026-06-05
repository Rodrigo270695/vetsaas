<?php

namespace App\Http\Controllers;

use App\Exports\TenantsXlsxExport;
use App\Http\Requests\TenantRequest;
use App\Models\Departamento;
use App\Models\Plan;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administración de tenants desde el panel del **superadmin**.
 *
 * Notas de diseño:
 *   - En operación normal, los tenants nacen vía provisioner HTTP desde
 *     Orvae PE (que también crea el schema físico). Este controller
 *     existe para soporte, migración manual y pruebas internas.
 *   - El `schema_name` lo derivamos del slug (`vet_<slug_normalizado>`).
 *     **No** disparamos `CREATE SCHEMA` aquí: eso queda fuera del CRUD
 *     básico y se delegará al provisioner / job dedicado más adelante.
 *   - Las transiciones de estado se hacen vía endpoints específicos
 *     (`suspend` / `resume`) para forzar auditoría clara y separar
 *     validaciones (motivo de suspensión es obligatorio).
 *
 * Hermano de SedeController/RoleController/UserController:
 *   - Mismo contrato de filtros (search, sort, direction, per_page).
 *   - Mismas opciones de per_page y columnas ordenables.
 *   - Mismo formato de bulk delete y export XLSX.
 */
class TenantController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'slug',
        'razon_social',
        'estado',
        'trial_ends_at',
        'created_at',
    ];

    /**
     * Filtros aceptados para `estado`:
     *   - 'todos'      → sin filtro (default).
     *   - 'trial'      → estado = 'trial'.
     *   - 'active'     → estado = 'active'.
     *   - 'suspended'  → estado = 'suspended'.
     *   - 'cancelled'  → estado = 'cancelled'.
     */
    private const ESTADO_OPTIONS = ['todos', 'trial', 'active', 'suspended', 'cancelled'];

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

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $query = $this->buildBaseQuery($search, $estado);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $tenants = $query
            ->with([
                'subscriptions' => fn ($q) => $q
                    ->whereIn('estado', ['trial', 'active', 'grace'])
                    ->latest()
                    ->limit(1),
                'subscriptions.plan:id,codigo,nombre,badge,color_hex',
                'distritoModel:id,name,provincia_id',
                'distritoModel.provincia:id,name,departamento_id',
                'distritoModel.provincia.departamento:id,name',
            ])
            ->paginate($perPage)
            ->withQueryString();

        // Catálogo de planes para el form de creación (mostramos solo
        // los públicos y activos; superadmin puede ver todos).
        $plansCatalog = Plan::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->get(['id', 'codigo', 'nombre', 'trial_days', 'precio_mensual']);

        // Catálogo de departamentos para el cascada geo. ~25 filas; el
        // resto (provincias/distritos) se cargan on-demand vía /geo/*.
        $departamentos = Departamento::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Estadísticas globales para los chips/cards del header.
        $statsByEstado = Tenant::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        return Inertia::render('plataforma/tenants/index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => Tenant::query()->count(),
                'trial' => (int) ($statsByEstado['trial'] ?? 0),
                'active' => (int) ($statsByEstado['active'] ?? 0),
                'suspended' => (int) ($statsByEstado['suspended'] ?? 0),
                'cancelled' => (int) ($statsByEstado['cancelled'] ?? 0),
                'coincidencias' => $tenants->total(),
            ],
            'plans_catalog' => $plansCatalog,
            'departamentos' => $departamentos,
        ]);
    }

    public function store(TenantRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $slug = $data['slug'];
        $schemaName = $this->schemaNameFromSlug($slug);

        // En lugar de relanzar errores 23505 al frontend, validamos
        // explícitamente la unicidad del schema_name. Es la última línea
        // de defensa: el slug ya es único, así que esto debería ser raro.
        if (Tenant::query()->where('schema_name', $schemaName)->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'El schema derivado de ese subdominio ya está en uso. Elige otro slug.',
            ]);
        }

        $trialDays = (int) ($data['trial_days'] ?? 14);

        Tenant::create([
            'slug' => $slug,
            'schema_name' => $schemaName,
            'razon_social' => $data['razon_social'],
            'nombre_comercial' => $data['nombre_comercial'] ?? null,
            'ruc' => $data['ruc'] ?? null,
            'email_admin' => $data['email_admin'],
            'telefono' => $data['telefono'] ?? null,
            'distrito_id' => $data['distrito_id'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'timezone' => $data['timezone'],
            'locale' => $data['locale'],
            'canal_adquisicion' => $data['canal_adquisicion'] ?? null,
            'estado' => 'trial',
            'trial_ends_at' => now()->addDays($trialDays),
            'onboarding_completado' => false,
            'onboarding_paso' => 0,
        ]);

        return back()->with('success', 'Tenant creado correctamente. El provisioner deberá crear el schema físico.');
    }

    public function update(TenantRequest $request, Tenant $tenant, TenantManager $manager): RedirectResponse
    {
        $data = $request->validated();

        // El schema_name NO se reasigna en update: cambiar el slug acá
        // implicaría migrar todo el schema físico, lo cual no es algo
        // que queramos disparar desde un CRUD básico. Bloqueamos cambios
        // de slug si el tenant ya está activo en producción.
        if ($tenant->slug !== $data['slug'] && in_array($tenant->estado, ['active', 'suspended'], true)) {
            throw ValidationException::withMessages([
                'slug' => 'No puedes cambiar el subdominio de un tenant que ya está en producción (estado active/suspended).',
            ]);
        }

        $payload = [
            'slug' => $data['slug'],
            'razon_social' => $data['razon_social'],
            'nombre_comercial' => $data['nombre_comercial'] ?? null,
            'ruc' => $data['ruc'] ?? null,
            'email_admin' => $data['email_admin'],
            'telefono' => $data['telefono'] ?? null,
            'distrito_id' => $data['distrito_id'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'timezone' => $data['timezone'],
            'locale' => $data['locale'],
            'canal_adquisicion' => $data['canal_adquisicion'] ?? null,
        ];

        // Solo recalculamos schema_name si el tenant aún está en trial y
        // sí cambió el slug; así una corrección rápida de tipeo en los
        // primeros minutos no obliga a borrar y recrear el registro.
        if ($tenant->estado === 'trial' && $tenant->slug !== $data['slug']) {
            $newSchema = $this->schemaNameFromSlug($data['slug']);
            if (Tenant::query()->where('schema_name', $newSchema)->whereKeyNot($tenant->id)->exists()) {
                throw ValidationException::withMessages([
                    'slug' => 'El schema derivado de ese subdominio ya está en uso. Elige otro slug.',
                ]);
            }
            $payload['schema_name'] = $newSchema;
        }

        $tenant->update($payload);

        // Cualquier cambio en slug, schema_name o estado debe invalidar
        // el cache del TenantManager para que la próxima request al
        // subdominio refleje el nuevo valor inmediatamente.
        $manager->flushCacheFor($tenant->fresh() ?? $tenant);

        return back()->with('success', 'Tenant actualizado correctamente.');
    }

    /**
     * Pasa el tenant a `suspended` (acceso bloqueado al subdominio).
     * Requiere motivo explícito para tener trazabilidad.
     */
    public function suspend(Request $request, Tenant $tenant, TenantManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($tenant->estado === 'suspended') {
            return back()->with('info', 'El tenant ya estaba suspendido.');
        }

        if ($tenant->estado === 'cancelled') {
            throw ValidationException::withMessages([
                'reason' => 'No se puede suspender un tenant cancelado.',
            ]);
        }

        $tenant->update([
            'estado' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $data['reason'],
        ]);

        $manager->flushCacheFor($tenant);

        return back()->with('success', 'Tenant suspendido correctamente.');
    }

    /**
     * Pasa el tenant de `suspended` a `active`. Solo aplica si previamente
     * estaba suspendido. No hace upgrade desde trial → active (eso es
     * responsabilidad del flujo de suscripciones).
     */
    public function resume(Tenant $tenant, TenantManager $manager): RedirectResponse
    {
        if ($tenant->estado !== 'suspended') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede reanudar un tenant suspendido.',
            ]);
        }

        $tenant->update([
            'estado' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $manager->flushCacheFor($tenant);

        return back()->with('success', 'Tenant reanudado correctamente.');
    }

    public function destroy(Tenant $tenant, TenantManager $manager): RedirectResponse
    {
        if ($tenant->estado === 'active') {
            throw ValidationException::withMessages([
                'id' => 'No se puede eliminar un tenant activo. Cancélalo o suspéndelo primero.',
            ]);
        }

        $tenant->update([
            'estado' => 'cancelled',
            'cancelled_at' => $tenant->cancelled_at ?? now(),
        ]);
        $tenant->delete();

        $manager->flushCacheFor($tenant);

        return back()->with('success', 'Tenant eliminado (soft delete) correctamente.');
    }

    /**
     * Eliminación masiva con softdelete. Filtra automáticamente los tenants
     * activos para que no se borren por accidente.
     */
    public function bulkDestroy(Request $request, TenantManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid'],
        ]);

        $deletable = Tenant::query()
            ->whereIn('id', $data['ids'])
            ->whereNotIn('estado', ['active'])
            ->get(['id', 'slug']);

        $deletableIds = $deletable->pluck('id')->all();

        if (empty($deletableIds)) {
            return back()->with(
                'info',
                'No se eliminó ningún tenant: todos los seleccionados estaban activos.',
            );
        }

        Tenant::whereIn('id', $deletableIds)->update([
            'estado' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $count = Tenant::whereIn('id', $deletableIds)->delete();
        $skipped = count($data['ids']) - $count;

        /** @var Tenant $tenant */
        foreach ($deletable as $tenant) {
            $manager->flushCacheFor($tenant);
        }

        $message = $count === 1
            ? '1 tenant eliminado correctamente.'
            : "{$count} tenants eliminados correctamente.";

        if ($skipped > 0) {
            $message .= sprintf(
                ' (%d tenant%s activo%s se omitieron)',
                $skipped,
                $skipped === 1 ? '' : 's',
                $skipped === 1 ? '' : 's',
            );
        }

        return back()->with('success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search, $estado)
            ->with([
                'subscriptions' => fn ($q) => $q
                    ->whereIn('estado', ['trial', 'active', 'grace'])
                    ->latest()
                    ->limit(1),
                'subscriptions.plan:id,codigo,nombre',
            ]);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $filename = 'tenants-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new TenantsXlsxExport;

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

    /**
     * @return Builder<Tenant>
     */
    private function buildBaseQuery(string $search, string $estado): Builder
    {
        $query = Tenant::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('slug', 'ILIKE', "%{$search}%")
                    ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                    ->orWhere('nombre_comercial', 'ILIKE', "%{$search}%")
                    ->orWhere('email_admin', 'ILIKE', "%{$search}%")
                    ->orWhere('ruc', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        return $query;
    }

    /**
     * Deriva el schema físico desde el slug. Reemplaza guiones por
     * underscores (los schemas Postgres no admiten guion sin quoting).
     */
    private function schemaNameFromSlug(string $slug): string
    {
        return 'vet_'.Str::of($slug)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
