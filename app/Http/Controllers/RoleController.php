<?php

namespace App\Http\Controllers;

use App\Exports\RolesXlsxExport;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
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
                'total' => Role::count(),
                'sistema' => Role::ofType('sistema')->count(),
                'personalizados' => Role::ofType('personalizado')->count(),
                'coincidencias' => $roles->total(),
            ],
            'permissions_catalog' => $permissionsCatalog,
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        ClinicAdminScope::assertRoleAccessible($role);

        // Bloqueamos toda edición de roles del sistema. Hacerlo en el
        // controller (no solo en el front) protege contra requests crafteados.
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'name' => 'No se puede modificar un rol del sistema.',
            ]);
        }

        $data = $request->validated();

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

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
        ClinicAdminScope::assertRoleAccessible($role);

        // Nota de diseño:
        // Mantenemos bloqueada la edición de METADATA y la eliminación de
        // roles del sistema (`update`/`destroy`) porque romperían contratos
        // del código (ej: el bypass de `superadmin` en `usePermission`
        // depende del NOMBRE exacto). Sin embargo SÍ permitimos editar el
        // set de permisos del superadmin: si el dueño quiere probar
        // restricciones temporales, debe poder hacerlo desde la UI sin
        // tener que ejecutar comandos artisan. El frontend muestra un
        // banner de advertencia cuando se edita un rol del sistema.

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

        $role->syncPermissions($permissions);

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
        ClinicAdminScope::assertRoleAccessible($role);

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'name' => 'No se puede eliminar un rol del sistema.',
            ]);
        }

        $role->delete();

        return back()->with('success', 'Rol eliminado correctamente.');
    }

    /**
     * Eliminación masiva con safeguard: descartamos cualquier ID que
     * apunte a un rol del sistema. Si quedan 0 IDs válidos, devolvemos
     * un toast informativo en vez de un error duro.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $deletableIds = ClinicAdminScope::rolesQuery()
            ->whereIn('id', $data['ids'])
            ->whereNotIn('name', Role::SYSTEM_ROLES)
            ->pluck('id')
            ->all();

        if (empty($deletableIds)) {
            return back()->with('info', 'No se eliminaron roles: la selección solo incluía roles del sistema.');
        }

        $count = Role::whereIn('id', $deletableIds)->delete();
        $skipped = count($data['ids']) - $count;

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
