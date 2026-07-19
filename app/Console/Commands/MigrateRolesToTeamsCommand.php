<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rbac\MigrateRolesToTeams;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migra roles Spatie globales → un set de roles por tenant (Spatie Teams).
 *
 * Tras `php artisan migrate` (columna tenant_id), ejecutar:
 *   php artisan vetsaas:migrate-roles-to-teams
 *
 * Si la migración de columnas ya invocó el servicio, este comando es idempotente
 * (vuelve a sincronizar roles base por tenant).
 */
final class MigrateRolesToTeamsCommand extends Command
{
    protected $signature = 'vetsaas:migrate-roles-to-teams
                            {--dry-run : Solo cuenta tenants sin escribir}
                            {--force : No pedir confirmación}';

    protected $description = 'Clona roles base por tenant, reasigna usuarios y elimina roles clínicos globales';

    public function handle(MigrateRolesToTeams $migrator): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Este comando requiere PostgreSQL.');

            return self::FAILURE;
        }

        if (! config('permission.teams')) {
            $this->error('config/permission.php debe tener teams=true.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $result = $migrator->run(dryRun: true);
            $this->info('Dry-run: '.$result['tenants'].' tenant(s) a migrar.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Migrar roles a Spatie Teams por tenant?', true)) {
            return self::SUCCESS;
        }

        $result = $migrator->run(dryRun: false);
        $this->info("Listo: {$result['tenants']} tenant(s), {$result['globals_deleted']} roles base globales eliminados.");

        return self::SUCCESS;
    }
}
