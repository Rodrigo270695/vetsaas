<?php

declare(strict_types=1);

namespace App\Support\Plan;

use App\Models\FelDocument;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Cupo de comprobantes electrónicos del plan (mensual o anual).
 *
 * - El cupo incluido mensual viene de `max_comprobantes_mes` (-1 = ilimitado).
 * - Plan anual: cupo = max_comprobantes_mes × 12 en el período de facturación.
 * - Al superar el cupo se permite seguir emitiendo; cada bloque de 100 extras
 *   suma un cargo adicional (p. ej. S/ 8), aplicable solo en ciclo mensual
 *   según regla comercial acordada (el cálculo se expone en UI).
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

            $ciclo = $subscription?->ciclo ?? 'mensual';
            $included = $unlimited ? null : self::includedLimit($monthlyBase, $ciclo);
            $remaining = $included === null ? null : max(0, $included - $used);

            $usagePct = ($included !== null && $included > 0)
                ? round(min(999.9, ($used / $included) * 100), 1)
                : null;

            $overage = $ciclo === 'mensual'
                ? self::overageBreakdown($used, $included)
                : [
                    'units' => $included !== null ? max(0, $used - $included) : 0,
                    'blocks' => 0,
                    'cost' => 0.0,
                ];

            return [
                'enabled' => self::planTieneFacturacion($plan),
                'unlimited' => $unlimited,
                'used' => $used,
                'included' => $included,
                'remaining' => $remaining,
                'ciclo' => $ciclo,
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
        if ($ciclo === 'anual') {
            return $monthlyBase * 12;
        }

        return $monthlyBase;
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

        if ($subscription?->ciclo === 'anual') {
            return [$now->copy()->startOfYear()->startOfDay(), $now->copy()->endOfYear()->endOfDay()];
        }

        return [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfMonth()->endOfDay()];
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
