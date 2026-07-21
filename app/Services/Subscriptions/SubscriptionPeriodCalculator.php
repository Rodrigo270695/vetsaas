<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Calcula el siguiente período de facturación conservando el ancla del ciclo.
 *
 * Ej.: vence cada día 05:
 * - paga el 01/07: período 05/07 → 05/08;
 * - paga el 06/07: período 05/07 → 05/08;
 * - paga el 10/08 tras más de un mes: período 05/08 → 05/09.
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
            $subscription->ciclo === 'anual' ? 'anual' : 'mensual',
            $paidAt,
        );
    }

    public function nextPeriodEnd(CarbonInterface $periodStart, string $ciclo): CarbonInterface
    {
        return $ciclo === 'anual'
            ? Carbon::parse($periodStart)->addYear()
            : Carbon::parse($periodStart)->addMonth();
    }

    private function advanceAnchorToCurrentPeriod(
        CarbonInterface $anchor,
        string $ciclo,
        CarbonInterface $paidAt,
    ): CarbonInterface {
        $cursor = Carbon::parse($anchor);
        $add = $ciclo === 'anual'
            ? static fn (Carbon $date): Carbon => $date->addYear()
            : static fn (Carbon $date): Carbon => $date->addMonth();

        while (true) {
            $next = $add($cursor->copy());
            if ($next->greaterThan($paidAt)) {
                return $cursor;
            }

            $cursor = $next;
        }
    }
}
