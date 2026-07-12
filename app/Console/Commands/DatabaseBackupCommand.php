<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Platform\DatabaseBackupService;
use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'vetsaas:backup-database';

    protected $description = 'Dump PostgreSQL completo + public + cada schema vet_* (backups diarios)';

    public function handle(DatabaseBackupService $backups): int
    {
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

        return self::SUCCESS;
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
