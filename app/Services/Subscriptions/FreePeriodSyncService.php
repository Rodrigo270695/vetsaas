<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;

/**
 * Los tenants Free "active" (fuera de trial) no tienen enforcement de cobro:
 * `SubscriptionBillingSupervisor::processOverdueActive` los excluye a propósito
 * porque nunca se les cobra de verdad. Eso deja `current_period_end` /
 * `proximo_cobro_at` sin ningún mantenimiento — pueden quedar "congelados"
 * con valores viejos (o editados a mano) que caen en el pasado o meses por
 * delante de hoy, haciendo que el badge de vencimiento muestre cifras sin
 * sentido (ej. "61 días") para un plan que solo debería regalar máximo
 * `MAX_DAYS` días por ciclo.
 *
 * Este servicio re-ancla esas fechas a una ventana relativa a "hoy" cada
 * vez que se detectan desfasadas, sin tocar el `estado` de la suscripción.
 */
final class FreePeriodSyncService
{
    /** Política de negocio: Free otorga máximo 30 días por ciclo activo. */
    public const MAX_DAYS = 30;

    /**
     * @return array{scanned: int, synced: int}
     */
    public function run(): array
    {
        $scanned = 0;
        $synced = 0;

        Subscription::query()
            ->where('estado', 'active')
            ->whereHas('plan', fn ($q) => $q->where('codigo', Plan::CODIGO_FREE))
            ->orderBy('id')
            ->chunkById(200, function ($subscriptions) use (&$scanned, &$synced): void {
                foreach ($subscriptions as $subscription) {
                    $scanned++;

                    if ($this->needsSync($subscription)) {
                        $this->sync($subscription);
                        $synced++;
                    }
                }
            });

        return ['scanned' => $scanned, 'synced' => $synced];
    }

    private function needsSync(Subscription $subscription): bool
    {
        $anchor = $subscription->proximo_cobro_at ?? $subscription->current_period_end;

        if ($anchor === null) {
            return true;
        }

        // Vencida (quedó en el pasado) o más allá de la ventana máxima de Free.
        return $anchor->lt(now()) || $anchor->gt(now()->addDays(self::MAX_DAYS));
    }

    private function sync(Subscription $subscription): void
    {
        $start = now();
        $end = $start->copy()->addDays(self::MAX_DAYS);

        $subscription->update([
            'current_period_start' => $start,
            'current_period_end' => $end,
            'proximo_cobro_at' => $end,
        ]);
    }
}
