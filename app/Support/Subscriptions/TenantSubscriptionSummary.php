<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Resumen de suscripción seguro para mostrar al cliente (tenant).
 * No expone tokens ni datos internos de plataforma.
 */
final class TenantSubscriptionSummary
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forTenant(?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        $subscription = $tenant->subscriptions()
            ->with('plan:id,codigo,nombre,badge,color_hex,precio_mensual,precio_anual')
            ->orderByDesc('created_at')
            ->first();

        if ($subscription === null) {
            return self::withoutSubscription($tenant);
        }

        $anchor = SubscriptionExpiry::anchor($subscription, $tenant);
        $daysUntil = SubscriptionExpiry::daysUntil($anchor);
        $renewalUrl = app(SubscriptionRenewalUrl::class)->for($tenant, $subscription);

        $plan = $subscription->plan;

        return [
            'has_subscription' => true,
            'plan' => $plan === null ? null : [
                'nombre' => $plan->nombre,
                'codigo' => $plan->codigo,
                'badge' => $plan->badge,
                'color_hex' => $plan->color_hex,
            ],
            'estado' => $subscription->estado,
            'ciclo' => $subscription->ciclo,
            'precio_pactado' => $subscription->precio_pactado !== null
                ? (string) $subscription->precio_pactado
                : null,
            'trial_ends_at' => self::iso($subscription->trial_ends_at ?? $tenant->trial_ends_at),
            'current_period_start' => self::iso($subscription->current_period_start),
            'current_period_end' => self::iso($subscription->current_period_end),
            'proximo_cobro_at' => self::iso($subscription->proximo_cobro_at),
            'renewal_anchor_at' => self::iso($anchor),
            'renewal_anchor_source' => SubscriptionExpiry::anchorWithSource($subscription, $tenant)[1] ?? 'trial_ends_at',
            'days_until_renewal' => $daysUntil,
            'urgency' => SubscriptionExpiry::urgency($subscription->estado, $daysUntil),
            'renewal_url' => $renewalUrl,
            'bot_ia' => SubscriptionBotIaAddon::payload($subscription),
            'renewal_billing' => SubscriptionRenewalBilling::payload($subscription),
        ];
    }

    /**
     * Payload compacto para el modal de aviso al ingresar (7→0 días).
     *
     * @return array<string, mixed>|null
     */
    public static function renewalAlertForTenant(?Tenant $tenant): ?array
    {
        $summary = self::forTenant($tenant);

        if ($summary === null) {
            return null;
        }

        $daysUntil = $summary['days_until_renewal'];

        if (! is_int($daysUntil) || $daysUntil < 0 || $daysUntil > 7) {
            return null;
        }

        return [
            'days_until_renewal' => $daysUntil,
            'renewal_anchor_at' => $summary['renewal_anchor_at'],
            'urgency' => $summary['urgency'],
            'plan_nombre' => is_array($summary['plan']) ? ($summary['plan']['nombre'] ?? null) : null,
            'renewal_url' => $summary['renewal_url'],
            'subscription_url' => '/configuracion/suscripcion',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function withoutSubscription(Tenant $tenant): array
    {
        $trialEnd = self::toCarbon($tenant->trial_ends_at);
        $daysUntil = SubscriptionExpiry::daysUntil($trialEnd);

        return [
            'has_subscription' => false,
            'plan' => null,
            'estado' => $tenant->estado === 'trial' ? 'trial' : 'unknown',
            'ciclo' => null,
            'precio_pactado' => null,
            'trial_ends_at' => self::iso($trialEnd),
            'current_period_start' => null,
            'current_period_end' => null,
            'proximo_cobro_at' => null,
            'renewal_anchor_at' => self::iso($trialEnd),
            'renewal_anchor_source' => 'trial_ends_at',
            'days_until_renewal' => $daysUntil,
            'urgency' => SubscriptionExpiry::urgency('trial', $daysUntil),
            'renewal_url' => null,
            'bot_ia' => SubscriptionBotIaAddon::payload(null),
            'renewal_billing' => [
                'applies' => false,
                'currency' => 'PEN',
                'plan_amount' => 0,
                'bot_ia_amount' => 0,
                'total_amount' => 0,
                'addons' => [],
            ],
        ];
    }

    private static function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }

    private static function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::toCarbon($value)?->toIso8601String();
    }
}
