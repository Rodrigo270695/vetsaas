<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Platform\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Restaura el schema PostgreSQL de un tenant desde un dump local de backup.
 *
 * Uso:
 *   php artisan vetsaas:tenant-restore mi-clinica
 *   php artisan vetsaas:tenant-restore mi-clinica 2026-07-12_020015 --force
 */
class TenantRestoreCommand extends Command
{
    protected $signature = 'vetsaas:tenant-restore
                            {slug : Slug del tenant (o schema vet_*)}
                            {folder? : Carpeta de backup (Y-m-d_His). Si se omite, usa la más reciente con dump del schema}
                            {--force : Confirma la restauración destructiva}';

    protected $description = 'Restaura el schema de un tenant desde un dump local (pg_restore)';

    public function handle(DatabaseBackupService $backups): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Este comando requiere PostgreSQL.');

            return self::FAILURE;
        }

        $input = strtolower(trim((string) $this->argument('slug')));
        $prefix = (string) config('tenant.schema_prefix', 'vet_');

        $tenant = Tenant::query()->where('slug', $input)->first();
        $schema = $tenant?->schema_name;

        if ($schema === null && str_starts_with($input, $prefix)) {
            $schema = $input;
            $tenant = Tenant::query()->where('schema_name', $schema)->first();
        }

        if ($schema === null || $schema === '') {
            $this->error("No se resolvió schema para: {$input}");

            return self::FAILURE;
        }

        if (! str_starts_with($schema, $prefix)) {
            $this->error("Schema denegado ({$schema}): solo se restauran schemas {$prefix}*.");

            return self::FAILURE;
        }

        $folder = $this->argument('folder');
        $folder = is_string($folder) && trim($folder) !== '' ? trim($folder) : null;

        try {
            $resolved = $backups->resolveTenantDump($schema, $folder);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->warn('Esto reemplazará el schema actual con el dump seleccionado.');
        $this->line('  tenant: '.($tenant?->razon_social ?? '(sin registro central)'));
        $this->line("  slug: ".($tenant?->slug ?? $input));
        $this->line("  schema: {$schema}");
        $this->line("  carpeta: {$resolved['folder']}");
        $this->line("  dump: {$resolved['dump_path']}");

        if (! $this->option('force') && ! $this->confirm('¿Continuar con la restauración?', false)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        try {
            $result = $backups->restoreTenantSchema($schema, $folder ?? $resolved['folder']);
        } catch (Throwable $e) {
            $this->error('Restauración fallida: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("OK — schema {$result['schema']} restaurado.");
        $this->line("  desde: {$result['dump_path']}");

        if ($result['safety_dump'] !== null) {
            $this->line("  safety dump (pre-restore): {$result['safety_dump']}");
        }

        return self::SUCCESS;
    }
}
