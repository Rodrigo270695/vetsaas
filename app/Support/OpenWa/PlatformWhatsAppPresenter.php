<?php

declare(strict_types=1);

namespace App\Support\OpenWa;

use App\Models\PlatformWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\PlatformWhatsAppSessionSync;

final class PlatformWhatsAppPresenter
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly PlatformWhatsAppSessionSync $sync,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(): array
    {
        $enabled = $this->client->isConfigured();

        if (! $enabled) {
            return [
                'enabled' => false,
                'configured' => false,
                'session' => null,
            ];
        }

        $session = PlatformWhatsAppSession::query()
            ->where('openwa_session_name', $this->sync->sessionName())
            ->first();

        return [
            'enabled' => true,
            'configured' => true,
            'session' => $session === null ? null : [
                'id' => $session->id,
                'openwa_session_id' => $session->openwa_session_id,
                'openwa_session_name' => $session->openwa_session_name,
                'status' => $session->status,
                'phone' => $session->phone,
                'push_name' => $session->push_name,
                'connected_at' => $session->connected_at?->toIso8601String(),
                'last_synced_at' => $session->last_synced_at?->toIso8601String(),
                'last_error' => $session->last_error,
                'is_ready' => $session->isReady(),
            ],
        ];
    }
}
