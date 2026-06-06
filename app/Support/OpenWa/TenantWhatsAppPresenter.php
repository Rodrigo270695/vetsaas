<?php

declare(strict_types=1);

namespace App\Support\OpenWa;

use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;

final class TenantWhatsAppPresenter
{
    public function __construct(
        private readonly OpenWaClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forTenant(?Tenant $tenant): array
    {
        $enabled = $this->client->isConfigured();

        if (! $enabled || ! $tenant instanceof Tenant) {
            return [
                'enabled' => false,
                'configured' => $enabled,
                'session' => null,
            ];
        }

        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
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
