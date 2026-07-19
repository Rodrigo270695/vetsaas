<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Alias histórico → delega en `vetsaas:reset-tenant-roles`.
 */
final class MigrateRolesToTeamsCommand extends Command
{
    protected $signature = 'vetsaas:migrate-roles-to-teams
                            {--dry-run : Solo lista tenants}
                            {--force : No pedir confirmación}
                            {--slug= : Solo un tenant (ej. clinica-grupo-maclabi)}
                            {--lock-timeout=5s : Timeout de lock Postgres (ej. 3s, 5s)}';

    protected $description = 'Alias de vetsaas:reset-tenant-roles (migra/resetea roles Spatie por tenant)';

    public function handle(): int
    {
        $this->warn('Este comando ahora delega en vetsaas:reset-tenant-roles.');

        return $this->call('vetsaas:reset-tenant-roles', [
            '--dry-run' => (bool) $this->option('dry-run'),
            '--force' => (bool) $this->option('force'),
            '--slug' => $this->option('slug') ?: null,
            '--lock-timeout' => (string) $this->option('lock-timeout'),
        ]);
    }
}
