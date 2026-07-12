<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Platform\DatabaseBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunDatabaseBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function handle(DatabaseBackupService $backups): void
    {
        $result = $backups->run();

        if (! $result['ok']) {
            throw new \RuntimeException($result['error'] ?? 'Backup fallido');
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('RunDatabaseBackupJob falló', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
