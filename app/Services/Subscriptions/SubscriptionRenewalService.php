<?php

namespace App\Services\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Renueva el período de una suscripción existente (mismo tenant/subdominio).
 * Llamado desde Orvae tras un pago de renovación.
 */
class SubscriptionRenewalService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function renew(Tenant $tenant, array $payload): Subscription
    {
        $plan = Plan::query()
            ->where('codigo', $payload['plan_slug'])
            ->where('activo', true)
            ->first();

        if ($plan === null) {
            throw new InvalidArgumentException("Plan no encontrado o inactivo: {$payload['plan_slug']}");
        }

        $subscription = $tenant->activeSubscription()
            ?? $tenant->subscriptions()->latest()->first();

        if ($subscription === null) {
            throw new InvalidArgumentException("El tenant no tiene suscripción para renovar.");
        }

        $ciclo = in_array($payload['ciclo'] ?? $subscription->ciclo, ['mensual', 'anual'], true)
            ? ($payload['ciclo'] ?? $subscription->ciclo)
            : $subscription->ciclo;

        $periodStart = $this->parseDate($payload['period_start'] ?? null)
            ?? $this->defaultPeriodStart($subscription);
        $periodEnd = $this->parseDate($payload['period_end'] ?? null)
            ?? $this->defaultPeriodEnd($periodStart, $ciclo);

        $precio = isset($payload['precio_pactado'])
            ? (float) $payload['precio_pactado']
            : ($ciclo === 'anual'
                ? (float) ($plan->precio_anual ?? $plan->precio_mensual * 12)
                : (float) $plan->precio_mensual);

        $subscription->update([
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => $ciclo,
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'proximo_cobro_at' => $periodEnd,
            'precio_pactado' => $precio,
        ]);

        if (is_array($payload['payment'] ?? null)) {
            $this->recordPayment($subscription, $tenant, $plan, $payload['payment'], $periodStart, $periodEnd);
        }

        return $subscription->fresh(['plan']);
    }

    private function defaultPeriodStart(Subscription $subscription): CarbonInterface
    {
        $end = $subscription->current_period_end;

        if ($end instanceof CarbonInterface && $end->isFuture()) {
            return $end;
        }

        return now();
    }

    private function defaultPeriodEnd(CarbonInterface $start, string $ciclo): CarbonInterface
    {
        return $ciclo === 'anual'
            ? Carbon::parse($start)->addYear()
            : Carbon::parse($start)->addMonth();
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function recordPayment(
        Subscription $subscription,
        Tenant $tenant,
        Plan $plan,
        array $payment,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): void {
        $transactionId = $payment['transaction_id'] ?? null;

        if (is_string($transactionId) && $transactionId !== '') {
            $exists = SubscriptionPayment::query()
                ->where('subscription_id', $subscription->id)
                ->where('pasarela_transaction_id', $transactionId)
                ->exists();

            if ($exists) {
                return;
            }
        }

        SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'monto' => $payment['monto'],
            'moneda' => $payment['moneda'] ?? 'PEN',
            'igv_monto' => $payment['igv_monto'] ?? 0,
            'descuento_monto' => $payment['descuento_monto'] ?? 0,
            'total' => $payment['total'] ?? $payment['monto'],
            'estado' => $payment['estado'] ?? 'procesado',
            'pasarela' => $payment['pasarela'] ?? 'orvae',
            'pasarela_transaction_id' => $transactionId,
            'pasarela_response' => $payment['raw_response'] ?? null,
            'periodo_inicio' => $periodStart,
            'periodo_fin' => $periodEnd,
            'pagado_at' => isset($payment['pagado_at']) ? Carbon::parse($payment['pagado_at']) : now(),
            'created_at' => now(),
        ]);
    }

    private function parseDate(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
