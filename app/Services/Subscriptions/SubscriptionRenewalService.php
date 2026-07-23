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
    public function __construct(
        private readonly SubscriptionPeriodCalculator $periods,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function renew(Tenant $tenant, array $payload): Subscription
    {
        $subscription = $tenant->activeSubscription()
            ?? $tenant->subscriptions()->latest()->first();

        if ($subscription === null) {
            throw new InvalidArgumentException('El tenant no tiene suscripción para renovar.');
        }

        return $this->renewExisting($subscription, $tenant, $payload);
    }

    /**
     * Renueva una suscripción concreta. Permite que flujos internos bloqueen
     * esa fila antes de aplicar el período y registrar el pago.
     *
     * @param  array<string, mixed>  $payload
     */
    public function renewExisting(
        Subscription $subscription,
        Tenant $tenant,
        array $payload,
    ): Subscription {
        if ((string) $subscription->tenant_id !== (string) $tenant->id) {
            throw new InvalidArgumentException('La suscripción no pertenece al tenant indicado.');
        }

        $planSlug = (string) ($payload['plan_slug'] ?? '');
        $plan = Plan::query()
            ->where('codigo', $planSlug)
            ->where('activo', true)
            ->first();

        if ($plan === null) {
            throw new InvalidArgumentException("Plan no encontrado o inactivo: {$planSlug}");
        }

        $cicloCandidate = (string) ($payload['ciclo'] ?? $subscription->ciclo ?? 'mensual');
        $ciclo = in_array($cicloCandidate, ['mensual', 'anual'], true)
            ? $cicloCandidate
            : 'mensual';

        $payment = is_array($payload['payment'] ?? null)
            ? $payload['payment']
            : null;
        $paidAt = $this->parseDate($payment['pagado_at'] ?? null) ?? now();
        $periodStart = $this->parseDate($payload['period_start'] ?? null)
            ?? $this->defaultPeriodStart($subscription, $paidAt);
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
            'grace_ends_at' => $periodEnd->copy()->addDays(max(1, (int) config('billing.grace_days', 3))),
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'proximo_cobro_at' => $periodEnd,
            'precio_pactado' => $precio,
        ]);

        // El cupo de comprobantes en UI usa emitido_at dentro de [period_start, period_end].
        // Al renovar, las fechas nuevas reinician el contador sin campo aparte.

        if ($payment !== null) {
            $this->recordPayment($subscription, $tenant, $plan, $payment, $periodStart, $periodEnd, $payload['comprobantes_overage'] ?? null);
        }

        return $subscription->fresh(['plan']);
    }

    private function defaultPeriodStart(
        Subscription $subscription,
        CarbonInterface $paidAt,
    ): CarbonInterface {
        return $this->periods->nextPeriodStart($subscription, $paidAt);
    }

    private function defaultPeriodEnd(CarbonInterface $start, string $ciclo): CarbonInterface
    {
        return $this->periods->nextPeriodEnd($start, $ciclo);
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
        ?array $comprobantesOverage = null,
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
            'pasarela_response' => array_filter([
                'raw' => $payment['raw_response'] ?? null,
                'comprobantes_overage' => $comprobantesOverage,
            ], fn ($v) => $v !== null),
            'periodo_inicio' => $periodStart,
            'periodo_fin' => $periodEnd,
            'pagado_at' => isset($payment['pagado_at']) ? Carbon::parse($payment['pagado_at']) : now(),
            'created_at' => now(),
            'internal_note' => $payment['internal_note'] ?? null,
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
