<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Repara / migra roles Spatie a teams por tenant.
 * Imprime progreso por clínica y usa lock_timeout para no colgarse eterno.
 */
final class MigrateRolesToTeamsCommand extends Command
{
    protected $signature = 'vetsaas:migrate-roles-to-teams
                            {--dry-run : Solo lista tenants}
                            {--force : No pedir confirmación}
                            {--slug= : Solo un tenant (ej. clinica-grupo-maclabi)}
                            {--lock-timeout=3s : Timeout de lock Postgres (ej. 3s, 5s)}';

    protected $description = 'Clona roles base por tenant, reasigna usuarios y elimina roles clínicos globales';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Este comando requiere PostgreSQL.');

            return self::FAILURE;
        }

        if (! config('permission.teams')) {
            $this->error('config/permission.php debe tener teams=true.');

            return self::FAILURE;
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'tenant_id');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRoles = config('permission.table_names.model_has_roles', 'model_has_roles');

        if (! \Illuminate\Support\Facades\Schema::hasColumn($rolesTable, $teamKey)) {
            $this->error("Falta {$rolesTable}.{$teamKey}. Corre migrate primero.");

            return self::FAILURE;
        }

        $query = Tenant::query()->orderBy('slug');
        if ($this->option('slug')) {
            $query->where('slug', (string) $this->option('slug'));
        }
        $tenants = $query->get(['id', 'slug', 'nombre_comercial']);

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants que coincidan.');

            return self::SUCCESS;
        }

        $this->info('Tenants: '.$tenants->count());
        if ($this->option('dry-run')) {
            foreach ($tenants as $t) {
                $this->line('  - '.$t->slug);
            }

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Continuar?', true)) {
            return self::SUCCESS;
        }

        $lockTimeout = (string) $this->option('lock-timeout');
        DB::statement('SET lock_timeout = '.DB::getPdo()->quote($lockTimeout));
        $this->line("lock_timeout={$lockTimeout}");

        $seeder = new TenantRolesSeeder;
        $seeder->setCommand($this);
        $baseNames = Role::BASE_CLINIC_ROLES;

        $globalTemplates = Role::query()
            ->whereNull($teamKey)
            ->whereIn('name', $baseNames)
            ->with('permissions:id,name')
            ->get()
            ->keyBy('name');

        $ok = 0;
        $fail = 0;

        foreach ($tenants as $i => $tenant) {
            $n = $i + 1;
            $tenantId = (string) $tenant->id;
            $this->line("[{$n}/{$tenants->count()}] {$tenant->slug} …");

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

                    $names = Role::query()->whereIn('id', $roleIds)->pluck('name')->all();
                    $names = array_values(array_filter($names, static fn (string $n): bool => $n !== 'superadmin'));
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

                $this->info("  ✓ {$users->count()} usuario(s)");
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $this->error('  ✗ '.$e->getMessage());
                if (str_contains($e->getMessage(), 'lock') || str_contains($e->getMessage(), '55P03')) {
                    $this->warn('  → Hay locks. php artisan down && systemctl reload php8.3-fpm && reintenta.');
                }
            } finally {
                setPermissionsTeamId($previousTeam);
            }
        }

        if ($this->option('slug') === null || $this->option('slug') === '') {
            $this->line('Limpiando roles base globales…');
            try {
                $deleted = Role::query()->whereNull($teamKey)->whereIn('name', $baseNames)->delete();
                $this->info("  globales eliminados: {$deleted}");
            } catch (\Throwable $e) {
                $this->warn('  no se pudieron borrar globales aún: '.$e->getMessage());
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info("Fin. ok={$ok} fail={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
