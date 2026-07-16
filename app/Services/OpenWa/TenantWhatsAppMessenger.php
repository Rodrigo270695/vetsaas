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
        return $this->client->sendText($this->readySessionId($session), $chatId, $text);
    }

    /**
     * Envío tolerante a timeouts/5xx tardíos de OpenWA: asume entrega en vez
     * de fallar. Usar solo para mensajes one-shot disparados por el usuario.
     */
    public function sendTextWithDeliveryFallback(TenantWhatsAppSession $session, string $chatId, string $text): array
    {
        return $this->client->sendTextWithDeliveryFallback($this->readySessionId($session), $chatId, $text);
    }

    private function readySessionId(TenantWhatsAppSession $session): string
    {
        if (! $session->isReady()) {
            throw new RuntimeException('Sesión WhatsApp del tenant no está conectada.');
        }

        $sessionId = trim((string) $session->openwa_session_id);
        if ($sessionId === '') {
            throw new RuntimeException('Sesión WhatsApp sin id OpenWA.');
        }

        return $sessionId;
    }
}
