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

    public static function isClinicContext(): bool
    {
        return tenant_id() !== null;
    }

    /**
     * @return list<string>
     */
    public static function hiddenRoleNames(): array
    {
        return Role::SYSTEM_ROLES;
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

        if (self::isClinicContext()) {
            $query->whereNotIn('name', self::hiddenRoleNames());
        }

        return $query;
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

        return true;
    }

    /**
     * Permisos que el usuario autenticado puede ver/asignar en roles (clínica).
     *
     * @return list<string>
     */
    public static function assignablePermissionNamesFor(?User $editor): array
    {
        if ($editor === null) {
            return [];
        }

        $names = $editor->getAllPermissions()->pluck('name')->all();

        if (! self::isClinicContext()) {
            return $names;
        }

        return array_values(array_filter(
            $names,
            static fn (string $name): bool => self::isTenantAssignablePermission($name),
        ));
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
