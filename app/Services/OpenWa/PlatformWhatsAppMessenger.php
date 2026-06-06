<?php

declare(strict_types=1);

namespace App\Services\OpenWa;

use RuntimeException;

/**
 * Envía WhatsApp desde la sesión OpenWA de plataforma (Orvae / VetSaaS).
 */
final class PlatformWhatsAppMessenger
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly PlatformWhatsAppSessionSync $sync,
    ) {}

    public function isReady(): bool
    {
        if (! $this->client->isConfigured()) {
            return false;
        }

        $session = $this->sync->ensure();

        return $session?->isReady() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function sendText(string $chatId, string $text): array
    {
        $sessionId = $this->resolveReadySessionId();

        return $this->client->sendText($sessionId, $chatId, $text);
    }

    private function resolveReadySessionId(): string
    {
        $session = $this->sync->ensure();
        if ($session === null) {
            throw new RuntimeException('OpenWA no está configurado para plataforma.');
        }

        if (! $session->isReady()) {
            $session = $this->sync->refresh($session);
        }

        if (! $session->isReady()) {
            throw new RuntimeException('Sesión OpenWA de plataforma no está conectada (status: '.$session->status.').');
        }

        $sessionId = trim((string) $session->openwa_session_id);
        if ($sessionId === '') {
            throw new RuntimeException('Sesión OpenWA de plataforma sin id.');
        }

        return $sessionId;
    }
}
