<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantSchemaMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantMigrateAllCommand extends Command
{
    protected $signature = 'vetsaas:tenant-migrate-all
                            {--slug= : Filtrar por slug del tenant (ej. paws-care)}
                            {--schema= : Filtrar por nombre exacto del schema PostgreSQL}
                            {--dry-run : Solo listar los schemas que se migrarían}
                            {--stop-on-error : Detener al primer fallo (por defecto se continúa con el resto)}';

    protected $description = 'Ejecuta migraciones tenant pendientes en todos los tenants (o uno filtrado por --slug / --schema).';

    public function handle(TenantSchemaMigrator $migrator): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Solo está soportado PostgreSQL para multi-schema tenant.');

            return self::FAILURE;
        }

        $slug = $this->option('slug') ? (string) $this->option('slug') : null;
        $schemaFilter = $this->option('schema') ? (string) $this->option('schema') : null;

        if ($slug !== null && $slug !== '' && $schemaFilter !== null && $schemaFilter !== '') {
            $this->error('Usa solo uno: --slug o --schema, no ambos.');

            return self::FAILURE;
        }

        $query = Tenant::query()
            ->whereNotNull('schema_name')
            ->where('schema_name', '!=', '')
            ->orderBy('slug');

        if ($slug !== null && $slug !== '') {
            $query->where('slug', $slug);
        }

        if ($schemaFilter !== null && $schemaFilter !== '') {
            $query->where('schema_name', $schemaFilter);
        }

        $tenants = $query->get(['id', 'slug', 'schema_name', 'estado']);

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants que coincidan con el filtro.');

            return self::SUCCESS;
        }

        $this->info('Tenants a procesar: '.$tenants->count());
        foreach ($tenants as $t) {
            $this->line(sprintf('  · %s → schema `%s` (%s)', $t->slug, $t->schema_name, $t->estado));
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no se ejecutaron migraciones.');

            return self::SUCCESS;
        }

        $failures = 0;
        $stopOnError = (bool) $this->option('stop-on-error');

        foreach ($tenants as $tenant) {
            $schema = (string) $tenant->schema_name;
            $this->newLine();
            $this->info('Migrando: '.$tenant->slug.' (`'.$schema.'`)...');

            try {
                $code = $migrator->migrate($schema, $this->output, false, false);
                if ($code !== TenantSchemaMigrator::EXIT_SUCCESS) {
                    $this->error('Fallo (código '.$code.') en schema: '.$schema);
                    $failures++;
                    if ($stopOnError) {
                        return self::FAILURE;
                    }
                }
            } catch (\Throwable $e) {
                $this->error('Excepción en `'.$schema.'`: '.$e->getMessage());
                $failures++;
                if ($stopOnError) {
                    return self::FAILURE;
                }
            }
        }

        $this->newLine();
        if ($failures > 0) {
            $this->error('Terminado con '.$failures.' error(es). Revisa el log de arriba.');

            return self::FAILURE;
        }

        $this->info('Todos los schemas se migraron correctamente.');

        return self::SUCCESS;
    }
}
