<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Support\OpenWa\OpenWaReconnectPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class TenantWhatsAppSessionSync
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppWebhookRegistrar $webhookRegistrar,
    ) {}

    /**
     * @param  bool  $wakeForLink  true = el usuario pidió Conectar/QR: arrancar aunque esté `created`.
     *                             false = cron/envío: solo despertar si ya hubo vínculo (phone) o está caída.
     */
    public function ensureForTenant(Tenant $tenant, bool $wakeForLink = false): ?TenantWhatsAppSession
    {
        if (! $this->client->isConfigured() || ! is_string($tenant->slug) || $tenant->slug === '') {
            return null;
        }

        if (! $tenant->qualifiesForPaidWhatsApp()) {
            $local = TenantWhatsAppSession::query()
                ->where('tenant_id', $tenant->id)
                ->first();
            if ($local instanceof TenantWhatsAppSession && $local->auto_reconnect) {
                $local->forceFill([
                    'auto_reconnect' => false,
                    'last_synced_at' => now(),
                ])->save();
            }

            return $local;
        }

        $local = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        try {
            $knownId = trim((string) ($local?->openwa_session_id ?? ''));
            $remote = $knownId !== '' ? $this->client->tryGetSession($knownId) : null;

            if ($remote === null && $wakeForLink) {
                $remote = $this->client->createSession($tenant->slug);
            }
        } catch (OpenWaRateLimitedException $e) {
            if ($local instanceof TenantWhatsAppSession) {
                $local->forceFill([
                    'last_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ])->save();
            }

            throw $e;
        } catch (\Throwable $e) {
            if ($local instanceof TenantWhatsAppSession) {
                $local->forceFill([
                    'last_error' => $e->getMessage(),
                    'last_synced_at' => now(),
                ])->save();
            }

            return $local;
        }

        if ($remote === null) {
            return $local;
        }

        $sessionId = (string) ($remote['id'] ?? '');
        if ($sessionId === '') {
            return $local;
        }

        $status = (string) ($remote['status'] ?? 'created');
        $wantsReconnect = $local === null || (bool) ($local->auto_reconnect ?? true);
        $lastError = null;
        $hadPhone = filled($remote['phone'] ?? null) || filled($local?->phone);

        $shouldStart = OpenWaReconnectPolicy::shouldStartEngine(
            $status,
            $wantsReconnect,
            $wakeForLink,
            $hadPhone,
        );

        if ($shouldStart) {
            $reconnect = $this->client->tryStartIfDown($sessionId, $status);
            if ($reconnect['remote'] !== null) {
                $remote = $reconnect['remote'];
                $status = (string) ($remote['status'] ?? $status);
            } elseif ($reconnect['attempted'] && is_string($reconnect['error']) && $reconnect['error'] !== '') {
                $lastError = $reconnect['error'];
            }
            $this->client->forgetSessionListCache();
        }

        $payload = [
            'tenant_id' => $tenant->id,
            'openwa_session_id' => $sessionId,
            'openwa_session_name' => (string) ($remote['name'] ?? $tenant->slug),
            'status' => $status,
            'phone' => isset($remote['phone']) ? (string) $remote['phone'] : null,
            'push_name' => isset($remote['pushName']) ? (string) $remote['pushName'] : null,
            'connected_at' => filled($remote['connectedAt'] ?? null)
                ? Carbon::parse($remote['connectedAt'])
                : null,
            'last_synced_at' => now(),
            'last_error' => $lastError,
        ];

        if ($local instanceof TenantWhatsAppSession) {
            $local->forceFill($payload)->save();
            $session = $local->fresh();
        } else {
            $payload['auto_reconnect'] = true;
            $session = TenantWhatsAppSession::query()->create($payload);
        }

        if ($session instanceof TenantWhatsAppSession && $session->isReady()) {
            try {
                $this->webhookRegistrar->ensureForSession($session);
            } catch (\Throwable $e) {
                Log::warning('OpenWA tenant webhook ensure failed after sync', [
                    'tenant' => $tenant->slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $session;
    }

    /**
     * Al enviar: si la sesión está caída pero hay auth (teléfono) y auto-reconnect,
     * intenta start sin pedir QR. No usa el cupo del cron.
     */
    public function ensureReadyForSend(Tenant $tenant): ?TenantWhatsAppSession
    {
        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($session === null) {
            if (! $tenant->qualifiesForPaidWhatsApp()) {
                return null;
            }
            $session = $this->ensureForTenant($tenant);
        }

        if (! $session instanceof TenantWhatsAppSession) {
            return null;
        }

        if (! ($session->isReady() && $session->isSyncedRecently(2))) {
            $session = $this->pullRemoteStatus($session);
        }

        if ($session->isReady() && $session->isSyncedRecently(2)) {
            return $session;
        }

        $wantsReconnect = (bool) ($session->auto_reconnect ?? true);
        $sessionId = trim((string) $session->openwa_session_id);
        $hadPhone = filled($session->phone);
        $status = (string) $session->status;

        if (
            $sessionId !== ''
            && OpenWaReconnectPolicy::shouldStartEngine($status, $wantsReconnect, false, $hadPhone)
        ) {
            $this->client->tryStartIfDown($sessionId, $status);
        }

        try {
            $session = $this->refresh($session);
        } catch (\Throwable) {
            if ($wantsReconnect) {
                $session = $this->ensureForTenant($tenant) ?? $session;
            }
        }

        if (! $session->isReady() && $wantsReconnect) {
            $session = $this->ensureForTenant($tenant) ?? $session;
        }

        return $session instanceof TenantWhatsAppSession
            && $session->isReady()
            && $session->isSyncedRecently(5)
            ? $session
            : null;
    }

    /**
     * GET a OpenWA y actualiza la fila. No dispara start.
     */
    public function pullRemoteStatus(TenantWhatsAppSession $session): TenantWhatsAppSession
    {
        $id = trim((string) $session->openwa_session_id);
        if ($id === '') {
            return $session;
        }

        $remote = $this->client->tryGetSession($id);
        if (! is_array($remote)) {
            return $session;
        }

        return $this->fillFromRemote($session, $remote);
    }

    public function refresh(TenantWhatsAppSession $session): TenantWhatsAppSession
    {
        $remote = $this->client->getSession($session->openwa_session_id);

        return $this->fillFromRemote($session, $remote);
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function fillFromRemote(TenantWhatsAppSession $session, array $remote): TenantWhatsAppSession
    {
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
            try {
                $this->webhookRegistrar->ensureForSession($session);
            } catch (\Throwable $e) {
                Log::warning('OpenWA tenant webhook ensure failed after status pull', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $session;
    }

    public function enableAutoReconnect(TenantWhatsAppSession $session): TenantWhatsAppSession
    {
        $session->forceFill(['auto_reconnect' => true])->save();

        return $session->fresh();
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
                'auto_reconnect' => false,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $session->forceFill([
                'auto_reconnect' => false,
                'last_error' => $e->getMessage(),
                'last_synced_at' => now(),
            ])->save();

            throw $e;
        }

        return $session->fresh();
    }
}
