<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;

/**
 * Add-on opcional: asistente IA por WhatsApp para el tenant.
 * El cobro va acoplado a la renovación del plan (proximo_cobro_at), no en ciclo aparte.
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

    public static function activationWhatsAppUrl(?\App\Models\Tenant $tenant): string
    {
        $digits = preg_replace('/\D/', '', (string) config('bot-ia.activation_whatsapp_phone', '51976809804'));
        $clinic = trim((string) ($tenant?->nombre_comercial ?? 'mi clínica'));
        $precio = self::defaultPrecioMensual();
        $message = sprintf(
            'Hola, soy %s. Quiero activar el Asistente IA de VetSaaS (S/. %s/mes adicional a mi plan).',
            $clinic,
            $precio,
        );

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(?Subscription $subscription): array
    {
        $active = self::isActive($subscription);

        $proximoCobro = $subscription?->proximo_cobro_at ?? $subscription?->current_period_end;

        return [
            'activo' => $active,
            'precio_mensual' => self::precioMensual($subscription),
            'activado_at' => $active && $subscription?->bot_ia_activado_at !== null
                ? $subscription->bot_ia_activado_at->toIso8601String()
                : null,
            'acoplado_al_plan' => true,
            'proximo_cobro_at' => $proximoCobro !== null
                ? $proximoCobro->toIso8601String()
                : null,
        ];
    }
}
