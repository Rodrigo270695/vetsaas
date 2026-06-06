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
            ?? $subscription->current_period_start;

        if ($anchor === null) {
            return false;
        }

        return $subscription->payments()
            ->where('estado', 'procesado')
            ->where('pagado_at', '>=', $anchor)
            ->exists();
    }
}
