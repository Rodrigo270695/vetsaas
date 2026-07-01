<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use App\Models\TenantWhatsAppSession;
use RuntimeException;

/**
 * Envía WhatsApp desde la sesión OpenWA de un tenant (clínica).
 */
final class TenantWhatsAppMessenger
{
    public function __construct(
        private readonly OpenWaClient $client,
    ) {}

    public function sendText(TenantWhatsAppSession $session, string $chatId, string $text): array
    {
        if (! $session->isReady()) {
            throw new RuntimeException('Sesión WhatsApp del tenant no está conectada.');
        }

        $sessionId = trim((string) $session->openwa_session_id);
        if ($sessionId === '') {
            throw new RuntimeException('Sesión WhatsApp sin id OpenWA.');
        }

        return $this->client->sendText($sessionId, $chatId, $text);
    }
}
