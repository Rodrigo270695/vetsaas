<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use App\Services\Subscriptions\TenantSubscriptionAccess;
use Illuminate\Console\Command;

class WhatsAppSyncSessionsCommand extends Command
{
    protected $signature = 'vetsaas:whatsapp-sync-sessions';

    protected $description = 'Crea/sincroniza sesiones OpenWA por tenant (slug = nombre de sesión)';

    public function handle(
        OpenWaClient $client,
        TenantWhatsAppSessionSync $sync,
        TenantSubscriptionAccess $access,
    ): int {
        if (! $client->isConfigured()) {
            $this->warn('OpenWA deshabilitado o sin OPENWA_API_KEY.');

            return self::SUCCESS;
        }

        $synced = 0;
        $ready = 0;

        Tenant::query()
            ->whereIn('estado', ['trial', 'active'])
            ->orderBy('slug')
            ->each(function (Tenant $tenant) use ($sync, $access, &$synced, &$ready): void {
                if (! $access->allowsAccess($tenant)) {
                    return;
                }

                $session = $sync->ensureForTenant($tenant);
                if ($session === null) {
                    return;
                }

                $synced++;
                if ($session->isReady()) {
                    $ready++;
                }

                $this->line(sprintf(
                    '  %s → %s (%s)',
                    $tenant->slug,
                    $session->status,
                    $session->phone ?? 'sin teléfono',
                ));
            });

        $this->info("Sesiones sincronizadas: {$synced}, listas (ready): {$ready}");

        return self::SUCCESS;
    }
}
