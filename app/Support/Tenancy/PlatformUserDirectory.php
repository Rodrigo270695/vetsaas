<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listado de usuarios en el panel central (sin tenant en el host).
 * Los roles Spatie van por team (`tenant_id`); con team null el eager load queda vacío.
 */
final class PlatformUserDirectory
{
    public static function isCentralList(): bool
    {
        return ! ClinicAdminScope::isClinicContext();
    }

    /**
     * @return list<array{id: string, slug: string, label: string}>
     */
    public static function tenantsCatalog(): array
    {
        if (! self::isCentralList()) {
            return [];
        }

        return Tenant::query()
            ->orderBy('razon_social')
            ->orderBy('slug')
            ->get(['id', 'slug', 'razon_social', 'nombre_comercial'])
            ->map(static function (Tenant $tenant): array {
                $label = trim((string) ($tenant->razon_social ?: $tenant->nombre_comercial ?: $tenant->slug));

                return [
                    'id' => (string) $tenant->id,
                    'slug' => (string) $tenant->slug,
                    'label' => $label !== '' ? $label : (string) $tenant->slug,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Nombres de rol usados en clínicas (para filtro en central).
     *
     * @return list<array{name: string}>
     */
    public static function tenantRolesCatalog(): array
    {
        if (! self::isCentralList()) {
            return [];
        }

        $teamKey = (string) config('permission.column_names.team_foreign_key', 'tenant_id');

        return Role::query()
            ->where('guard_name', 'web')
            ->whereNotNull($teamKey)
            ->whereNotIn('name', ClinicAdminScope::hiddenRoleNames())
            ->orderBy('name')
            ->distinct()
            ->get(['name'])
            ->map(static fn (Role $role): array => ['name' => $role->name])
            ->values()
            ->all();
    }

    /**
     * @param  LengthAwarePaginator<int, User>|EloquentCollection<int, User>  $users
     */
    public static function hydrateRoles(LengthAwarePaginator|EloquentCollection|Collection $users): void
    {
        if (! self::isCentralList()) {
            return;
        }

        $collection = $users instanceof LengthAwarePaginator
            ? $users->getCollection()
            : ($users instanceof EloquentCollection ? $users : collect($users));

        if ($collection->isEmpty()) {
            return;
        }

        $ids = $collection->pluck('id')->filter()->values()->all();
        if ($ids === []) {
            return;
        }

        $pivot = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $rolesTable = (string) config('permission.table_names.roles', 'roles');
        $teamKey = (string) config('permission.column_names.team_foreign_key', 'tenant_id');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');

        $rows = DB::table($pivot.' as mhr')
            ->join($rolesTable.' as r', 'r.id', '=', 'mhr.role_id')
            ->join('users as u', 'u.id', '=', 'mhr.'.$morphKey)
            ->where('mhr.model_type', User::class)
            ->whereIn('mhr.'.$morphKey, $ids)
            ->where(function ($q) use ($teamKey): void {
                $q->whereColumn('mhr.'.$teamKey, 'u.tenant_id')
                    ->orWhere(function ($q2) use ($teamKey): void {
                        $q2->whereNull('mhr.'.$teamKey)->whereNull('u.tenant_id');
                    });
            })
            ->select([
                'u.id as user_id',
                'r.id as role_id',
                'r.name as role_name',
            ])
            ->orderBy('r.name')
            ->get()
            ->groupBy('user_id');

        foreach ($collection as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $assigned = collect($rows->get($user->id, []))->map(static function (object $row): Role {
                $role = new Role();
                $role->id = (int) $row->role_id;
                $role->name = (string) $row->role_name;

                return $role;
            });

            $user->setRelation('roles', $assigned);
        }
    }

    /**
     * @param  Builder<User>  $query
     */
    public static function applyTenantFilter(Builder $query, string $tenantId): void
    {
        if ($tenantId === '' || ! self::isCentralList()) {
            return;
        }

        $query->where('tenant_id', $tenantId);
    }

    /**
     * @param  Builder<User>  $query
     */
    public static function applyRoleFilter(Builder $query, string $rol): void
    {
        if ($rol === '') {
            return;
        }

        if (! self::isCentralList()) {
            $query->whereHas('roles', function (Builder $q) use ($rol): void {
                $q->where('name', $rol);
            });

            return;
        }

        $pivot = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $rolesTable = (string) config('permission.table_names.roles', 'roles');
        $teamKey = (string) config('permission.column_names.team_foreign_key', 'tenant_id');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');

        $query->whereExists(function ($sub) use ($rol, $pivot, $rolesTable, $teamKey, $morphKey): void {
            $sub->select(DB::raw('1'))
                ->from($pivot.' as mhr')
                ->join($rolesTable.' as r', 'r.id', '=', 'mhr.role_id')
                ->whereColumn('mhr.'.$morphKey, 'users.id')
                ->where('mhr.model_type', User::class)
                ->where('r.name', $rol)
                ->where(function ($q) use ($teamKey): void {
                    $q->whereColumn('mhr.'.$teamKey, 'users.tenant_id')
                        ->orWhere(function ($q2) use ($teamKey): void {
                            $q2->whereNull('mhr.'.$teamKey)->whereNull('users.tenant_id');
                        });
                });
        });
    }
}
