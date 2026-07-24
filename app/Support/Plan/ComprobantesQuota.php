<?php

declare(strict_types=1);

namespace App\Support\Plan;

use App\Models\FelDocument;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Subscriptions\SubscriptionCiclo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Cupo de comprobantes electrónicos del plan según ciclo de facturación.
 *
 * - Base mensual: `max_comprobantes_mes` vía PlanLimits (incluye extras/regalos).
 * - Incluido del periodo: base × meses(ciclo) — mensual 1, trimestral 3, semestral 6, anual 12.
 * - Al superar el cupo se permite seguir emitiendo; cada bloque de 100 extras
 *   suma un cargo (p. ej. S/ 8) en cualquier ciclo.
 */
final class ComprobantesQuota
{
    public const OVERAGE_BLOCK_SIZE = 100;

    public const OVERAGE_COST_PER_BLOCK = 8.0;

    /**
     * @return array<string, mixed>|null
     */
    public static function forTenant(?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        try {
            $subscription = $tenant->activeSubscription()
                ?? $tenant->subscriptions()->orderByDesc('created_at')->first();

            $plan = $subscription?->plan ?? PlanLimits::activePlan($tenant);

            if ($plan === null) {
                return null;
            }

            $monthlyBase = PlanLimits::intLimit($tenant, 'max_comprobantes_mes');
            $unlimited = $monthlyBase === null;

            [$periodStart, $periodEnd] = self::billingPeriod($subscription);
            $used = self::countEmittedBetween($periodStart, $periodEnd);

            $ciclo = SubscriptionCiclo::normalize($subscription?->ciclo);
            $included = $unlimited ? null : self::includedLimit($monthlyBase, $ciclo);
            $remaining = $included === null ? null : max(0, $included - $used);

            $usagePct = ($included !== null && $included > 0)
                ? round(min(999.9, ($used / $included) * 100), 1)
                : null;

            $overage = self::overageBreakdown($used, $included);

            return [
                'enabled' => self::planTieneFacturacion($plan),
                'unlimited' => $unlimited,
                'used' => $used,
                'included' => $included,
                'remaining' => $remaining,
                'ciclo' => $ciclo,
                'cycle_months' => SubscriptionCiclo::months($ciclo),
                'monthly_base' => $unlimited ? null : $monthlyBase,
                'period_start' => $periodStart->toIso8601String(),
                'period_end' => $periodEnd->toIso8601String(),
                'usage_pct' => $usagePct,
                'semaphore' => self::semaphore($used, $included, $unlimited),
                'allows_overage' => true,
                'overage_units' => $overage['units'],
                'overage_blocks' => $overage['blocks'],
                'overage_cost' => number_format($overage['cost'], 2, '.', ''),
                'overage_block_size' => self::OVERAGE_BLOCK_SIZE,
                'overage_cost_per_block' => number_format(self::OVERAGE_COST_PER_BLOCK, 2, '.', ''),
                'production_only' => true,
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function includedLimit(int $monthlyBase, string $ciclo): int
    {
        return max(0, $monthlyBase) * SubscriptionCiclo::months($ciclo);
    }

    /**
     * @return array{units: int, blocks: int, cost: float}
     */
    public static function overageBreakdown(int $used, ?int $included): array
    {
        if ($included === null || $used <= $included) {
            return ['units' => 0, 'blocks' => 0, 'cost' => 0.0];
        }

        $units = $used - $included;
        $blocks = (int) ceil($units / self::OVERAGE_BLOCK_SIZE);

        return [
            'units' => $units,
            'blocks' => $blocks,
            'cost' => round($blocks * self::OVERAGE_COST_PER_BLOCK, 2),
        ];
    }

    /**
     * @return 'unlimited'|'ok'|'caution'|'warning'|'over'
     */
    public static function semaphore(int $used, ?int $included, bool $unlimited = false): string
    {
        if ($unlimited || $included === null || $included <= 0) {
            return 'unlimited';
        }

        if ($used >= $included) {
            return 'over';
        }

        $pct = ($used / $included) * 100;

        if ($pct >= 90) {
            return 'warning';
        }

        if ($pct >= 75) {
            return 'caution';
        }

        return 'ok';
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function billingPeriod(?Subscription $subscription): array
    {
        $now = now();

        if ($subscription !== null) {
            $start = self::toCarbon($subscription->current_period_start);
            $end = self::toCarbon($subscription->current_period_end);

            if ($start !== null && $end !== null) {
                return [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
            }
        }

        $ciclo = SubscriptionCiclo::normalize($subscription?->ciclo);
        $months = SubscriptionCiclo::months($ciclo);

        if ($months >= 12) {
            return [$now->copy()->startOfYear()->startOfDay(), $now->copy()->endOfYear()->endOfDay()];
        }

        if ($months > 1) {
            $start = $now->copy()->startOfMonth()->startOfDay();

            return [$start, $start->copy()->addMonthsNoOverflow($months)->subDay()->endOfDay()];
        }

        return [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfMonth()->endOfDay()];
    }

    /**
     * Excedente de comprobantes del período en curso (cualquier ciclo).
     *
     * @return array<string, mixed>
     */
    public static function renewalOverage(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return self::emptyRenewalOverage();
        }

        $snapshot = self::forTenant($tenant);

        if ($snapshot === null || ! ($snapshot['enabled'] ?? false)) {
            return self::emptyRenewalOverage();
        }

        $applies = ! ($snapshot['unlimited'] ?? false);
        $cost = $applies ? (float) ($snapshot['overage_cost'] ?? 0) : 0.0;

        return [
            'applies' => $applies && $cost > 0,
            'ciclo' => $snapshot['ciclo'] ?? null,
            'used' => (int) ($snapshot['used'] ?? 0),
            'included' => $snapshot['included'],
            'overage_units' => (int) ($snapshot['overage_units'] ?? 0),
            'overage_blocks' => (int) ($snapshot['overage_blocks'] ?? 0),
            'overage_cost' => number_format($cost, 2, '.', ''),
            'currency' => 'PEN',
            'period_start' => $snapshot['period_start'] ?? null,
            'period_end' => $snapshot['period_end'] ?? null,
            'block_size' => self::OVERAGE_BLOCK_SIZE,
            'cost_per_block' => number_format(self::OVERAGE_COST_PER_BLOCK, 2, '.', ''),
            'description' => $applies && $cost > 0
                ? sprintf(
                    'Comprobantes adicionales (%d bloque(s) × S/. %s)',
                    (int) ($snapshot['overage_blocks'] ?? 0),
                    number_format(self::OVERAGE_COST_PER_BLOCK, 2, '.', ''),
                )
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyRenewalOverage(): array
    {
        return [
            'applies' => false,
            'ciclo' => null,
            'used' => 0,
            'included' => null,
            'overage_units' => 0,
            'overage_blocks' => 0,
            'overage_cost' => '0.00',
            'currency' => 'PEN',
            'period_start' => null,
            'period_end' => null,
            'block_size' => self::OVERAGE_BLOCK_SIZE,
            'cost_per_block' => number_format(self::OVERAGE_COST_PER_BLOCK, 2, '.', ''),
            'description' => null,
        ];
    }

    public static function countEmittedBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        if (! Schema::hasTable('fel_documents')) {
            return 0;
        }

        $query = FelDocument::query()
            ->whereNotNull('emitido_at')
            ->whereBetween('emitido_at', [$start, $end]);

        if (Schema::hasColumn('fel_documents', 'apisunat_mode')) {
            $query->where('apisunat_mode', 'produccion');
        }

        return $query->count();
    }

    private static function planTieneFacturacion(Plan $plan): bool
    {
        $max = $plan->resolveFeature('max_comprobantes_mes');
        $hasLimit = is_int($max) && $max >= 0;

        return (bool) $plan->resolveFeature('boletas_electronicas')
            || (bool) $plan->resolveFeature('facturas_electronicas')
            || $hasLimit;
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
