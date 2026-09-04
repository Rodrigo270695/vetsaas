<?php

declare(strict_types=1);

namespace App\Support\OpenWa;

/**
 * Eventos OpenWA de mensajes entrantes.
 *
 * La API solo acepta el enum documentado (`message.received`, `session.status`, `*`, …).
 * Nombres viejos como `onMessage` provocan HTTP 400 al registrar el webhook.
 * Laravel igual reconoce esos nombres si algún payload los trae.
 */
final class OpenWaWebhookEvents
{
    /**
     * @return list<string>
     */
    public static function inboundMessageSubscriptions(): array
    {
        return ['message.received'];
    }

    public static function isInboundChat(string $event): bool
    {
        $normalized = strtolower(trim($event));
        if ($normalized === '') {
            return false;
        }

        foreach (['message.sent', 'message.ack', 'message.delivered', 'message.read', 'onack', 'onsent', 'presence', 'typing'] as $noise) {
            if ($normalized === $noise || str_contains($normalized, $noise)) {
                return false;
            }
        }

        return in_array($normalized, ['message.received', 'onmessage', 'message', 'chat'], true)
            || (str_contains($normalized, 'message') && str_contains($normalized, 'receiv'));
    }
}
