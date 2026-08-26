<?php

namespace App\Http\Controllers;

use App\Exports\UsersXlsxExport;
use App\Http\Controllers\Concerns\RespondsToApiPeruConsulta;
use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\PlatformSecurityAuditLogger;
use App\Services\Integrations\ApiPeruDniService;
use App\Support\Tenancy\ClinicAdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    use RespondsToApiPeruConsulta;
    /**
     * Tamaños de página permitidos en el selector del paginador.
     */
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    /**
     * Columnas por las que se puede ordenar desde el frontend.
     */
    private const SORTABLE_COLUMNS = [
        'name',
        'email',
        'last_login_at',
        'created_at',
    ];

    /**
     * Valores aceptados para el filtro `estado`:
     *   - 'todos'      → sin filtro (default).
     *   - 'activos'    → is_active = true.
     *   - 'inactivos'  → is_active = false.
     */
    private const ESTADO_OPTIONS = ['todos', 'activos', 'inactivos'];

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

        // Filtro adicional por rol (opcional). Vacío significa "todos".
        $rol = trim((string) $request->string('rol', ''));

        $query = $this->buildBaseQuery($search, $estado, $rol);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $users = $query
            ->with([
                'roles:id,name',
                'createdBy:id,name',
            ])
            ->paginate($perPage)
            ->withQueryString();

        // Listado plano de roles para el filtro segmentado / select del
        // formulario. Lo mandamos sin permisos para no hinchar el payload.
        $rolesCatalog = ClinicAdminScope::rolesQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->map(fn (Role $r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'is_system' => $r->is_system,
            ]);

        return Inertia::render('configuracion/usuarios/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
                'rol' => $rol !== '' ? $rol : null,
            ],
            'stats' => [
                'total' => ClinicAdminScope::usersQuery()->count(),
                'activos' => ClinicAdminScope::usersQuery()->where('is_active', true)->count(),
                'inactivos' => ClinicAdminScope::usersQuery()->where('is_active', false)->count(),
                'coincidencias' => $users->total(),
            ],
            'roles_catalog' => $rolesCatalog,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'tenant_id' => tenant_id(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'documento_tipo' => $data['documento_tipo'] ?? null,
            'documento_numero' => $data['documento_numero'] ?? null,
            'colegiatura' => $data['colegiatura'] ?? null,
            'password' => $data['password'],
            'is_active' => $data['is_active'],
            'created_by_id' => $request->user()?->id,
        ]);

        $this->syncProfessionalFiles($request, $user);

        $user->syncRoles([$data['role']]);

        PlatformSecurityAuditLogger::log(
            action: 'users.created',
            modulo: 'usuarios',
            summary: 'Creó el usuario '.$user->email.' con rol '.$data['role'],
            metadata: [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $data['role'],
                'is_active' => (bool) $user->is_active,
            ],
            subjectType: 'user',
            subjectId: (string) $user->id,
            subjectLabel: $user->email,
        );

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        ClinicAdminScope::assertUserAccessible($user);

        // No permitimos que un usuario se desactive a sí mismo: dejaría
        // su propia sesión zombi y al cerrar quedaría fuera del sistema.
        if ($request->user()?->id === $user->id && $request->boolean('is_active') === false) {
            throw ValidationException::withMessages([
                'is_active' => 'No puedes suspender tu propia cuenta.',
            ]);
        }

        $data = $request->validated();

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'documento_tipo' => $data['documento_tipo'] ?? null,
            'documento_numero' => $data['documento_numero'] ?? null,
            'colegiatura' => $data['colegiatura'] ?? null,
            'is_active' => $data['is_active'],
        ];

        // Solo actualizamos password si vino algo. Pasar `null` haría que
        // el hashed cast intente hashear `null` → error.
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'roles' => $user->getRoleNames()->values()->all(),
        ];

        $user->update($payload);
        $this->syncProfessionalFiles($request, $user);
        $user->syncRoles([$data['role']]);

        PlatformSecurityAuditLogger::log(
            action: 'users.updated',
            modulo: 'usuarios',
            summary: 'Actualizó el usuario '.$user->email
                .(isset($payload['password']) ? ' (incluye cambio de contraseña)' : ''),
            metadata: [
                'user_id' => $user->id,
                'before' => $before,
                'after' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_active' => (bool) $user->is_active,
                    'roles' => [$data['role']],
                    'password_changed' => isset($payload['password']),
                ],
            ],
            subjectType: 'user',
            subjectId: (string) $user->id,
            subjectLabel: $user->email,
        );

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Consulta DNI (RENIEC vía APIPerú / Lucode) para autocompletar el nombre del staff.
     */
    public function consultaDni(Request $request, ApiPeruDniService $apiPeru): JsonResponse
    {
        abort_unless(
            $request->user()?->can('usuarios.create')
            || $request->user()?->can('usuarios.update'),
            403,
        );

        $dni = preg_replace('/\D+/', '', (string) $request->query('dni', ''));
        $request->merge(['dni' => $dni]);

        $validated = $request->validate([
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
        ]);

        return $this->consultaApiPeruResponse(
            fn () => $apiPeru->consultar($validated['dni']),
        );
    }

    private function syncProfessionalFiles(UserRequest $request, User $user): void
    {
        $disk = Storage::disk('public');
        $tenantId = (string) ($user->tenant_id ?? tenant_id() ?? 'central');
        $baseDir = 'tenants/'.$tenantId.'/staff/'.$user->id;

        $map = [
            'cv' => ['path' => 'cv_path', 'remove' => 'remove_cv'],
            'dni_file' => ['path' => 'dni_file_path', 'remove' => 'remove_dni_file'],
            'firma' => ['path' => 'firma_path', 'remove' => 'remove_firma'],
        ];

        $updates = [];

        foreach ($map as $input => $meta) {
            $column = $meta['path'];
            if ($request->boolean($meta['remove'])) {
                if (filled($user->{$column}) && $disk->exists((string) $user->{$column})) {
                    $disk->delete((string) $user->{$column});
                }
                $updates[$column] = null;
            }

            $file = $request->file($input);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if (filled($user->{$column}) && $disk->exists((string) $user->{$column})) {
                $disk->delete((string) $user->{$column});
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filename = $input.'-'.now()->format('YmdHis').'.'.$ext;
            $path = $file->storeAs($baseDir, $filename, 'public');
            $updates[$column] = $path;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        ClinicAdminScope::assertUserAccessible($user);

        if ($request->user()?->id === $user->id) {
            throw ValidationException::withMessages([
                'id' => 'No puedes eliminar tu propia cuenta.',
            ]);
        }

        if ($user->isPlatformSuperadmin()) {
            throw ValidationException::withMessages([
                'id' => 'No se puede eliminar un superadmin desde el panel.',
            ]);
        }

        $snapshot = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $user->getRoleNames()->values()->all(),
        ];

        $user->delete();

        PlatformSecurityAuditLogger::log(
            action: 'users.deleted',
            modulo: 'usuarios',
            summary: 'Eliminó el usuario '.$snapshot['email'],
            metadata: $snapshot,
            subjectType: 'user',
            subjectId: (string) $snapshot['user_id'],
            subjectLabel: $snapshot['email'],
        );

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Eliminación masiva. Filtra automáticamente al usuario actual y a
     * los superadmins para no romper el sistema.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['uuid'],
        ]);

        $currentId = (string) ($request->user()?->id ?? '');

        $deletableIds = ClinicAdminScope::usersQuery()
            ->whereIn('id', $data['ids'])
            ->whereKeyNot($currentId)
            ->pluck('id')
            ->all();

        if (empty($deletableIds)) {
            return back()->with(
                'info',
                'No se eliminaron usuarios: la selección solo incluía cuentas protegidas (sistema, superadmin o tu propia sesión).',
            );
        }

        $deletedUsers = User::query()
            ->whereIn('id', $deletableIds)
            ->get(['id', 'name', 'email']);

        $count = User::whereIn('id', $deletableIds)->delete();
        $skipped = count($data['ids']) - $count;

        PlatformSecurityAuditLogger::log(
            action: 'users.bulk_deleted',
            modulo: 'usuarios',
            summary: 'Borrado masivo de '.$count.' usuario(s)',
            metadata: [
                'deleted_ids' => $deletableIds,
                'deleted_emails' => $deletedUsers->pluck('email')->all(),
                'requested_count' => count($data['ids']),
                'deleted_count' => $count,
            ],
            subjectType: 'user',
            subjectLabel: $deletedUsers->pluck('email')->implode(', '),
        );

        $message = $count === 1
            ? '1 usuario eliminado correctamente.'
            : "{$count} usuarios eliminados correctamente.";

        if ($skipped > 0) {
            $message .= sprintf(
                ' (%d cuenta%s protegida%s se omitieron)',
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

        $rol = trim((string) $request->string('rol', ''));

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search, $estado, $rol)
            ->with([
                'roles:id,name',
                'createdBy:id,name',
            ]);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $filename = 'usuarios-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new UsersXlsxExport;

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
     * @return Builder<User>
     */
    private function buildBaseQuery(string $search, string $estado, string $rol): Builder
    {
        $query = ClinicAdminScope::usersQuery();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activos') {
            $query->where('is_active', true);
        } elseif ($estado === 'inactivos') {
            $query->where('is_active', false);
        }

        if ($rol !== '') {
            $query->whereHas('roles', function ($q) use ($rol) {
                $q->where('name', $rol);
            });
        }

        return $query;
    }
}
