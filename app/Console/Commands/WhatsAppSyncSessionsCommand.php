<?php

namespace App\Console\Commands;

use App\Models\PlatformWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\OpenWaReconnectCoordinator;
use Illuminate\Console\Command;

class WhatsAppSyncSessionsCommand extends Command
{
    protected $signature = 'vetsaas:whatsapp-sync-sessions
                            {--force : Liberar la cadena de reconexión si quedó colgada}';

    protected $description = 'Refresca estados OpenWA y encola reconexión serial de sesiones caídas';

    public function handle(
        OpenWaClient $client,
        OpenWaReconnectCoordinator $coordinator,
    ): int {
        if (! config('openwa.sync_enabled', true)) {
            $this->warn('Sync OpenWA desactivado (OPENWA_SYNC_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! $client->isConfigured()) {
            $this->warn('OpenWA deshabilitado o sin OPENWA_API_KEY.');

            return self::SUCCESS;
        }

        if (! $client->ping()) {
            $this->error('OpenWA no responde (proceso congelado). Se omite el sync para no empeorarlo.');

            return self::SUCCESS;
        }

        if ($client->isRateLimited()) {
            $this->warn('OpenWA está en cooldown por 429. Se omite esta corrida.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('force')) {
            $coordinator->releaseChain();
            $this->comment('Cadena de reconexión liberada (--force).');
        }

        $result = $coordinator->syncAndEnqueue();

        $platformName = trim((string) config('openwa.platform_session_name', 'vetsaas-platform'));
        $platform = $platformName !== ''
            ? PlatformWhatsAppSession::query()->where('openwa_session_name', $platformName)->first()
            : null;

        if ($platform !== null) {
            $this->line(sprintf(
                '  [plataforma] %s → %s (%s)%s',
                $platform->openwa_session_name,
                $platform->status,
                $platform->phone ?? 'sin teléfono',
                $platform->auto_reconnect ? '' : ' [auto-reconnect off]',
            ));
        }

        $this->info(sprintf(
            'Estados refrescados: %d. Caídas encoladas (1 a 1): %d.',
            $result['refreshed'],
            $result['queued'],
        ));

        if ($result['chain_already_running']) {
            $this->comment('Cadena anterior aún en curso: no se lanza otra en paralelo. Las ya conectadas no se tocan.');
        } elseif ($result['queued'] === 0) {
            $this->comment('Nada que reconectar. Las sesiones ready/arrancando no reciben start.');
        } else {
            $stagger = max(0, (int) config('openwa.reconnect_stagger_seconds', 20));
            $this->comment("Worker: un start cada {$stagger}s. Si el job ve la sesión ya conectada, pasa a la siguiente.");
        }

        return self::SUCCESS;
    }
}
