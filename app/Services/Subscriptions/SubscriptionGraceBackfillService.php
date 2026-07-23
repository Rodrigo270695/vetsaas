<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Inicializa / sincroniza `grace_days` en suscripciones de pago y, si ya
 * están vencidas/en gracia/suspendidas, activa la ventana de gracia.
 *
 * Las active aún vigentes solo reciben el valor de días (configurable);
 * al vencer el cobro el supervisor usa `effectiveGraceDays()`.
 */
class SubscriptionGraceBackfillService
{
    /**
     * @return array{
     *     scanned: int,
     *     days_set: int,
     *     grace_applied: int,
     *     skipped: int,
     *     dry_run: bool,
     *     default_grace_days: int,
     *     items: list<array{id: string, tenant_id: string, from: string, grace_days: int, estado: string, grace_ends_at: string|null}>
     * }
     */
    public function run(bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $defaultDays = max(1, (int) config('billing.grace_days', 3));

        $scanned = 0;
        $daysSet = 0;
        $graceApplied = 0;
        $skipped = 0;
        /** @var list<array{id: string, tenant_id: string, from: string, grace_days: int, estado: string, grace_ends_at: string|null}> $items */
        $items = [];

        Subscription::query()
            ->billable()
            ->where('precio_pactado', '>', 0)
            ->whereIn('estado', ['active', 'grace', 'suspended'])
            ->with(['plan:id,codigo', 'tenant:id,slug'])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (
                $dryRun,
                $defaultDays,
                $now,
                &$scanned,
                &$daysSet,
                &$graceApplied,
                &$skipped,
                &$items,
            ): void {
                foreach ($subscriptions as $subscription) {
                    $scanned++;

                    if ($subscription->plan?->isFree()) {
                        $skipped++;

                        continue;
                    }

                    $from = (string) $subscription->estado;
                    $days = $subscription->grace_days !== null && (int) $subscription->grace_days >= 1
                        ? max(1, min(90, (int) $subscription->grace_days))
                        : $defaultDays;

                    $payload = [];
                    $needsDays = $subscription->grace_days === null || (int) $subscription->grace_days < 1;

                    if ($needsDays) {
                        $payload['grace_days'] = $defaultDays;
                        $days = $defaultDays;
                    }

                    $enterGrace = in_array($subscription->estado, ['grace', 'suspended'], true)
                        || ($subscription->estado === 'active' && $this->isOverdue($subscription));

                    $graceEndsAt = null;

                    if ($enterGrace) {
                        $graceEndsAt = Carbon::parse($now)->copy()->addDays($days);
                        $payload['estado'] = 'grace';
                        $payload['grace_ends_at'] = $graceEndsAt;
                    }

                    if ($payload === []) {
                        $skipped++;

                        continue;
                    }

                    $items[] = [
                        'id' => (string) $subscription->id,
                        'tenant_id' => (string) $subscription->tenant_id,
                        'from' => $from,
                        'grace_days' => $days,
                        'estado' => $payload['estado'] ?? $from,
                        'grace_ends_at' => $graceEndsAt?->toIso8601String(),
                    ];

                    if (! $dryRun) {
                        $subscription->update($payload);
                    }

                    if ($needsDays) {
                        $daysSet++;
                    }

                    if ($enterGrace) {
                        $graceApplied++;
                    }
                }
            });

        return [
            'scanned' => $scanned,
            'days_set' => $daysSet,
            'grace_applied' => $graceApplied,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'default_grace_days' => $defaultDays,
            'items' => $items,
        ];
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
