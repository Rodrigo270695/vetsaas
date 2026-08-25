<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Platform\DatabaseBackupService;
use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'vetsaas:backup-database
                            {--prune-only : Solo elimina backups locales/remotos fuera de retención}';

    protected $description = 'Dump PostgreSQL completo + public + cada schema vet_* (backups diarios)';

    public function handle(DatabaseBackupService $backups): int
    {
        if ($this->option('prune-only')) {
            return $this->runPruneOnly($backups);
        }

        $this->info('Iniciando backup de base de datos…');

        $result = $backups->run();

        if (! $result['ok']) {
            $this->error($result['error'] ?? 'Backup fallido.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'OK — %d schemas tenant, full=%s, en %ds',
            $result['schema_count'],
            $this->humanBytes($result['full_size_bytes']),
            $result['duration_seconds'],
        ));
        $this->line('Directorio: '.$result['directory']);

        if ($result['remote_enabled'] ?? false) {
            if (($result['remote_ok'] ?? null) === true) {
                $this->info(sprintf(
                    'Remoto OK — %d archivos → %s',
                    $result['remote_files'] ?? 0,
                    $result['remote_path'] ?? '',
                ));
            } else {
                $this->error('Remoto falló: '.($result['remote_error'] ?? 'sin detalle'));

                return self::FAILURE;
            }
        }

        if (isset($result['prune']) && is_array($result['prune'])) {
            $this->reportPrune($result['prune']);
        }

        return self::SUCCESS;
    }

    private function runPruneOnly(DatabaseBackupService $backups): int
    {
        $this->info('Podando backups fuera de retención (local + R2/S3)…');

        $prune = $backups->prune();
        $this->reportPrune($prune);

        if (($prune['remote_error'] ?? null) !== null) {
            $this->error('Error al podar remoto: '.$prune['remote_error']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     retention_days?: int,
     *     local_deleted?: int,
     *     remote_deleted?: int,
     *     remote_skipped?: bool,
     *     remote_error?: string|null
     * }  $prune
     */
    private function reportPrune(array $prune): void
    {
        $this->info(sprintf(
            'Retención %d días — eliminados local=%d, remoto=%d, full.dump remoto=%d%s',
            (int) ($prune['retention_days'] ?? 14),
            (int) ($prune['local_deleted'] ?? 0),
            (int) ($prune['remote_deleted'] ?? 0),
            (int) ($prune['remote_full_deleted'] ?? 0),
            ($prune['remote_skipped'] ?? false) ? ' (remoto omitido)' : '',
        ));
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
