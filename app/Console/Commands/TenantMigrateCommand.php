<?php

namespace App\Console\Commands;

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

        $code = $migrator->migrate(
            $schema,
            $this->output,
            (bool) $this->option('wipe'),
            (bool) $this->option('replay'),
        );

        return $code === TenantSchemaMigrator::EXIT_SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
