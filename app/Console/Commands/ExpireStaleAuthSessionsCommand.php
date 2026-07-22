<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Platform\UserAuthSessionLogger;
use Illuminate\Console\Command;

class ExpireStaleAuthSessionsCommand extends Command
{
    protected $signature = 'vetsaas:auth-sessions-expire-stale';

    protected $description = 'Cierra filas del historial de login cuya cookie Laravel expiró o ya no existe';

    public function handle(UserAuthSessionLogger $logger): int
    {
        $closed = $logger->expireStaleSessions();

        $this->info(sprintf('Sesiones de login marcadas como expiradas: %d', $closed));

        return self::SUCCESS;
    }
}
