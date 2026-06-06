<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;

/**
 * Indica si ya hay un pago procesado que cubre el período vigente.
 */
final class SubscriptionPaymentCoverage
{
    public function hasCoveringPayment(Subscription $subscription): bool
    {
        $anchor = $subscription->proximo_cobro_at
            ?? $subscription->trial_ends_at
            ?? $subscription->current_period_end
            ?? $subscription->current_period_start;

        if ($anchor === null) {
            return false;
        }

        $coversUpcomingPeriod = $subscription->payments()
            ->where('estado', 'procesado')
            ->whereNotNull('periodo_fin')
            ->where('periodo_fin', '>=', $anchor)
            ->exists();

        if ($coversUpcomingPeriod) {
            return true;
        }

        return $subscription->payments()
            ->where('estado', 'procesado')
            ->where('pagado_at', '>=', $anchor)
            ->exists();
    }
}
