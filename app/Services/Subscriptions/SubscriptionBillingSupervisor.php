<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use Carbon\CarbonInterface;

/**
 * Revisa suscripciones vencidas y aplica la máquina de estados de cobro:
 *   trial/active impago → grace → suspended.
 *
 * Diseñado para ejecutarse vía scheduler (p. ej. una vez al día).
 */
class SubscriptionBillingSupervisor
{
    /**
     * @return array{trials_to_grace: int, active_to_grace: int, grace_to_suspended: int}
     */
    public function run(?CarbonInterface $now = null): array
    {
        $now ??= now();

        return [
            'trials_to_grace' => $this->processExpiredTrials($now),
            'active_to_grace' => $this->processOverdueActive($now),
            'grace_to_suspended' => $this->processExpiredGrace($now),
        ];
    }

    private function processExpiredTrials(CarbonInterface $now): int
    {
        $count = 0;
        $graceDays = $this->graceDays();

        Subscription::query()
            ->where('estado', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, $graceDays, &$count): void {
                foreach ($subscriptions as $subscription) {
                    if ($this->hasCoveringPayment($subscription)) {
                        continue;
                    }

                    if ((float) $subscription->precio_pactado <= 0) {
                        $subscription->update([
                            'estado' => 'active',
                            'trial_ends_at' => null,
                            'grace_ends_at' => null,
                        ]);
                        $count++;

                        continue;
                    }

                    $subscription->update([
                        'estado' => 'grace',
                        'grace_ends_at' => $now->copy()->addDays($graceDays),
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    private function processOverdueActive(CarbonInterface $now): int
    {
        $count = 0;
        $graceDays = $this->graceDays();

        Subscription::query()
            ->where('estado', 'active')
            ->where('precio_pactado', '>', 0)
            ->whereNotNull('proximo_cobro_at')
            ->where('proximo_cobro_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, $graceDays, &$count): void {
                foreach ($subscriptions as $subscription) {
                    if ($this->hasCoveringPayment($subscription)) {
                        continue;
                    }

                    $subscription->update([
                        'estado' => 'grace',
                        'grace_ends_at' => $now->copy()->addDays($graceDays),
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    private function processExpiredGrace(CarbonInterface $now): int
    {
        $count = 0;

        Subscription::query()
            ->where('estado', 'grace')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, &$count): void {
                foreach ($subscriptions as $subscription) {
                    if ($this->hasCoveringPayment($subscription)) {
                        $subscription->update([
                            'estado' => 'active',
                            'grace_ends_at' => null,
                        ]);
                        $count++;

                        continue;
                    }

                    $subscription->update([
                        'estado' => 'suspended',
                        'grace_ends_at' => null,
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    private function hasCoveringPayment(Subscription $subscription): bool
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

    private function graceDays(): int
    {
        return max(1, (int) config('billing.grace_days', 7));
    }
}
