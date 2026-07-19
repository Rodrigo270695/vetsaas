<?php

namespace App\Http\Controllers;

use App\Exports\RolesXlsxExport;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Services\Audit\PlatformSecurityAuditLogger;
use App\Support\Tenancy\ClinicAdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoleController extends Controller
{
    /**
     * Tamaños de página permitidos en el selector del paginador.
     * Cualquier valor distinto se "normaliza" al default.
     */
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    /**
     * Columnas por las que se puede ordenar desde el frontend.
     */
    private const SORTABLE_COLUMNS = [
        'name',
        'description',
        'permissions_count',
        'created_at',
    ];

    /**
     * Valores aceptados para el filtro `tipo`.
     * 'todos' = sin filtro (default).
     */
    private const TIPO_OPTIONS = ['todos', 'sistema', 'personalizado'];

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

        $tipo = (string) $request->string('tipo', 'todos');
        if (! in_array($tipo, self::TIPO_OPTIONS, true)) {
            $tipo = 'todos';
        }

        $query = $this->buildBaseQuery($search, $tipo);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('name');
        }

        $roles = $query
            ->withCount('permissions')
            ->with(['permissions:id,name'])
            ->paginate($perPage)
            ->withQueryString();

        // Catálogo de permisos agrupados por módulo para el modal de
        // crear/editar. Se manda en cada request del index porque el
        // catálogo es pequeño (~120 filas) y simplifica el frontend.
        $permissionsCatalog = $this->buildPermissionsCatalog($request);

        // Stats acotados al alcance actual (tenant o plataforma).
        $statsBase = ClinicAdminScope::rolesQuery();

        return Inertia::render('configuracion/roles/index', [
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'tipo' => $tipo,
            ],
            'stats' => [
                'total' => (clone $statsBase)->count(),
                'sistema' => (clone $statsBase)->ofType('sistema')->count(),
                'personalizados' => (clone $statsBase)->ofType('personalizado')->count(),
                'coincidencias' => $roles->total(),
            ],
            'permissions_catalog' => $permissionsCatalog,
            // Demo: bloquear mutaciones (roles por tenant, pero demo es pública).
            'mutations_locked' => is_public_demo_tenant(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'description' => $data['description'] ?? null,
            'tenant_id' => tenant_id(),
        ]);

        PlatformSecurityAuditLogger::log(
            action: 'roles.created',
            modulo: 'roles',
            summary: 'Creó el rol '.$role->name,
            metadata: [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'description' => $role->description,
            ],
            subjectType: 'role',
            subjectId: (string) $role->id,
            subjectLabel: $role->name,
        );

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        ClinicAdminScope::assertRoleAccessible($role);

        // Bloqueamos toda edición de roles del sistema. Hacerlo en el
        // controller (no solo en el front) protege contra requests crafteados.
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'name' => 'No se puede renombrar un rol protegido ('.$role->name.').',
            ]);
        }

        $data = $request->validated();
        $before = [
            'name' => $role->name,
            'description' => $role->description,
        ];

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        PlatformSecurityAuditLogger::log(
            action: 'roles.updated',
            modulo: 'roles',
            summary: 'Actualizó el rol '.$before['name'].($before['name'] !== $role->name ? ' → '.$role->name : ''),
            metadata: [
                'before' => $before,
                'after' => [
                    'name' => $role->name,
                    'description' => $role->description,
                ],
            ],
            subjectType: 'role',
            subjectId: (string) $role->id,
            subjectLabel: $role->name,
        );

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    /**
     * Sincroniza la lista completa de permisos asociados al rol.
     *
     * Endpoint dedicado para mantener el flujo de "crear/editar metadata"
     * separado del de "gestionar permisos". El modal de permisos solo manda
     * el array final de nombres y nosotros usamos `syncPermissions()`
     * (Spatie limpia diffs por nosotros).
     *
     * Reusa el permiso `roles.update` porque conceptualmente sigue siendo
     * una modificación del rol.
     */
    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        ClinicAdminScope::assertRoleAccessible($role);

        // Nota de diseño:
        // Bloqueamos metadata y eliminación de roles protegidos (`update`/`destroy`)
        // porque los nombres son contratos del código y porque borrar un rol
        // base de clínica CASCADE-vacía `model_has_roles` de TODAS las clínicas.
        // Sí permitimos ajustar permisos, con banner de advertencia en UI,
        // pero no dejar roles base/plataforma sin ningún permiso.

        $assignable = ClinicAdminScope::assignablePermissionNamesFor($request->user());

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [
                'string',
                Rule::exists(config('permission.table_names.permissions'), 'name')
                    ->where('guard_name', 'web'),
                Rule::in($assignable),
            ],
        ]);

        $permissions = $data['permissions'] ?? [];
        if (ClinicAdminScope::isClinicContext()) {
            $permissions = array_values(array_filter(
                $permissions,
                static fn (string $name): bool => ClinicAdminScope::isTenantAssignablePermission($name),
            ));
        }

        // Evitar dejar un rol base de clínica sin permisos (volvería a tumbar
        // el acceso de todas las clínicas que lo usan).
        if ($role->isBaseClinicRole() && $permissions === []) {
            throw ValidationException::withMessages([
                'permissions' => 'No puedes dejar sin permisos un rol base de clínica ('.$role->name.').',
            ]);
        }

        if ($role->isPlatformRole() && $permissions === []) {
            throw ValidationException::withMessages([
                'permissions' => 'No puedes dejar sin permisos el rol de plataforma '.$role->name.'.',
            ]);
        }

        $beforeNames = $role->permissions()->pluck('name')->sort()->values()->all();
        $role->syncPermissions($permissions);
        $afterNames = collect($permissions)->sort()->values()->all();

        PlatformSecurityAuditLogger::log(
            action: 'roles.permissions_updated',
            modulo: 'roles',
            summary: 'Cambió permisos del rol '.$role->name.' ('.count($beforeNames).' → '.count($afterNames).')',
            metadata: [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'is_protected' => $role->is_system,
                'before_count' => count($beforeNames),
                'after_count' => count($afterNames),
                'added' => array_values(array_diff($afterNames, $beforeNames)),
                'removed' => array_values(array_diff($beforeNames, $afterNames)),
            ],
            subjectType: 'role',
            subjectId: (string) $role->id,
            subjectLabel: $role->name,
        );

        $count = count($permissions);
        $message = $count === 0
            ? 'Se removieron todos los permisos del rol.'
            : ($count === 1
                ? '1 permiso asignado al rol.'
                : "{$count} permisos asignados al rol.");

        return back()->with('success', $message);
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        ClinicAdminScope::assertRoleAccessible($role);

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'name' => 'No se puede eliminar un rol protegido ('.$role->name.'). Afectaría a todas las clínicas.',
            ]);
        }

        $snapshot = [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permissions_count' => $role->permissions()->count(),
        ];

        $role->delete();

        PlatformSecurityAuditLogger::log(
            action: 'roles.deleted',
            modulo: 'roles',
            summary: 'Eliminó el rol '.$snapshot['role_name'],
            metadata: $snapshot,
            subjectType: 'role',
            subjectId: (string) $snapshot['role_id'],
            subjectLabel: $snapshot['role_name'],
        );

        return back()->with('success', 'Rol eliminado correctamente.');
    }

    /**
     * Eliminación masiva con safeguard: descartamos cualquier ID que
     * apunte a un rol del sistema. Si quedan 0 IDs válidos, devolvemos
     * un toast informativo en vez de un error duro.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->abortIfDemoRolesLocked();

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $requestedIds = $data['ids'];
        $protectedSkipped = ClinicAdminScope::rolesQuery()
            ->whereIn('id', $requestedIds)
            ->whereIn('name', Role::protectedRoleNames())
            ->pluck('name')
            ->all();

        $deletable = ClinicAdminScope::rolesQuery()
            ->whereIn('id', $requestedIds)
            ->whereNotIn('name', Role::protectedRoleNames())
            ->get(['id', 'name']);

        $deletableIds = $deletable->pluck('id')->all();
        $deletedNames = $deletable->pluck('name')->all();

        if (empty($deletableIds)) {
            PlatformSecurityAuditLogger::log(
                action: 'roles.bulk_deleted_blocked',
                modulo: 'roles',
                summary: 'Intentó borrado masivo de roles: solo roles protegidos (omitidos)',
                metadata: [
                    'requested_ids' => $requestedIds,
                    'protected_skipped' => $protectedSkipped,
                ],
            );

            return back()->with('info', 'No se eliminaron roles: la selección solo incluía roles del sistema.');
        }

        $count = Role::whereIn('id', $deletableIds)->delete();
        $skipped = count($requestedIds) - $count;

        PlatformSecurityAuditLogger::log(
            action: 'roles.bulk_deleted',
            modulo: 'roles',
            summary: 'Borrado masivo de '.$count.' rol(es): '.implode(', ', $deletedNames),
            metadata: [
                'deleted_ids' => $deletableIds,
                'deleted_names' => $deletedNames,
                'protected_skipped' => $protectedSkipped,
                'requested_count' => count($requestedIds),
                'deleted_count' => $count,
            ],
            subjectType: 'role',
            subjectLabel: implode(', ', $deletedNames),
        );

        $message = $count === 1
            ? '1 rol eliminado correctamente.'
            : "{$count} roles eliminados correctamente.";

        if ($skipped > 0) {
            $message .= sprintf(' (%d rol%s del sistema se omitieron)', $skipped, $skipped === 1 ? '' : 'es');
        }

        return back()->with('success', $message);
    }

    /**
     * Exporta los roles a XLSX respetando los filtros vigentes en la URL.
     */
    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));
        $tipo = (string) $request->string('tipo', 'todos');
        if (! in_array($tipo, self::TIPO_OPTIONS, true)) {
            $tipo = 'todos';
        }

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search, $tipo)
            ->withCount('permissions')
            ->with(['permissions:id,name']);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('name');
        }

        $filename = 'roles-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new RolesXlsxExport;

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
     * En el tenant público `demo` no se permiten alta/edición/borrado de
     * roles ni sync de permisos: Spatie no aísla por tenant y un cambio
     * afectaría a todas las clínicas reales.
     */
    private function abortIfDemoRolesLocked(): void
    {
        if (! is_public_demo_tenant()) {
            return;
        }

        abort(403, 'En la clínica demo no se pueden modificar roles ni permisos.');
    }

    /**
     * Construye el query base aplicando los filtros de búsqueda y tipo.
     *
     * Se extrae a método privado para que index/export apliquen
     * exactamente las mismas condiciones (un usuario espera que el XLSX
     * coincida con la vista en pantalla).
     *
     * @return Builder<Role>
     */
    private function buildBaseQuery(string $search, string $tipo): Builder
    {
        $query = ClinicAdminScope::rolesQuery();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        $query->ofType($tipo);

        return $query;
    }

    /**
     * Devuelve el catálogo de permisos agrupado por módulo, listo para
     * pintarse en el modal de crear/editar rol.
     *
     * Output:
     *   [
     *     ['module' => 'sedes', 'permissions' => [
     *        ['id' => 7, 'name' => 'sedes.view',  'action' => 'view'],
     *        ['id' => 8, 'name' => 'sedes.create','action' => 'create'],
     *        ...
     *     ]],
     *     ...
     *   ]
     *
     * @return array<int, array{module: string, permissions: array<int, array{id:int,name:string,action:string}>}>
     */
    private function buildPermissionsCatalog(Request $request): array
    {
        $all = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);

        if (ClinicAdminScope::isClinicContext()) {
            $allowed = ClinicAdminScope::assignablePermissionNamesFor($request->user());
            $all = $all->filter(
                static fn ($perm): bool => in_array($perm->name, $allowed, true),
            );
        }

        return ClinicAdminScope::groupPermissionsCatalog($all);
    }
}
