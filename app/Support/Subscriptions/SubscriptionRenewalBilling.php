<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;

/**
 * Importe a cobrar en una renovación: precio pactado del plan + add-ons activos.
 */
final class SubscriptionRenewalBilling
{
    public static function planAmount(Subscription $subscription): float
    {
        return round(max(0, (float) $subscription->precio_pactado), 2);
    }

    public static function botIaAmount(Subscription $subscription): float
    {
        if (! SubscriptionBotIaAddon::isActive($subscription)) {
            return 0.0;
        }

        return round((float) SubscriptionBotIaAddon::precioMensual($subscription), 2);
    }

    public static function totalAmount(Subscription $subscription): float
    {
        return round(self::planAmount($subscription) + self::botIaAmount($subscription), 2);
    }

    public static function isBillable(Subscription $subscription): bool
    {
        return self::totalAmount($subscription) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forTenant(?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        $subscription = $tenant->subscriptions()
            ->with('plan:id,codigo,nombre')
            ->orderByDesc('created_at')
            ->first();

        if ($subscription === null || $subscription->estado === 'cancelled') {
            return null;
        }

        return self::payload($subscription);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(Subscription $subscription): array
    {
        $planAmount = self::planAmount($subscription);
        $botIaAmount = self::botIaAmount($subscription);
        $total = round($planAmount + $botIaAmount, 2);

        $addons = [];
        if ($botIaAmount > 0) {
            $addons[] = [
                'key' => 'bot_ia',
                'label' => 'Asistente IA WhatsApp',
                'amount' => $botIaAmount,
            ];
        }

        return [
            'applies' => $total > 0,
            'currency' => 'PEN',
            'plan_codigo' => $subscription->plan?->codigo,
            'plan_nombre' => $subscription->plan?->nombre,
            'ciclo' => $subscription->ciclo,
            'plan_amount' => $planAmount,
            'bot_ia_active' => SubscriptionBotIaAddon::isActive($subscription),
            'bot_ia_amount' => $botIaAmount,
            'total_amount' => $total,
            'addons' => $addons,
        ];
    }
}
