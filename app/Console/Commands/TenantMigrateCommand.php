<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantSchemaMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantMigrateCommand extends Command
{
    protected $signature = 'vetsaas:tenant-migrate
                            {schema : Nombre del schema PostgreSQL (ej. vet_a1b2c3)}
                            {--replay : Borra el historial de migraciones tenant en public.migrations y vuelve a ejecutarlas (solo desarrollo / schema nuevo)}
                            {--wipe : DROP SCHEMA CASCADE + recrear vacío y limpiar historial tenant; luego aplica migraciones (útil si el schema quedó a medias)}';

    protected $description = 'Ejecuta las migraciones de database/migrations/tenant en el schema indicado (TENANT_MIGRATION_SCHEMA).';

    public function handle(TenantSchemaMigrator $migrator): int
    {
        $schema = (string) $this->argument('schema');

        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Solo está soportado PostgreSQL para multi-schema tenant.');

            return self::FAILURE;
        }

        $wipe = (bool) $this->option('wipe');
        $replay = (bool) $this->option('replay');

        if (($wipe || $replay) && app()->isProduction() && ! $this->isDemoSchema($schema)) {
            $this->error('En producción --wipe/--replay solo están permitidos en el schema del tenant demo.');
            $this->line('Para otras clínicas usa: php artisan vetsaas:tenant-restore {slug} --force');

            return self::FAILURE;
        }

        $code = $migrator->migrate(
            $schema,
            $this->output,
            $wipe,
            $replay,
        );

        return $code === TenantSchemaMigrator::EXIT_SUCCESS ? self::SUCCESS : self::FAILURE;
    }

    private function isDemoSchema(string $schema): bool
    {
        $demo = Tenant::query()->where('slug', 'demo')->value('schema_name');

        return is_string($demo) && $demo !== '' && $schema === $demo;
    }
}
