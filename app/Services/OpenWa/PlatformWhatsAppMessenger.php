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
    ) {}

    public function isReady(): bool
    {
        if (! $this->client->isConfigured()) {
            return false;
        }

        $sessionName = trim((string) config('openwa.platform_session_name', ''));
        if ($sessionName === '') {
            return false;
        }

        try {
            $remote = $this->client->findSessionByName($sessionName);

            return is_array($remote)
                && ($remote['status'] ?? null) === 'ready'
                && filled($remote['id'] ?? null);
        } catch (\Throwable) {
            return false;
        }
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
        $sessionName = trim((string) config('openwa.platform_session_name', ''));
        if ($sessionName === '') {
            throw new RuntimeException('OPENWA_PLATFORM_SESSION_NAME no configurada.');
        }

        $remote = $this->client->findSessionByName($sessionName);
        if (! is_array($remote)) {
            throw new RuntimeException('Sesión OpenWA de plataforma no encontrada: '.$sessionName);
        }

        if (($remote['status'] ?? null) !== 'ready') {
            throw new RuntimeException('Sesión OpenWA de plataforma no está conectada (status: '.($remote['status'] ?? 'unknown').').');
        }

        $sessionId = (string) ($remote['id'] ?? '');
        if ($sessionId === '') {
            throw new RuntimeException('Sesión OpenWA de plataforma sin id.');
        }

        return $sessionId;
    }
}
