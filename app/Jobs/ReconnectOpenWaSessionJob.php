<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformWhatsAppSession;
use App\Models\Tenant;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\OpenWaRateLimitedException;
use App\Services\OpenWa\OpenWaReconnectCoordinator;
use App\Services\OpenWa\PlatformWhatsAppSessionSync;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use App\Services\Subscriptions\TenantSubscriptionAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Un start OpenWA por job. El siguiente se encola con delay para no
 * levantar Chromium en paralelo.
 *
 * @phpstan-type QueueItem array{kind: 'platform'|'tenant', id: string}
 */
final class ReconnectOpenWaSessionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * Lista serial de sesiones a reconectar (no confundir con Queueable::$queue).
     *
     * @param  list<QueueItem>  $targets
     */
    public function __construct(
        public readonly array $targets,
        public readonly int $index = 0,
    ) {}

    public function handle(
        OpenWaClient $client,
        OpenWaReconnectCoordinator $coordinator,
        TenantWhatsAppSessionSync $tenantSync,
        PlatformWhatsAppSessionSync $platformSync,
        TenantSubscriptionAccess $access,
    ): void {
        $item = $this->targets[$this->index] ?? null;
        if (! is_array($item)) {
            $coordinator->releaseChain();

            return;
        }

        if (! $client->isConfigured()) {
            $coordinator->releaseChain();

            return;
        }

        if (! $client->ping() || $client->isRateLimited()) {
            $this->requeueSame($coordinator, $client);

            return;
        }

        try {
            $this->reconnectItem($item, $tenantSync, $platformSync, $access);
        } catch (OpenWaRateLimitedException) {
            $this->requeueSame($coordinator, $client);

            return;
        } catch (Throwable $e) {
            Log::warning('OpenWA reconnect job failed', [
                'kind' => $item['kind'] ?? null,
                'id' => $item['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $next = $this->index + 1;
        if (! isset($this->targets[$next])) {
            $coordinator->releaseChain();

            return;
        }

        $stagger = max(0, (int) config('openwa.reconnect_stagger_seconds', 20));
        self::dispatch($this->targets, $next)->delay(now()->addSeconds($stagger));
    }

    /**
     * @param  QueueItem  $item
     */
    private function reconnectItem(
        array $item,
        TenantWhatsAppSessionSync $tenantSync,
        PlatformWhatsAppSessionSync $platformSync,
        TenantSubscriptionAccess $access,
    ): void {
        if (($item['kind'] ?? '') === 'platform') {
            $session = PlatformWhatsAppSession::query()->find($item['id']);
            if ($session === null) {
                return;
            }
            $platformSync->ensure(wakeForLink: false);

            return;
        }

        $tenant = Tenant::query()
            ->with(['whatsappSession', 'subscriptions.plan'])
            ->find($item['id']);

        if (! $tenant instanceof Tenant || ! $access->allowsAccess($tenant)) {
            return;
        }

        $tenantSync->ensureForTenant($tenant, wakeForLink: false);
    }

    private function requeueSame(OpenWaReconnectCoordinator $coordinator, OpenWaClient $client): void
    {
        $wait = max(
            30,
            (int) config('openwa.rate_limit_cooldown_seconds', 240),
        );

        Log::info('OpenWA reconnect job deferred', [
            'index' => $this->index,
            'wait_seconds' => $wait,
            'rate_limited' => $client->isRateLimited(),
        ]);

        self::dispatch($this->targets, $this->index)->delay(now()->addSeconds($wait));
        $coordinator->touchChainTtl();
    }
}
