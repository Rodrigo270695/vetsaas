<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Fecha de vencimiento y semáforo de urgencia a partir de la suscripción viva.
 * Fuente única para Plataforma (Tenants, Suscripciones, Cobros) y resumen del tenant.
 */
final class SubscriptionExpiry
{
    /** @var list<string> */
    public const FILTER_OPTIONS = ['todos', 'por_vencer_7', 'por_vencer_3', 'por_vencer_1', 'vencido'];

    /**
     * @return array{0: ?CarbonInterface, 1: ?string}
     */
    public static function anchorWithSource(Subscription $subscription, ?Tenant $tenant = null): array
    {
        if ($subscription->estado === 'grace' && $subscription->grace_ends_at !== null) {
            return [$subscription->grace_ends_at->copy(), 'grace_ends_at'];
        }

        if ($subscription->estado === 'trial') {
            $anchor = self::toCarbon($subscription->trial_ends_at ?? $tenant?->trial_ends_at);

            return [$anchor, $anchor !== null ? 'trial_ends_at' : null];
        }

        if ($subscription->proximo_cobro_at !== null) {
            return [$subscription->proximo_cobro_at->copy(), 'proximo_cobro_at'];
        }

        if ($subscription->current_period_end !== null) {
            return [$subscription->current_period_end->copy(), 'current_period_end'];
        }

        $fallback = self::toCarbon($subscription->trial_ends_at ?? $tenant?->trial_ends_at);

        return [$fallback, $fallback !== null ? 'trial_ends_at' : null];
    }

    public static function anchor(Subscription $subscription, ?Tenant $tenant = null): ?CarbonInterface
    {
        return self::anchorWithSource($subscription, $tenant)[0];
    }

    public static function daysUntil(?CarbonInterface $anchor): ?int
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
     * ok (>7 días), yellow (4–7), amber (2–3), red (0–1 o vencido),
     * danger (suspendida/cancelada sin fecha), muted (sin fecha).
     *
     * @return 'ok'|'yellow'|'amber'|'red'|'danger'|'muted'
     */
    public static function urgency(string $estado, ?int $daysUntil): string
    {
        if (in_array($estado, ['suspended', 'cancelled'], true)) {
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

    public static function urgencyFor(Subscription $subscription, ?Tenant $tenant = null): string
    {
        $daysUntil = self::daysUntil(self::anchor($subscription, $tenant));

        return self::urgency($subscription->estado, $daysUntil);
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    public static function applyFilter(Builder $query, string $filter, string $table = 'subscriptions'): void
    {
        if (! in_array($filter, self::FILTER_OPTIONS, true) || $filter === 'todos') {
            return;
        }

        $dueAt = self::dueAtSql($table);
        $today = now()->startOfDay()->toDateTimeString();

        match ($filter) {
            'vencido' => $query
                ->whereIn("{$table}.estado", ['trial', 'active', 'grace'])
                ->whereRaw("({$dueAt}) IS NOT NULL")
                ->whereRaw("({$dueAt}) < ?", [$today]),
            'por_vencer_1' => $query
                ->whereIn("{$table}.estado", ['trial', 'active', 'grace'])
                ->whereRaw("({$dueAt}) IS NOT NULL")
                ->whereRaw("({$dueAt}) >= ?", [$today])
                ->whereRaw("({$dueAt}) < ?", [now()->startOfDay()->addDay()->toDateTimeString()]),
            'por_vencer_3' => $query
                ->whereIn("{$table}.estado", ['trial', 'active', 'grace'])
                ->whereRaw("({$dueAt}) IS NOT NULL")
                ->whereRaw("({$dueAt}) >= ?", [$today])
                ->whereRaw("({$dueAt}) < ?", [now()->startOfDay()->addDays(3)->toDateTimeString()]),
            'por_vencer_7' => $query
                ->whereIn("{$table}.estado", ['trial', 'active', 'grace'])
                ->whereRaw("({$dueAt}) IS NOT NULL")
                ->whereRaw("({$dueAt}) >= ?", [$today])
                ->whereRaw("({$dueAt}) < ?", [now()->startOfDay()->addDays(7)->toDateTimeString()]),
            default => null,
        };
    }

    /**
     * Filtra pagos cuyo tenant tiene una suscripción viva que coincide con el umbral.
     *
     * @param  Builder<\App\Models\SubscriptionPayment>  $query
     */
    public static function applyPaymentFilter(Builder $query, string $filter): void
    {
        if (! in_array($filter, self::FILTER_OPTIONS, true) || $filter === 'todos') {
            return;
        }

        $query->whereHas('tenant.subscriptions', function (Builder $subscriptionQuery) use ($filter): void {
            $subscriptionQuery->whereIn('estado', ['trial', 'active', 'grace']);

            self::applyFilter($subscriptionQuery, $filter);
        });
    }

  /**
     * Filtra por plan de la suscripción viva del tenant (no el snapshot del pago).
     *
     * @param  Builder<\App\Models\SubscriptionPayment>  $query
     */
    public static function applyPaymentPlanFilter(Builder $query, string $planId): void
    {
        if ($planId === '') {
            return;
        }

        $query->whereHas('tenant.subscriptions', function (Builder $subscriptionQuery) use ($planId): void {
            $subscriptionQuery
                ->where('plan_id', $planId)
                ->whereIn('estado', ['trial', 'active', 'grace', 'suspended']);
        });
    }

    public static function dueAtSql(string $table = 'subscriptions'): string
    {
        return "CASE
            WHEN {$table}.estado = 'grace' AND {$table}.grace_ends_at IS NOT NULL THEN {$table}.grace_ends_at
            WHEN {$table}.estado = 'trial' THEN {$table}.trial_ends_at
            ELSE COALESCE({$table}.proximo_cobro_at, {$table}.current_period_end)
        END";
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
}
