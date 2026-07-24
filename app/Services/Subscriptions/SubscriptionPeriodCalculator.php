<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Support\Subscriptions\SubscriptionCiclo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Calcula el siguiente período de facturación conservando el ancla del ciclo.
 *
 * Ej. ciclo mensual, vence cada día 05:
 * - paga el 01/07: período 05/07 → 05/08;
 * - paga el 06/07: período 05/07 → 05/08.
 *
 * Trimestral / semestral / anual avanzan 3 / 6 / 12 meses desde el ancla.
 */
final class SubscriptionPeriodCalculator
{
    public function nextPeriodStart(Subscription $subscription, ?CarbonInterface $paidAt = null): CarbonInterface
    {
        $paidAt ??= now();

        $periodEnd = $subscription->current_period_end;
        $anchor = $periodEnd instanceof CarbonInterface
            ? $periodEnd
            : ($subscription->current_period_start ?? $paidAt);

        if ($anchor->greaterThan($paidAt)) {
            return $anchor->copy();
        }

        return $this->advanceAnchorToCurrentPeriod(
            $anchor,
            SubscriptionCiclo::normalize($subscription->ciclo),
            $paidAt,
        );
    }

    public function nextPeriodEnd(CarbonInterface $periodStart, string $ciclo): CarbonInterface
    {
        $months = SubscriptionCiclo::months($ciclo);

        return Carbon::parse($periodStart)->addMonthsNoOverflow($months);
    }

    private function advanceAnchorToCurrentPeriod(
        CarbonInterface $anchor,
        string $ciclo,
        CarbonInterface $paidAt,
    ): CarbonInterface {
        $cursor = Carbon::parse($anchor);
        $months = SubscriptionCiclo::months($ciclo);

        while (true) {
            $next = $cursor->copy()->addMonthsNoOverflow($months);
            if ($next->greaterThan($paidAt)) {
                return $cursor;
            }

            $cursor = $next;
        }
    }
}
