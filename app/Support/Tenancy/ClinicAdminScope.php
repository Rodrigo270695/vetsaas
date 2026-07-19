<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Alcance del panel Configuración (usuarios / roles) cuando hay tenant activo.
 *
 * En subdominio de clínica:
 *   - Solo usuarios con `tenant_id` del host (nunca superadmin ni staff central).
 *   - Solo roles operativos (sin `superadmin`).
 *   - Catálogo de permisos sin módulos de plataforma ni audit-trail interno.
 */
final class ClinicAdminScope
{
    /** Prefijos de permisos reservados al panel SaaS central. */
    private const PLATFORM_PERMISSION_PREFIXES = [
        'plataforma-',
        'platform-settings',
    ];

    /**
     * Módulos de plataforma (permiso = `modulo` o `modulo.accion`).
     *
     * @var list<string>
     */
    private const PLATFORM_PERMISSION_MODULES = [
        'salesbot-knowledge',
        'bot-ia-announcements',
    ];

    public static function isClinicContext(): bool
    {
        return tenant_id() !== null;
    }

    /**
     * @return list<string>
     */
    public static function hiddenRoleNames(): array
    {
        // Solo plataforma: los roles base de clínica deben seguir visibles
        // y asignables en el tenant. La protección anti-borrado va en Role::is_system.
        return Role::platformOnlyRoleNames();
    }

    /**
     * @return Builder<User>
     */
    public static function usersQuery(): Builder
    {
        $query = User::query();

        if (! self::isClinicContext()) {
            return $query;
        }

        return $query
            ->where('tenant_id', tenant_id())
            ->whereDoesntHave('roles', function (Builder $q): void {
                $q->whereIn('name', self::hiddenRoleNames());
            });
    }

    /**
     * @return Builder<Role>
     */
    public static function rolesQuery(): Builder
    {
        $query = Role::query()->where('guard_name', 'web');
        $teamKey = config('permission.column_names.team_foreign_key', 'tenant_id');

        if (self::isClinicContext()) {
            return $query
                ->where($teamKey, tenant_id())
                ->whereNotIn('name', self::hiddenRoleNames());
        }

        // Panel central: solo roles de plataforma (tenant_id null).
        return $query->whereNull($teamKey);
    }

    public static function isTenantAssignablePermission(string $permissionName): bool
    {
        if (str_starts_with($permissionName, 'audit-trail.')) {
            return false;
        }

        foreach (self::PLATFORM_PERMISSION_PREFIXES as $prefix) {
            if (str_starts_with($permissionName, $prefix)) {
                return false;
            }
        }

        foreach (self::PLATFORM_PERMISSION_MODULES as $module) {
            if ($permissionName === $module || str_starts_with($permissionName, $module.'.')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Permisos que se pueden ver/asignar al gestionar roles.
     *
     * En clínica: catálogo completo de permisos operativos del tenant
     * (sin plataforma). NO se limita a `getAllPermissions()` del editor:
     * si el admin quita un permiso de su propio `admin_clinica`, ese
     * permiso seguiría existiendo en BD y debe poder volver a marcarse.
     * La autorización real es el middleware `roles.update`.
     *
     * En panel central: se limita a lo que el editor ya tiene (superadmin).
     *
     * @return list<string>
     */
    public static function assignablePermissionNamesFor(?User $editor): array
    {
        if ($editor === null) {
            return [];
        }

        if (! self::isClinicContext()) {
            return $editor->getAllPermissions()->pluck('name')->all();
        }

        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->filter(static fn (string $name): bool => self::isTenantAssignablePermission($name))
            ->values()
            ->all();
    }

    /**
     * Catálogo agrupado por módulo, filtrado para el panel de la clínica.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return array<int, array{module: string, permissions: array<int, array{id: int, name: string, action: string}>}>
     */
    public static function groupPermissionsCatalog(Collection $permissions): array
    {
        $catalogOrder = array_keys(PermissionsSeeder::CATALOG);

        $grouped = [];
        foreach ($permissions as $perm) {
            [$module, $action] = self::splitPermission($perm->name);
            $grouped[$module][] = [
                'id' => (int) $perm->id,
                'name' => $perm->name,
                'action' => $action,
            ];
        }

        $output = [];
        foreach ($catalogOrder as $module) {
            if (isset($grouped[$module])) {
                $output[] = [
                    'module' => $module,
                    'permissions' => $grouped[$module],
                ];
                unset($grouped[$module]);
            }
        }

        foreach ($grouped as $module => $items) {
            $output[] = [
                'module' => $module,
                'permissions' => $items,
            ];
        }

        return $output;
    }

    public static function assertUserAccessible(User $user): void
    {
        if (! self::isClinicContext()) {
            return;
        }

        abort_unless($user->belongsToTenant(tenant_id()), 404);

        if ($user->hasRole(self::hiddenRoleNames())) {
            abort(404);
        }
    }

    public static function assertRoleAccessible(Role $role): void
    {
        if (! self::isClinicContext()) {
            return;
        }

        if (in_array($role->name, self::hiddenRoleNames(), true)) {
            abort(404);
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'tenant_id');
        if ((string) ($role->{$teamKey} ?? '') !== (string) tenant_id()) {
            abort(404);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitPermission(string $name): array
    {
        $pos = strrpos($name, '.');

        if ($pos === false) {
            return [$name, ''];
        }

        return [substr($name, 0, $pos), substr($name, $pos + 1)];
    }
}
