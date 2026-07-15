<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Platform\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Restaura schemas vet_* desde backup local (recuperación).
 *
 * Por defecto SOLO schemas faltantes. Sobrescribir requiere
 * --include-existing y, en production, confirmar escribiendo RESTORE-ALL.
 *
 *   php artisan vetsaas:tenant-restore-all --dry-run
 *   php artisan vetsaas:tenant-restore-all --force
 */
final class TenantRestoreAllCommand extends Command
{
    protected $signature = 'vetsaas:tenant-restore-all
                            {folder? : Carpeta (Y-m-d_His). Si se omite, cada tenant usa la más reciente con dump}
                            {--force : Sin prompt sí/no (en production con --include-existing aún pide RESTORE-ALL)}
                            {--include-existing : También sobrescribe schemas que ya existen}
                            {--dry-run : Solo lista, no restaura}';

    protected $description = 'Restaura schemas vet_* faltantes desde backup (recuperación; no es mantenimiento diario)';

    public function handle(DatabaseBackupService $backups): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Este comando requiere PostgreSQL.');

            return self::FAILURE;
        }

        $folder = $this->argument('folder');
        $folder = is_string($folder) && trim($folder) !== '' ? trim($folder) : null;
        $includeExisting = (bool) $this->option('include-existing');
        $dryRun = (bool) $this->option('dry-run');

        $tenants = Tenant::query()
            ->orderBy('slug')
            ->get(['id', 'slug', 'schema_name', 'razon_social', 'nombre_comercial']);

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants en public.tenants.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        $skip = 0;
        $targets = [];

        foreach ($tenants as $tenant) {
            $schema = (string) $tenant->schema_name;
            if ($schema === '') {
                $this->warn("SKIP {$tenant->slug}: sin schema_name");
                $skip++;

                continue;
            }

            $exists = (bool) DB::selectOne(
                'select exists(select 1 from information_schema.schemata where schema_name = ?) as ok',
                [$schema],
            )?->ok;

            if ($exists && ! $includeExisting) {
                $this->line("OK    {$tenant->slug}  {$schema}  (ya existe, omitido)");
                $skip++;

                continue;
            }

            try {
                $resolved = $backups->resolveTenantDump($schema, $folder);
            } catch (Throwable $e) {
                $this->error("FAIL  {$tenant->slug}  {$schema}  — sin dump: {$e->getMessage()}");
                $fail++;

                continue;
            }

            $targets[] = [
                'slug' => (string) $tenant->slug,
                'schema' => $schema,
                'folder' => $resolved['folder'],
                'dump' => $resolved['dump_path'],
                'exists' => $exists,
            ];
        }

        if ($targets === []) {
            $this->info('Nada que restaurar.');

            return $fail > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Pendientes de restauración: '.count($targets));
        foreach ($targets as $row) {
            $flag = $row['exists'] ? 'OVERWRITE' : 'MISSING';
            $this->line("  [{$flag}] {$row['slug']} ← {$row['folder']}");
        }

        if ($dryRun) {
            $this->info('Dry-run: no se restauró nada.');

            return $fail > 0 ? self::FAILURE : self::SUCCESS;
        }

        if ($includeExisting && app()->isProduction()) {
            $this->error('Vas a SOBRESCRIBIR schemas existentes en PRODUCTION.');
            $typed = (string) $this->ask('Escribe exactamente RESTORE-ALL para continuar');
            if ($typed !== 'RESTORE-ALL') {
                $this->info('Cancelado.');

                return self::SUCCESS;
            }
        } elseif (! $this->option('force') && ! $this->confirm('¿Restaurar todos los listados?', false)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($targets as $row) {
            $this->line("→ {$row['slug']} ({$row['schema']})…");
            try {
                $result = $backups->restoreTenantSchema($row['schema'], $folder ?? $row['folder']);
                $tables = $result['tables'] ?? '?';
                $this->info("  OK  tablas={$tables} ← {$result['dump_path']}");
                $ok++;
            } catch (Throwable $e) {
                $this->error('  FAIL: '.$e->getMessage());
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Listo — restaurados: {$ok} | fallidos: {$fail} | omitidos: {$skip}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
