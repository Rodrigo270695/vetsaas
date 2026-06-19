<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;
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

        $anchor = self::billingAnchor($subscription, $tenant);
        $daysUntil = self::daysUntil($anchor);
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
            'renewal_anchor_source' => self::anchorSource($subscription, $tenant),
            'days_until_renewal' => $daysUntil,
            'urgency' => self::urgency($subscription->estado, $daysUntil),
            'renewal_url' => $renewalUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function withoutSubscription(Tenant $tenant): array
    {
        $trialEnd = $tenant->trial_ends_at;
        $daysUntil = self::daysUntil($trialEnd);

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
            'urgency' => self::urgency('trial', $daysUntil),
            'renewal_url' => null,
        ];
    }

    private static function billingAnchor(Subscription $subscription, Tenant $tenant): ?Carbon
    {
        if ($subscription->estado === 'trial') {
            return $subscription->trial_ends_at ?? $tenant->trial_ends_at;
        }

        if ($subscription->proximo_cobro_at !== null) {
            return $subscription->proximo_cobro_at->copy();
        }

        if ($subscription->current_period_end !== null) {
            return $subscription->current_period_end->copy();
        }

        return $subscription->trial_ends_at ?? $tenant->trial_ends_at;
    }

    private static function anchorSource(Subscription $subscription, Tenant $tenant): string
    {
        if ($subscription->estado === 'trial') {
            return 'trial_ends_at';
        }

        if ($subscription->proximo_cobro_at !== null) {
            return 'proximo_cobro_at';
        }

        if ($subscription->current_period_end !== null) {
            return 'current_period_end';
        }

        return 'trial_ends_at';
    }

    private static function daysUntil(?Carbon $anchor): ?int
    {
        if ($anchor === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($anchor->copy()->startOfDay(), false);
    }

    /**
     * @return 'ok'|'warning'|'danger'|'muted'
     */
    private static function urgency(string $estado, ?int $daysUntil): string
    {
        if (in_array($estado, ['suspended', 'cancelled', 'grace'], true)) {
            return 'danger';
        }

        if ($daysUntil === null) {
            return 'muted';
        }

        if ($daysUntil < 0) {
            return 'danger';
        }

        if ($daysUntil <= 7) {
            return 'warning';
        }

        return 'ok';
    }

    private static function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
