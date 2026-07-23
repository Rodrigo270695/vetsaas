<?php

namespace App\Services\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Revisa suscripciones vencidas y aplica la máquina de estados de cobro:
 *   trial/active impago → grace → suspended.
 *
 * Diseñado para ejecutarse vía scheduler (p. ej. una vez al día).
 * El periodo de gracia se activa al vencer el cobro (`proximo_cobro_at`)
 * y dura `billing.grace_days` desde esa fecha ancla (no desde el pago).
 */
class SubscriptionBillingSupervisor
{
    public function __construct(
        private readonly SubscriptionPaymentCoverage $coverage,
    ) {}

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

        Subscription::query()
            ->where('estado', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, &$count): void {
                foreach ($subscriptions as $subscription) {
                    if ($this->hasCoveringPayment($subscription)) {
                        continue;
                    }

                    if ((float) $subscription->precio_pactado <= 0 || $this->isFreePlan($subscription)) {
                        $subscription->update([
                            'estado' => 'active',
                            'trial_ends_at' => null,
                            'grace_ends_at' => null,
                        ]);
                        $count++;

                        continue;
                    }

                    $this->enterGraceOrSuspend(
                        $subscription,
                        $now,
                        $subscription->effectiveGraceDays(),
                        $subscription->trial_ends_at,
                    );
                    $count++;
                }
            });

        return $count;
    }

    private function processOverdueActive(CarbonInterface $now): int
    {
        $count = 0;

        Subscription::query()
            ->where('estado', 'active')
            ->where('precio_pactado', '>', 0)
            ->whereNotNull('proximo_cobro_at')
            ->where('proximo_cobro_at', '<=', $now)
            ->whereHas('plan', fn ($q) => $q->excludingFree())
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, &$count): void {
                foreach ($subscriptions as $subscription) {
                    if ($this->hasCoveringPayment($subscription)) {
                        continue;
                    }

                    $anchor = $subscription->proximo_cobro_at ?? $subscription->current_period_end;
                    $this->enterGraceOrSuspend(
                        $subscription,
                        $now,
                        $subscription->effectiveGraceDays(),
                        $anchor,
                    );
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
            ->chunkById(100, function ($subscriptions) use (&$count): void {
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

    private function enterGraceOrSuspend(
        Subscription $subscription,
        CarbonInterface $now,
        int $graceDays,
        mixed $anchor,
    ): void {
        $graceEndsAt = $this->graceEndsAtFromAnchor($anchor, $graceDays, $now);

        if ($graceEndsAt->lte($now)) {
            $subscription->update([
                'estado' => 'suspended',
                'grace_ends_at' => null,
            ]);

            return;
        }

        $subscription->update([
            'estado' => 'grace',
            'grace_ends_at' => $graceEndsAt,
        ]);
    }

    private function graceEndsAtFromAnchor(mixed $anchor, int $graceDays, CarbonInterface $now): CarbonInterface
    {
        $base = $anchor !== null ? Carbon::parse($anchor) : Carbon::parse($now);

        return $base->copy()->addDays($graceDays);
    }

    private function isFreePlan(Subscription $subscription): bool
    {
        $subscription->loadMissing('plan');

        return $subscription->plan?->codigo === Plan::CODIGO_FREE;
    }

    private function hasCoveringPayment(Subscription $subscription): bool
    {
        return $this->coverage->hasCoveringPayment($subscription);
    }
}
