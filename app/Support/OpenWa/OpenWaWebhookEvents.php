<?php

declare(strict_types=1);

namespace App\Support\OpenWa;

/**
 * Eventos OpenWA que deben disparar el webhook de mensajes entrantes.
 *
 * El panel y forks distintos usan `message.received`, `onMessage` o `message`.
 * Si el webhook solo se suscribe a uno, Laravel nunca recibe el SI/NO.
 */
final class OpenWaWebhookEvents
{
    /**
     * @return list<string>
     */
    public static function inboundMessageSubscriptions(): array
    {
        return ['message.received', 'onMessage', 'message'];
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
