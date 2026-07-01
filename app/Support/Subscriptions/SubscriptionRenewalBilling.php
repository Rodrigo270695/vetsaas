<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Plan\ComprobantesQuota;

/**
 * Importe a cobrar en una renovación: precio pactado del plan + add-ons activos
 * + excedente de comprobantes del período (planes mensuales).
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

    public static function comprobantesOverageAmount(?Tenant $tenant): float
    {
        if ($tenant === null) {
            return 0.0;
        }

        $overage = ComprobantesQuota::renewalOverage($tenant);

        if (! ($overage['applies'] ?? false)) {
            return 0.0;
        }

        return round((float) ($overage['overage_cost'] ?? 0), 2);
    }

    public static function totalAmount(Subscription $subscription, ?Tenant $tenant = null): float
    {
        $tenant = self::resolveTenant($subscription, $tenant);

        return round(
            self::planAmount($subscription)
            + self::botIaAmount($subscription)
            + self::comprobantesOverageAmount($tenant),
            2,
        );
    }

    public static function isBillable(Subscription $subscription, ?Tenant $tenant = null): bool
    {
        return self::totalAmount($subscription, $tenant) > 0;
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

        return self::payload($subscription, $tenant);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(Subscription $subscription, ?Tenant $tenant = null): array
    {
        $tenant = self::resolveTenant($subscription, $tenant);

        $planAmount = self::planAmount($subscription);
        $botIaAmount = self::botIaAmount($subscription);
        $comprobantesOverage = ComprobantesQuota::renewalOverage($tenant);
        $comprobantesAmount = self::comprobantesOverageAmount($tenant);
        $total = round($planAmount + $botIaAmount + $comprobantesAmount, 2);

        $addons = [];
        if ($botIaAmount > 0) {
            $addons[] = [
                'key' => 'bot_ia',
                'label' => 'Asistente IA WhatsApp (renovación del plan)',
                'amount' => $botIaAmount,
            ];
        }
        if ($comprobantesAmount > 0) {
            $addons[] = [
                'key' => 'comprobantes_overage',
                'label' => (string) ($comprobantesOverage['description'] ?? 'Comprobantes electrónicos adicionales'),
                'amount' => $comprobantesAmount,
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
            'comprobantes_overage_amount' => $comprobantesAmount,
            'comprobantes_overage' => $comprobantesOverage,
            'total_amount' => $total,
            'addons' => $addons,
        ];
    }

    private static function resolveTenant(Subscription $subscription, ?Tenant $tenant = null): ?Tenant
    {
        if ($tenant !== null) {
            return $tenant;
        }

        if ($subscription->relationLoaded('tenant')) {
            return $subscription->tenant;
        }

        return Tenant::query()->find($subscription->tenant_id);
    }
}
