<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Support\Plan\TenantPlanLimitBilling;
use Illuminate\Support\Collection;

/**
 * Convierte una suscripción de pago en fila del listado Plataforma → Cobros.
 * Si existe un cobro webhook, se usa; si no, se arma una fila sintética.
 */
final class CobrosListPresenter
{
    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return Collection<int, array<string, mixed>>
     */
    public static function mapPage(Collection $subscriptions): Collection
    {
        $tenantIds = $subscriptions
            ->pluck('tenant_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $aggregates = self::aggregatesByTenant($tenantIds);
        $histories = self::historiesByTenant($tenantIds);

        return $subscriptions->map(function (Subscription $subscription) use ($aggregates, $histories): array {
            $row = self::fromSubscription($subscription);
            $tenantId = (string) ($subscription->tenant_id ?? '');
            $stats = $aggregates[$tenantId] ?? ['pagos_count' => 0, 'pagado_acumulado' => 0.0];

            $row['pagos_count'] = (int) $stats['pagos_count'];
            $row['pagado_acumulado'] = number_format((float) $stats['pagado_acumulado'], 2, '.', '');
            $row['manual_renewal_suggested_amount'] = number_format(
                SubscriptionRenewalBilling::planAmount($subscription)
                + SubscriptionRenewalBilling::botIaAmount($subscription)
                + TenantPlanLimitBilling::totalAmount($subscription->tenant),
                2,
                '.',
                '',
            );
            $row['payment_history'] = $histories[$tenantId] ?? [];

            return $row;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromSubscription(Subscription $subscription): array
    {
        $subscription->loadMissing([
            'tenant:id,slug,razon_social,nombre_comercial,email_admin',
            'plan:id,codigo,nombre,badge,color_hex',
        ]);

        $payment = self::resolvePayment($subscription);

        if ($payment instanceof SubscriptionPayment) {
            return self::fromPayment($payment, $subscription);
        }

        return self::syntheticFromSubscription($subscription);
    }

    /**
     * @param  list<string>  $tenantIds
     * @return array<string, array{pagos_count: int, pagado_acumulado: float}>
     */
    private static function aggregatesByTenant(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        return SubscriptionPayment::query()
            ->forBillableOrGateway()
            ->where('estado', 'procesado')
            ->whereIn('tenant_id', $tenantIds)
            ->selectRaw('tenant_id, COUNT(*) as pagos_count, COALESCE(SUM(total), 0) as pagado_acumulado')
            ->groupBy('tenant_id')
            ->get()
            ->mapWithKeys(static fn ($row): array => [
                (string) $row->tenant_id => [
                    'pagos_count' => (int) $row->pagos_count,
                    'pagado_acumulado' => (float) $row->pagado_acumulado,
                ],
            ])
            ->all();
    }

    /**
     * Historial de cobros procesados por tenant (más reciente primero).
     *
     * @param  list<string>  $tenantIds
     * @return array<string, list<array<string, mixed>>>
     */
    private static function historiesByTenant(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $rows = SubscriptionPayment::query()
            ->forBillableOrGateway()
            ->with('plan:id,codigo,nombre')
            ->where('estado', 'procesado')
            ->whereIn('tenant_id', $tenantIds)
            ->orderByDesc('pagado_at')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'tenant_id',
                'plan_id',
                'total',
                'moneda',
                'pasarela',
                'pasarela_transaction_id',
                'periodo_inicio',
                'periodo_fin',
                'pagado_at',
                'created_at',
            ]);

        $grouped = [];
        foreach ($rows as $payment) {
            $tenantId = (string) $payment->tenant_id;
            if (! isset($grouped[$tenantId])) {
                $grouped[$tenantId] = [];
            }
            if (count($grouped[$tenantId]) >= 12) {
                continue;
            }

            $grouped[$tenantId][] = [
                'id' => $payment->id,
                'total' => (string) $payment->total,
                'moneda' => (string) ($payment->moneda ?: 'PEN'),
                'pasarela' => $payment->pasarela,
                'pasarela_transaction_id' => $payment->pasarela_transaction_id,
                'plan' => $payment->plan?->only(['id', 'codigo', 'nombre']),
                'periodo_inicio' => optional($payment->periodo_inicio)?->toIso8601String(),
                'periodo_fin' => optional($payment->periodo_fin)?->toIso8601String(),
                'pagado_at' => optional($payment->pagado_at)?->toIso8601String(),
                'created_at' => optional($payment->created_at)?->toIso8601String(),
            ];
        }

        return $grouped;
    }

    private static function resolvePayment(Subscription $subscription): ?SubscriptionPayment
    {
        $baseQuery = SubscriptionPayment::query()
            ->forBillableOrGateway()
            ->with('refundedBy:id,name,email')
            ->orderByDesc('pagado_at')
            ->orderByDesc('created_at');

        $onSubscription = (clone $baseQuery)
            ->where('subscription_id', $subscription->id)
            ->first();

        if ($onSubscription instanceof SubscriptionPayment) {
            return $onSubscription;
        }

        return (clone $baseQuery)
            ->where('tenant_id', $subscription->tenant_id)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private static function fromPayment(
        SubscriptionPayment $payment,
        Subscription $subscription,
    ): array {
        $payment->loadMissing('refundedBy:id,name,email');

        $row = $payment->toArray();
        $row['has_payment_record'] = true;
        $row['plan'] = self::planRef($subscription);
        $row['subscription'] = self::subscriptionRef($subscription);
        $row['tenant'] = self::tenantRef($subscription);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private static function syntheticFromSubscription(Subscription $subscription): array
    {
        $precio = number_format((float) $subscription->precio_pactado, 2, '.', '');

        return [
            'id' => 'subscription:'.$subscription->id,
            'has_payment_record' => false,
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'monto' => $precio,
            'moneda' => 'PEN',
            'igv_monto' => '0.00',
            'descuento_monto' => '0.00',
            'total' => $precio,
            'estado' => 'sin_cobro',
            'pasarela' => null,
            'pasarela_transaction_id' => null,
            'pasarela_response' => null,
            'periodo_inicio' => optional($subscription->current_period_start)?->toIso8601String(),
            'periodo_fin' => optional($subscription->current_period_end)?->toIso8601String(),
            'fel_emitido' => false,
            'fel_numero' => null,
            'error_mensaje' => null,
            'pagado_at' => null,
            'created_at' => optional($subscription->created_at)?->toIso8601String(),
            'internal_note' => null,
            'refunded_at' => null,
            'refunded_by' => null,
            'refund_reason' => null,
            'invoice_resent_at' => null,
            'plan' => self::planRef($subscription),
            'subscription' => self::subscriptionRef($subscription),
            'tenant' => self::tenantRef($subscription),
            'refundedBy' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function planRef(Subscription $subscription): ?array
    {
        $plan = $subscription->plan;

        if ($plan === null) {
            return null;
        }

        return $plan->only(['id', 'codigo', 'nombre', 'badge', 'color_hex']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function subscriptionRef(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'plan_id' => $subscription->plan_id,
            'estado' => $subscription->estado,
            'ciclo' => $subscription->ciclo,
            'precio_pactado' => (string) $subscription->precio_pactado,
            'trial_ends_at' => optional($subscription->trial_ends_at)?->toIso8601String(),
            'current_period_start' => optional($subscription->current_period_start)?->toIso8601String(),
            'current_period_end' => optional($subscription->current_period_end)?->toIso8601String(),
            'grace_ends_at' => optional($subscription->grace_ends_at)?->toIso8601String(),
            'proximo_cobro_at' => optional($subscription->proximo_cobro_at)?->toIso8601String(),
            'plan' => self::planRef($subscription),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function tenantRef(Subscription $subscription): ?array
    {
        $tenant = $subscription->tenant;

        if ($tenant === null) {
            return null;
        }

        return [
            ...$tenant->only([
                'id',
                'slug',
                'razon_social',
                'nombre_comercial',
                'email_admin',
            ]),
            'subscriptions' => [self::subscriptionRef($subscription)],
        ];
    }
}
