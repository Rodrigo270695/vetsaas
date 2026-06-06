<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Calcula el siguiente período de facturación conservando el ancla del ciclo.
 *
 * Ej.: inicio 05/06 → vence 05/07; si paga el 01/07, el nuevo período es
 * 05/07 → 05/08 (no 01/08).
 */
final class SubscriptionPeriodCalculator
{
    public function nextPeriodStart(Subscription $subscription, ?CarbonInterface $paidAt = null): CarbonInterface
    {
        $paidAt ??= now();

        $periodEnd = $subscription->current_period_end;

        if ($periodEnd instanceof CarbonInterface && $periodEnd->greaterThan($paidAt)) {
            return $periodEnd->copy();
        }

        $anchor = $subscription->current_period_start ?? $paidAt;

        return $this->advanceAnchorToUpcoming($anchor, $subscription->ciclo === 'anual' ? 'anual' : 'mensual', $paidAt);
    }

    public function nextPeriodEnd(CarbonInterface $periodStart, string $ciclo): CarbonInterface
    {
        return $ciclo === 'anual'
            ? Carbon::parse($periodStart)->addYear()
            : Carbon::parse($periodStart)->addMonth();
    }

    private function advanceAnchorToUpcoming(
        CarbonInterface $anchor,
        string $ciclo,
        CarbonInterface $paidAt,
    ): CarbonInterface {
        $cursor = Carbon::parse($anchor);
        $add = $ciclo === 'anual'
            ? static fn (Carbon $date): Carbon => $date->addYear()
            : static fn (Carbon $date): Carbon => $date->addMonth();

        while ($cursor->lessThanOrEqualTo($paidAt)) {
            $cursor = $add($cursor);
        }

        return $cursor;
    }
}
