<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use Illuminate\Support\Carbon;

final class TenantWhatsAppSessionSync
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppWebhookRegistrar $webhookRegistrar,
    ) {}

    public function ensureForTenant(Tenant $tenant): ?TenantWhatsAppSession
    {
        if (! $this->client->isConfigured() || ! is_string($tenant->slug) || $tenant->slug === '') {
            return null;
        }

        $local = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        try {
            $remote = $this->client->findSessionByName($tenant->slug)
                ?? $this->client->createSession($tenant->slug);
        } catch (\Throwable $e) {
            if ($local instanceof TenantWhatsAppSession) {
                $local->forceFill([
                    'last_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ])->save();
            }

            return $local;
        }

        $sessionId = (string) ($remote['id'] ?? '');
        if ($sessionId === '') {
            return $local;
        }

        $payload = [
            'tenant_id' => $tenant->id,
            'openwa_session_id' => $sessionId,
            'openwa_session_name' => (string) ($remote['name'] ?? $tenant->slug),
            'status' => (string) ($remote['status'] ?? 'created'),
            'phone' => isset($remote['phone']) ? (string) $remote['phone'] : null,
            'push_name' => isset($remote['pushName']) ? (string) $remote['pushName'] : null,
            'connected_at' => filled($remote['connectedAt'] ?? null)
                ? Carbon::parse($remote['connectedAt'])
                : null,
            'last_synced_at' => now(),
            'last_error' => null,
        ];

        if ($local instanceof TenantWhatsAppSession) {
            $local->forceFill($payload)->save();

            return $local->fresh();
        }

        return TenantWhatsAppSession::query()->create($payload);
    }

    public function refresh(TenantWhatsAppSession $session): TenantWhatsAppSession
    {
        $remote = $this->client->getSession($session->openwa_session_id);

        $session->forceFill([
            'status' => (string) ($remote['status'] ?? $session->status),
            'phone' => isset($remote['phone']) ? (string) $remote['phone'] : $session->phone,
            'push_name' => isset($remote['pushName']) ? (string) $remote['pushName'] : $session->push_name,
            'connected_at' => filled($remote['connectedAt'] ?? null)
                ? Carbon::parse($remote['connectedAt'])
                : $session->connected_at,
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();

        $session = $session->fresh();

        if ($session->isReady()) {
            $this->webhookRegistrar->ensureForSession($session);
        }

        return $session;
    }

    public function disconnect(TenantWhatsAppSession $session): TenantWhatsAppSession
    {
        try {
            $this->client->stopSession($session->openwa_session_id);
            $remote = $this->client->getSession($session->openwa_session_id);

            $session->forceFill([
                'status' => (string) ($remote['status'] ?? 'disconnected'),
                'phone' => null,
                'push_name' => null,
                'connected_at' => null,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $session->forceFill([
                'last_error' => $e->getMessage(),
                'last_synced_at' => now(),
            ])->save();

            throw $e;
        }

        return $session->fresh();
    }
}
