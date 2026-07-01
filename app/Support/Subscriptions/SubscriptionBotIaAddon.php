<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;

/**
 * Add-on opcional: asistente IA por WhatsApp para el tenant.
 */
final class SubscriptionBotIaAddon
{
    public static function defaultPrecioMensual(): string
    {
        return number_format((float) config('bot-ia.precio_mensual', 15), 2, '.', '');
    }

    public static function precioMensual(?Subscription $subscription): string
    {
        if ($subscription?->bot_ia_precio_mensual !== null) {
            return (string) $subscription->bot_ia_precio_mensual;
        }

        return self::defaultPrecioMensual();
    }

    public static function isActive(?Subscription $subscription): bool
    {
        return $subscription !== null
            && (bool) $subscription->bot_ia_activo
            && $subscription->estado !== 'cancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(?Subscription $subscription): array
    {
        $active = self::isActive($subscription);

        return [
            'activo' => $active,
            'precio_mensual' => self::precioMensual($subscription),
            'activado_at' => $active && $subscription?->bot_ia_activado_at !== null
                ? $subscription->bot_ia_activado_at->toIso8601String()
                : null,
        ];
    }
}
