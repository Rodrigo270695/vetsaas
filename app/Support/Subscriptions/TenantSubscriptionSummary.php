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
            return self::toCarbon($subscription->trial_ends_at ?? $tenant->trial_ends_at);
        }

        if ($subscription->proximo_cobro_at !== null) {
            return self::toCarbon($subscription->proximo_cobro_at);
        }

        if ($subscription->current_period_end !== null) {
            return self::toCarbon($subscription->current_period_end);
        }

        return self::toCarbon($subscription->trial_ends_at ?? $tenant->trial_ends_at);
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

    private static function daysUntil(?CarbonInterface $anchor): ?int
    {
        if ($anchor === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            Carbon::instance($anchor)->startOfDay(),
            false,
        );
    }

    /**
     * ok = verde (>7 días), yellow = 4–7, amber = 2–3, red = 0–1 o vencido,
     * danger = suspendida/cancelada/gracia, muted = sin fecha.
     *
     * @return 'ok'|'yellow'|'amber'|'red'|'danger'|'muted'
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
            return 'red';
        }

        if ($daysUntil <= 1) {
            return 'red';
        }

        if ($daysUntil <= 3) {
            return 'amber';
        }

        if ($daysUntil <= 7) {
            return 'yellow';
        }

        return 'ok';
    }

    private static function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::toCarbon($value)?->toIso8601String();
    }
}
