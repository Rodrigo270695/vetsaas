<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Support\Subscriptions\BillingGrace;

/**
 * Rellena `grace_ends_at` = `proximo_cobro_at` + `billing.grace_days` (default 3)
 * en suscripciones de pago. No cambia el estado; solo deja la fecha de fin de gracia.
 * Excluye plan free y precio_pactado <= 0.
 */
class SubscriptionGraceBackfillService
{
    /**
     * @return array{
     *     scanned: int,
     *     updated: int,
     *     skipped: int,
     *     dry_run: bool,
     *     grace_days: int,
     *     items: list<array{id: string, tenant_id: string, proximo_cobro_at: string, grace_ends_at: string}>
     * }
     */
    public function run(bool $dryRun = false): array
    {
        $graceDays = BillingGrace::days();

        $scanned = 0;
        $updated = 0;
        $skipped = 0;
        /** @var list<array{id: string, tenant_id: string, proximo_cobro_at: string, grace_ends_at: string}> $items */
        $items = [];

        Subscription::query()
            ->billable()
            ->where('precio_pactado', '>', 0)
            ->whereNotNull('proximo_cobro_at')
            ->whereIn('estado', ['active', 'grace', 'suspended', 'trial'])
            ->with(['plan:id,codigo'])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (
                $dryRun,
                $graceDays,
                &$scanned,
                &$updated,
                &$skipped,
                &$items,
            ): void {
                foreach ($subscriptions as $subscription) {
                    $scanned++;

                    if ($subscription->plan?->isFree() || $subscription->proximo_cobro_at === null) {
                        $skipped++;

                        continue;
                    }

                    $graceEndsAt = BillingGrace::endsAtFrom($subscription->proximo_cobro_at);

                    $items[] = [
                        'id' => (string) $subscription->id,
                        'tenant_id' => (string) $subscription->tenant_id,
                        'proximo_cobro_at' => $subscription->proximo_cobro_at->toIso8601String(),
                        'grace_ends_at' => $graceEndsAt->toIso8601String(),
                    ];

                    if (! $dryRun) {
                        $subscription->update([
                            'grace_ends_at' => $graceEndsAt,
                        ]);
                    }

                    $updated++;
                }
            });

        return [
            'scanned' => $scanned,
            'updated' => $updated,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'grace_days' => $graceDays,
            'items' => $items,
        ];
    }
}
