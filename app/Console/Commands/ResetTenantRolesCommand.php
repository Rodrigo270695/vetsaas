<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\SuperadminSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resetea roles base + pivotes de TODOS los tenants (o uno con --slug).
 *
 * Uso en producción tras activar Spatie Teams / 403 masivos:
 *   php artisan down
 *   php artisan vetsaas:reset-tenant-roles --force
 *   php artisan permission:cache-reset
 *   php artisan up
 */
final class ResetTenantRolesCommand extends Command
{
    protected $signature = 'vetsaas:reset-tenant-roles
                            {--dry-run : Solo lista tenants}
                            {--force : No pedir confirmación}
                            {--slug= : Solo un tenant (slug exacto)}
                            {--lock-timeout=5s : Timeout de lock Postgres}
                            {--skip-permission-seed : No re-sembrar catálogo de permisos}
                            {--skip-superadmin : No re-sincronizar superadmin}';

    protected $description = 'Resetea roles base y reasigna permisos de usuarios en todos los tenants';

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

        $teamKey = (string) config('permission.column_names.team_foreign_key', 'tenant_id');
        $rolesTable = (string) config('permission.table_names.roles', 'roles');
        $modelHasRoles = (string) config('permission.table_names.model_has_roles', 'model_has_roles');

        if (! Schema::hasColumn($rolesTable, $teamKey)) {
            $this->error("Falta {$rolesTable}.{$teamKey}. Corre migrate primero.");

            return self::FAILURE;
        }

        $query = Tenant::query()->orderBy('slug');
        if ($this->option('slug')) {
            $query->where('slug', (string) $this->option('slug'));
        }
        $tenants = $query->get(['id', 'slug', 'nombre_comercial', 'razon_social']);

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants que coincidan.');

