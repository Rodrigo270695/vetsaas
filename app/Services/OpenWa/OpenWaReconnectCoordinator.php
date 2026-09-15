<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Jobs\ReconnectOpenWaSessionJob;
use App\Models\PlatformWhatsAppSession;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\Subscriptions\TenantSubscriptionAccess;
use App\Support\OpenWa\OpenWaReconnectPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Refresca estados desde GET /api/sessions (un solo listado) y encola
 * reconexiones seriales. Nunca dispara start masivo.
 *
 * @phpstan-type QueueItem array{kind: 'platform'|'tenant', id: string}
 */
final class OpenWaReconnectCoordinator
{
    public const CHAIN_CACHE_KEY = 'openwa:reconnect-serial';

    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantSubscriptionAccess $access,
    ) {}

    /**
     * @return array{refreshed: int, queued: int, skipped_live: int, chain_already_running: bool}
     */
    public function syncAndEnqueue(): array
    {
        $this->client->forgetSessionListCache();
        $refreshed = $this->refreshAllFromList();
        $queue = $this->pendingQueue();
        $skippedLive = max(0, $refreshed - count($queue));

        if ($queue === []) {
            return [
                'refreshed' => $refreshed,
                'queued' => 0,
                'skipped_live' => $skippedLive,
                'chain_already_running' => false,
            ];
        }

        if (Cache::has(self::CHAIN_CACHE_KEY)) {
            Log::info('OpenWA reconnect chain already running; new downs wait for the next cycle', [
                'pending' => count($queue),
            ]);

            return [
                'refreshed' => $refreshed,
                'queued' => 0,
                'skipped_live' => $skippedLive,
                'chain_already_running' => true,
            ];
        }

        Cache::put(self::CHAIN_CACHE_KEY, [
            'total' => count($queue),
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        ReconnectOpenWaSessionJob::dispatch($queue, 0);

        return [
            'refreshed' => $refreshed,
            'queued' => count($queue),
            'skipped_live' => $skippedLive,
            'chain_already_running' => false,
        ];
    }

    public function releaseChain(): void
    {
        Cache::forget(self::CHAIN_CACHE_KEY);
    }

    public function touchChainTtl(): void
    {
        $payload = Cache::get(self::CHAIN_CACHE_KEY);
        if ($payload === null) {
            $payload = ['total' => 0, 'started_at' => now()->toIso8601String()];
        }

        Cache::put(self::CHAIN_CACHE_KEY, $payload, now()->addHours(2));
    }

    public function refreshAllFromList(): int
    {
        try {
            $list = $this->client->listSessions();
        } catch (OpenWaRateLimitedException $e) {
            Log::warning('OpenWA listSessions 429 during status refresh', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        } catch (\Throwable $e) {
            Log::warning('OpenWA listSessions failed during status refresh', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        $byId = [];
        $byName = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($id !== '') {
                $byId[$id] = $row;
            }
            if ($name !== '') {
                $byName[$name] = $row;
            }
        }

        $updated = 0;

        $tenants = TenantWhatsAppSession::query()->get();
        foreach ($tenants as $session) {
            $remote = $this->matchRemote($session->openwa_session_id, $session->openwa_session_name, $byId, $byName);
            if ($remote === null) {
                continue;
            }
            $this->applyRemote($session, $remote);
            $updated++;
        }

        $platforms = PlatformWhatsAppSession::query()->get();
        foreach ($platforms as $session) {
            $remote = $this->matchRemote($session->openwa_session_id, $session->openwa_session_name, $byId, $byName);
            if ($remote === null) {
                continue;
            }
            $this->applyRemote($session, $remote);
            $updated++;
        }

        return $updated;
    }

    /**
     * @return list<QueueItem>
     */
    public function pendingQueue(): array
    {
        $queue = [];

        $platformName = trim((string) config('openwa.platform_session_name', 'vetsaas-platform'));
        $platform = PlatformWhatsAppSession::query()
            ->when($platformName !== '', fn ($q) => $q->where('openwa_session_name', $platformName))
            ->orderByDesc('updated_at')
            ->first();

        if (
            $platform instanceof PlatformWhatsAppSession
            && OpenWaReconnectPolicy::shouldEnqueueAutoReconnect(
                (string) $platform->status,
                (bool) $platform->auto_reconnect,
                filled($platform->phone),
                trim((string) $platform->openwa_session_id) !== '',
            )
        ) {
            $queue[] = ['kind' => 'platform', 'id' => (string) $platform->id];
        }

        $tenants = Tenant::query()
            ->whereIn('estado', ['trial', 'active'])
            ->with(['whatsappSession', 'subscriptions.plan'])
            ->get()
            ->filter(function (Tenant $tenant): bool {
                if (! $tenant->qualifiesForPaidWhatsApp() || ! $this->access->allowsAccess($tenant)) {
                    return false;
                }

                $session = $tenant->whatsappSession;
                if (! $session instanceof TenantWhatsAppSession) {
                    return false;
                }

                return OpenWaReconnectPolicy::shouldEnqueueAutoReconnect(
                    (string) $session->status,
                    (bool) $session->auto_reconnect,
                    filled($session->phone),
                    trim((string) $session->openwa_session_id) !== '',
                );
            })
            ->sortBy(fn (Tenant $tenant): string => (string) (
                $tenant->whatsappSession?->last_synced_at?->toIso8601String() ?? '1970-01-01'
            ))
            ->values();

        foreach ($tenants as $tenant) {
            $queue[] = ['kind' => 'tenant', 'id' => (string) $tenant->id];
        }

        return $queue;
    }

    /**
     * @param  array<string, array<string, mixed>>  $byId
     * @param  array<string, array<string, mixed>>  $byName
     * @return array<string, mixed>|null
     */
    private function matchRemote(
        ?string $sessionId,
        ?string $sessionName,
        array $byId,
        array $byName,
    ): ?array {
        $id = trim((string) $sessionId);
        if ($id !== '' && isset($byId[$id])) {
            return $byId[$id];
        }

        $name = trim((string) $sessionName);
        if ($name !== '' && isset($byName[$name])) {
            return $byName[$name];
        }

        return null;
    }

    /**
     * @param  TenantWhatsAppSession|PlatformWhatsAppSession  $session
     * @param  array<string, mixed>  $remote
     */
    private function applyRemote(TenantWhatsAppSession|PlatformWhatsAppSession $session, array $remote): void
    {
        $status = (string) ($remote['status'] ?? $session->status);
        $sessionId = trim((string) ($remote['id'] ?? $session->openwa_session_id));

        $session->forceFill([
            'openwa_session_id' => $sessionId !== '' ? $sessionId : $session->openwa_session_id,
            'openwa_session_name' => (string) ($remote['name'] ?? $session->openwa_session_name),
            'status' => $status !== '' ? $status : $session->status,
            'phone' => isset($remote['phone']) ? (string) $remote['phone'] : $session->phone,
            'push_name' => isset($remote['pushName']) ? (string) $remote['pushName'] : $session->push_name,
            'connected_at' => filled($remote['connectedAt'] ?? null)
                ? Carbon::parse((string) $remote['connectedAt'])
                : $session->connected_at,
            'last_synced_at' => now(),
        ])->save();
    }
}
