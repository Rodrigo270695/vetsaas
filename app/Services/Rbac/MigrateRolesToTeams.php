<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Clona roles Spatie por tenant y reasigna usuarios (migración a teams).
 */
final class MigrateRolesToTeams
{
    /**
     * @return array{tenants: int, globals_deleted: int}
     */
    public function run(bool $dryRun = false): array
    {
        $teamKey = config('permission.column_names.team_foreign_key', 'tenant_id');
        $modelHasRoles = config('permission.table_names.model_has_roles', 'model_has_roles');
        $rolesTable = config('permission.table_names.roles', 'roles');

        if (! Schema::hasColumn($rolesTable, $teamKey)) {
            throw new \RuntimeException("Falta {$rolesTable}.{$teamKey}");
        }

        $tenants = Tenant::query()->orderBy('slug')->get(['id', 'slug']);
        $baseNames = Role::BASE_CLINIC_ROLES;
        $seeder = new TenantRolesSeeder;

        $globalTemplates = Role::query()
            ->whereNull($teamKey)
            ->whereIn('name', $baseNames)
            ->with('permissions:id,name')
            ->get()
            ->keyBy('name');

        if ($dryRun) {
            return ['tenants' => $tenants->count(), 'globals_deleted' => 0];
        }

        foreach ($tenants as $tenant) {
            $tenantId = (string) $tenant->id;
            $previousTeam = getPermissionsTeamId();
            setPermissionsTeamId($tenantId);

            try {
                $seeder->seedForTenant($tenantId);

                foreach ($baseNames as $roleName) {
                    $template = $globalTemplates->get($roleName);
                    if ($template === null) {
                        continue;
                    }

                    $local = Role::query()
                        ->where($teamKey, $tenantId)
                        ->where('name', $roleName)
                        ->where('guard_name', 'web')
                        ->first();

                    if ($local === null) {
                        continue;
                    }

                    $permNames = $template->permissions->pluck('name')->all();
                    if ($permNames !== []) {
                        $local->syncPermissions($permNames);
                    }
                }

                $users = User::query()->where('tenant_id', $tenantId)->get();
                foreach ($users as $user) {
                    $roleIds = DB::table($modelHasRoles)
                        ->where('model_type', $user->getMorphClass())
                        ->where('model_id', $user->getKey())
                        ->pluck('role_id')
                        ->all();

                    $names = Role::query()
                        ->whereIn('id', $roleIds)
                        ->pluck('name')
                        ->all();

                    $names = array_values(array_filter(
                        $names,
                        static fn (string $n): bool => $n !== 'superadmin',
                    ));

                    if ($names === []) {
                        $names = ['admin_clinica'];
                    }

                    DB::table($modelHasRoles)
                        ->where('model_type', $user->getMorphClass())
                        ->where('model_id', $user->getKey())
                        ->delete();

                    $user->syncRoles($names);
                    $user->forgetCachedPermissions();
                }
            } finally {
                setPermissionsTeamId($previousTeam);
            }
        }

        $this->migrateGlobalCustomRoles($teamKey, $modelHasRoles);

        $deleted = Role::query()
            ->whereNull($teamKey)
            ->whereIn('name', $baseNames)
            ->delete();

        // Asegurar superadmin con tenant_id null.
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);
        try {
            $super = Role::query()
                ->whereNull($teamKey)
                ->where('name', 'superadmin')
                ->where('guard_name', 'web')
                ->first();

            if ($super !== null) {
                $superUsers = User::query()
                    ->whereNull('tenant_id')
                    ->whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))
                    ->get();

                // Re-sync pivots con team null.
                foreach ($superUsers as $user) {
                    DB::table($modelHasRoles)
                        ->where('model_type', $user->getMorphClass())
                        ->where('model_id', $user->getKey())
                        ->delete();
                    $user->syncRoles([$super]);
                    $user->forgetCachedPermissions();
                }
            }
        } finally {
            setPermissionsTeamId($previousTeam);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'tenants' => $tenants->count(),
            'globals_deleted' => $deleted,
        ];
    }

    private function migrateGlobalCustomRoles(string $teamKey, string $modelHasRoles): void
    {
        $customs = Role::query()
            ->whereNull($teamKey)
            ->whereNotIn('name', [...Role::BASE_CLINIC_ROLES, 'superadmin'])
            ->with('permissions:id,name')
            ->get();

        foreach ($customs as $custom) {
            $userIds = DB::table($modelHasRoles)
                ->where('role_id', $custom->id)
                ->pluck('model_id')
                ->unique()
                ->all();

            $tenantIds = User::query()
                ->whereIn('id', $userIds)
                ->whereNotNull('tenant_id')
                ->pluck('tenant_id')
                ->unique()
                ->all();

            foreach ($tenantIds as $tenantId) {
                $tenantId = (string) $tenantId;
                $previousTeam = getPermissionsTeamId();
                setPermissionsTeamId($tenantId);

                try {
                    $local = Role::query()->firstOrCreate(
                        [
                            'name' => $custom->name,
                            'guard_name' => $custom->guard_name,
                            'tenant_id' => $tenantId,
                        ],
                        ['description' => $custom->description],
                    );
                    $local->syncPermissions($custom->permissions->pluck('name')->all());

                    $tenantUsers = User::query()
                        ->where('tenant_id', $tenantId)
                        ->whereIn('id', $userIds)
                        ->get();

                    foreach ($tenantUsers as $user) {
                        if (! $user->hasRole($local->name)) {
                            $user->assignRole($local);
                        }
                    }
                } finally {
                    setPermissionsTeamId($previousTeam);
                }
            }

            try {
                $custom->delete();
            } catch (Throwable) {
                // Puede fallar si aún hay pivotes; se limpia en reasignación.
            }
        }
    }
}
