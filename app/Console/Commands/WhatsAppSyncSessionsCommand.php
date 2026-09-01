<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\OpenWaRateLimitedException;
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

        if ($client->isRateLimited()) {
            $this->warn('OpenWA está en cooldown por 429. Se omite esta corrida.');

            return self::SUCCESS;
        }

        try {
            $platform = $platformSync->ensure();
        } catch (OpenWaRateLimitedException $e) {
            $this->error('OpenWA 429 al sync de plataforma. Se espera el cooldown.');

            return self::SUCCESS;
        }

        if ($platform !== null) {
            $this->line(sprintf(
                '  [plataforma] %s → %s (%s)%s',
                $platform->openwa_session_name,
                $platform->status,
                $platform->phone ?? 'sin teléfono',
                $platform->auto_reconnect ? '' : ' [auto-reconnect off]',
            ));
        }

        $limit = max(1, (int) config('openwa.sync_max_tenants_per_run', 8));
        $pauseMs = max(0, (int) config('openwa.sync_pause_ms', 700));

        $synced = 0;
        $ready = 0;

        $tenants = Tenant::query()
            ->whereIn('estado', ['trial', 'active'])
            ->with('whatsappSession')
            ->get()
            ->sortBy(fn (Tenant $tenant): string => (string) ($tenant->whatsappSession?->last_synced_at?->toIso8601String() ?? '1970-01-01'))
            ->values();

        foreach ($tenants as $tenant) {
            if ($synced >= $limit) {
                $this->comment("Lote completo ({$limit} clínicas). El resto rota en la próxima corrida.");
                break;
            }

            if (! $access->allowsAccess($tenant)) {
                continue;
            }

            try {
                $session = $sync->ensureForTenant($tenant);
            } catch (OpenWaRateLimitedException) {
                $this->error('OpenWA 429: se corta el lote para no empeorar el throttling.');
                break;
            }

            if ($session === null) {
                continue;
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

            if ($pauseMs > 0 && $synced < $limit) {
                usleep($pauseMs * 1000);
            }
        }

        $this->info("Sesiones sincronizadas: {$synced}, listas (ready): {$ready}");

        return self::SUCCESS;
    }
}
