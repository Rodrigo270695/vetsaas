<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\TenantWhatsAppMessenger;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use RuntimeException;

final class ClinicalHistoryWhatsAppSender
{
    public function __construct(
        private readonly TenantWhatsAppMessenger $messenger,
        private readonly TenantWhatsAppSessionSync $sessionSync,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function send(Tenant $tenant, string $chatId, string $message): array
    {
        $session = $this->resolveReadySession($tenant);

        if ($session === null) {
            throw new RuntimeException('La sesión de WhatsApp de la clínica no está conectada.');
        }

        // Con fallback: si OpenWA tarda en responder (timeout / 5xx tardío)
        // el mensaje normalmente ya salió; evitamos un toast de error falso.
        return $this->messenger->sendTextWithDeliveryFallback($session, $chatId, $message);
    }

    private function resolveReadySession(Tenant $tenant): ?TenantWhatsAppSession
    {
        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($session === null) {
            $session = $this->sessionSync->ensureForTenant($tenant);
        } elseif (! $session->isReady()) {
            $session = $this->sessionSync->refresh($session);
        }

        return $session instanceof TenantWhatsAppSession && $session->isReady()
            ? $session
            : null;
    }
}
