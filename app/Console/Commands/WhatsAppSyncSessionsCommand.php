<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\PlatformWhatsAppSessionSync;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use App\Services\Subscriptions\TenantSubscriptionAccess;
use Illuminate\Console\Command;

class WhatsAppSyncSessionsCommand extends Command
{
    protected $signature = 'vetsaas:whatsapp-sync-sessions';

    protected $description = 'Sincroniza sesiones OpenWA y reconecta las caídas (plataforma + tenants)';

    public function handle(
        OpenWaClient $client,
        TenantWhatsAppSessionSync $sync,
        PlatformWhatsAppSessionSync $platformSync,
        TenantSubscriptionAccess $access,
    ): int {
        if (! $client->isConfigured()) {
            $this->warn('OpenWA deshabilitado o sin OPENWA_API_KEY.');

            return self::SUCCESS;
        }

        $platform = $platformSync->ensure();
        if ($platform !== null) {
            $this->line(sprintf(
                '  [plataforma] %s → %s (%s)%s',
                $platform->openwa_session_name,
                $platform->status,
                $platform->phone ?? 'sin teléfono',
                $platform->auto_reconnect ? '' : ' [auto-reconnect off]',
            ));
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
                    '  %s → %s (%s)%s',
                    $tenant->slug,
                    $session->status,
                    $session->phone ?? 'sin teléfono',
                    $session->auto_reconnect ? '' : ' [auto-reconnect off]',
                ));
            });

        $this->info("Sesiones sincronizadas: {$synced}, listas (ready): {$ready}");

        return self::SUCCESS;
    }
}
