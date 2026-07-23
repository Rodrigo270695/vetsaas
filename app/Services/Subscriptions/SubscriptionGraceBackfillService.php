<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Aplica (o refresca) periodo de gracia a suscripciones de pago actuales.
 * Pensado para migración one-shot en VPS y re-ejecuciones controladas.
 *
 * No toca planes free ni suscripciones con precio_pactado <= 0.
 * El ciclo de facturación (ancla día 1, etc.) no se modifica: solo el estado
 * y `grace_ends_at` para dar tiempo a pagar.
 */
class SubscriptionGraceBackfillService
{
    /**
     * @return array{
     *     scanned: int,
     *     applied: int,
     *     skipped: int,
     *     dry_run: bool,
     *     grace_days: int,
     *     items: list<array{id: string, tenant_id: string, from: string, to: string, grace_ends_at: string}>
     * }
     */
    public function run(bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $graceDays = max(1, (int) config('billing.grace_days', 3));
        $graceEndsAt = Carbon::parse($now)->copy()->addDays($graceDays);

        $scanned = 0;
        $applied = 0;
        $skipped = 0;
        /** @var list<array{id: string, tenant_id: string, from: string, to: string, grace_ends_at: string}> $items */
        $items = [];

        Subscription::query()
            ->billable()
            ->where('precio_pactado', '>', 0)
            ->whereIn('estado', ['active', 'grace', 'suspended'])
            ->with(['plan:id,codigo', 'tenant:id,slug'])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (
                $dryRun,
                $graceEndsAt,
                &$scanned,
                &$applied,
                &$skipped,
                &$items,
            ): void {
                foreach ($subscriptions as $subscription) {
                    $scanned++;

                    if (! $this->shouldApply($subscription)) {
                        $skipped++;

                        continue;
                    }

                    $from = (string) $subscription->estado;
                    $items[] = [
                        'id' => (string) $subscription->id,
                        'tenant_id' => (string) $subscription->tenant_id,
                        'from' => $from,
                        'to' => 'grace',
                        'grace_ends_at' => $graceEndsAt->toIso8601String(),
                    ];

                    if (! $dryRun) {
                        $subscription->update([
                            'estado' => 'grace',
                            'grace_ends_at' => $graceEndsAt,
                        ]);
                    }

                    $applied++;
                }
            });

        return [
            'scanned' => $scanned,
            'applied' => $applied,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'grace_days' => $graceDays,
            'items' => $items,
        ];
    }

    /**
     * Aplica gracia a:
     * - active con cobro/periodo ya vencido
     * - grace (refresca ventana)
     * - suspended (reactiva ventana para pagar)
     *
     * Omite active aún vigente (recibirán gracia automáticamente al vencer el cobro).
     */
    private function shouldApply(Subscription $subscription): bool
    {
        if ((float) $subscription->precio_pactado <= 0) {
            return false;
        }

        if ($subscription->plan?->isFree()) {
            return false;
        }

        return match ($subscription->estado) {
            'grace', 'suspended' => true,
            'active' => $this->isOverdue($subscription),
            default => false,
        };
    }

    private function isOverdue(Subscription $subscription): bool
    {
        $anchor = $subscription->proximo_cobro_at ?? $subscription->current_period_end;

        if ($anchor === null) {
            return false;
        }

        return Carbon::parse($anchor)->isPast();
    }
}
