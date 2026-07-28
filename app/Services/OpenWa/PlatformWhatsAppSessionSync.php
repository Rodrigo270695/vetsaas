<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Models\PlatformWhatsAppSession;
use Illuminate\Support\Carbon;

final class PlatformWhatsAppSessionSync
{
    public function __construct(
        private readonly OpenWaClient $client,
    ) {}

    public function sessionName(): string
    {
        return trim((string) config('openwa.platform_session_name', 'vetsaas-platform'));
    }

    public function ensure(): ?PlatformWhatsAppSession
    {
        if (! $this->client->isConfigured()) {
            return null;
        }

        $name = $this->sessionName();
        if ($name === '') {
            return null;
        }

        $local = PlatformWhatsAppSession::query()
            ->where('openwa_session_name', $name)
            ->first();

        try {
            $remote = $this->client->findSessionByName($name)
                ?? $this->client->createSession($name);
        } catch (\Throwable $e) {
            if ($local instanceof PlatformWhatsAppSession) {
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

        $status = (string) ($remote['status'] ?? 'created');
        $wantsReconnect = $local === null || (bool) ($local->auto_reconnect ?? true);
        $lastError = null;

        if ($wantsReconnect) {
            $reconnect = $this->client->tryStartIfDown($sessionId, $status);
            if ($reconnect['remote'] !== null) {
                $remote = $reconnect['remote'];
                $status = (string) ($remote['status'] ?? $status);
            } elseif ($reconnect['attempted'] && is_string($reconnect['error']) && $reconnect['error'] !== '') {
                $lastError = $reconnect['error'];
            }
        }

        $payload = [
            'openwa_session_id' => $sessionId,
            'openwa_session_name' => (string) ($remote['name'] ?? $name),
            'status' => $status,
            'phone' => isset($remote['phone']) ? (string) $remote['phone'] : null,
            'push_name' => isset($remote['pushName']) ? (string) $remote['pushName'] : null,
            'connected_at' => filled($remote['connectedAt'] ?? null)
                ? Carbon::parse($remote['connectedAt'])
                : null,
            'last_synced_at' => now(),
            'last_error' => $lastError,
        ];

        if ($local instanceof PlatformWhatsAppSession) {
            $local->forceFill($payload)->save();

            return $local->fresh();
        }

        $payload['auto_reconnect'] = true;

        return PlatformWhatsAppSession::query()->create($payload);
    }

    public function refresh(PlatformWhatsAppSession $session): PlatformWhatsAppSession
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

        return $session->fresh();
    }

    public function enableAutoReconnect(PlatformWhatsAppSession $session): PlatformWhatsAppSession
    {
        $session->forceFill(['auto_reconnect' => true])->save();

        return $session->fresh();
    }

    public function disconnect(PlatformWhatsAppSession $session): PlatformWhatsAppSession
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