            return self::SUCCESS;
        }

        $this->info('Tenants a resetear: '.$tenants->count());
        if ($this->option('dry-run')) {
            foreach ($tenants as $t) {
                $label = trim((string) ($t->nombre_comercial ?: $t->razon_social));
                $this->line('  - '.$t->slug.($label !== '' ? " ({$label})" : ''));
            }

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Esto re-sincroniza roles base y reasigna TODOS los usuarios de clínica. ¿Continuar?',
            true,
        )) {
            return self::SUCCESS;
        }

        $lockTimeout = (string) $this->option('lock-timeout');
        DB::statement('SET lock_timeout = '.DB::getPdo()->quote($lockTimeout));
        $this->line("lock_timeout={$lockTimeout}");

        if (! $this->option('skip-permission-seed')) {
            $this->info('Sincronizando catálogo de permisos…');
            $this->callSilent('db:seed', ['--class' => PermissionsSeeder::class, '--force' => true]);
        }

        $seeder = new TenantRolesSeeder;
        $seeder->setCommand($this);
        $baseNames = Role::BASE_CLINIC_ROLES;

        $ok = 0;
        $fail = 0;
        $usersFixed = 0;
        $withoutDashboard = [];

        foreach ($tenants as $i => $tenant) {
            $n = $i + 1;
            $tenantId = (string) $tenant->id;
            $this->line("[{$n}/{$tenants->count()}] {$tenant->slug} …");

            $previousTeam = getPermissionsTeamId();
            setPermissionsTeamId($tenantId);

            try {
                $seeder->seedForTenant($tenantId);

                $availableNames = Role::query()
                    ->where($teamKey, $tenantId)
                    ->where('guard_name', 'web')
                    ->pluck('name')
                    ->all();
                $availableSet = array_flip($availableNames);

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

                    $names = array_values(array_unique(array_filter(
                        $names,
                        static fn (string $name): bool => $name !== 'superadmin' && isset($availableSet[$name]),
                    )));

                    if ($names === []) {
                        // Sin rol válido (p. ej. CASCADE / pivote huérfano) → acceso operativo mínimo.
                        $names = isset($availableSet['admin_clinica'])
                            ? ['admin_clinica']
                            : array_values(array_intersect($baseNames, $availableNames));
                    }

                    if ($names === []) {
                        $this->warn("  ! {$user->email}: sin roles disponibles para asignar");

                        continue;
                    }

                    DB::table($modelHasRoles)
                        ->where('model_type', $user->getMorphClass())
                        ->where('model_id', $user->getKey())
                        ->delete();

                    $user->unsetRelation('roles');
                    $user->unsetRelation('permissions');
                    $user->syncRoles($names);
                    $user->forgetCachedPermissions();
                    $usersFixed++;

                    if (! $user->can('dashboard.view')) {
                        $withoutDashboard[] = $tenant->slug.':'.$user->email;
                    }
                }

                $this->info("  ✓ roles base OK · {$users->count()} usuario(s)");
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $this->error('  ✗ '.$e->getMessage());
                if (str_contains(strtolower($e->getMessage()), 'lock') || str_contains($e->getMessage(), '55P03')) {
                    $this->warn('  → Hay locks. php artisan down && systemctl reload php8.3-fpm && reintenta.');
                }
            } finally {
                setPermissionsTeamId($previousTeam);
            }
        }

        // Roles base globales (tenant_id null) ya no deben existir: rompen el aislamiento.
        if ($this->option('slug') === null || $this->option('slug') === '') {
            $this->line('Eliminando roles base globales (tenant_id null)…');
            try {
                $deleted = Role::query()->whereNull($teamKey)->whereIn('name', $baseNames)->delete();
                $this->info("  eliminados: {$deleted}");
            } catch (\Throwable $e) {
                $this->warn('  no se pudieron borrar aún: '.$e->getMessage());
            }
        }

        if (! $this->option('skip-superadmin')) {
            $this->info('Re-sincronizando superadmin de plataforma…');
            $this->callSilent('db:seed', ['--class' => SuperadminSeeder::class, '--force' => true]);
            $this->ensureSuperadminPivots($teamKey, $modelHasRoles);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info("Fin. tenants_ok={$ok} tenants_fail={$fail} usuarios_reasignados={$usersFixed}");

        if ($withoutDashboard !== []) {
            $this->warn('Usuarios sin dashboard.view tras el reset (revisar roles custom):');
            foreach (array_slice($withoutDashboard, 0, 30) as $row) {
                $this->line('  - '.$row);
            }
            if (count($withoutDashboard) > 30) {
                $this->line('  … y '.(count($withoutDashboard) - 30).' más');
            }
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function ensureSuperadminPivots(string $teamKey, string $modelHasRoles): void
    {
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            $super = Role::query()
                ->whereNull($teamKey)
                ->where('name', 'superadmin')
                ->where('guard_name', 'web')
                ->first();

            if ($super === null) {
                $this->warn('  No existe rol superadmin global.');

                return;
            }

            $idsFromPivot = DB::table($modelHasRoles)
                ->where('role_id', $super->id)
                ->pluck('model_id')
                ->all();

            $platformEmail = config('platform.superadmin.email');
            $hasPlatformEmail = is_string($platformEmail) && $platformEmail !== '';

            if ($idsFromPivot === [] && ! $hasPlatformEmail) {
                $this->warn('  No hay pivotes ni PLATFORM_SUPERADMIN_EMAIL para reasignar.');

                return;
            }

            $superUsers = User::query()
                ->whereNull('tenant_id')
                ->where(function ($q) use ($idsFromPivot, $hasPlatformEmail, $platformEmail): void {
                    if ($idsFromPivot !== []) {
                        $q->whereIn('id', $idsFromPivot);
                    }
                    if ($hasPlatformEmail) {
                        $q->orWhere('email', $platformEmail);
                    }
                })
                ->get();
            foreach ($superUsers as $user) {
                DB::table($modelHasRoles)
                    ->where('model_type', $user->getMorphClass())
                    ->where('model_id', $user->getKey())
                    ->delete();

                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');
                $user->syncRoles([$super]);
                $user->forgetCachedPermissions();
            }

            $this->info('  pivotes superadmin OK ('.$superUsers->count().' usuario(s))');
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }
}
