<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;

/**
 * Convierte una suscripción de pago en fila del listado Plataforma → Cobros.
 * Si existe un cobro webhook, se usa; si no, se arma una fila sintética.
 */
final class CobrosListPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function fromSubscription(Subscription $subscription): array
    {
        $subscription->loadMissing([
            'tenant:id,slug,razon_social,nombre_comercial,email_admin',
            'plan:id,codigo,nombre,badge,color_hex',
        ]);

        $payment = $subscription->payments()
            ->forBillablePlans()
            ->with('refundedBy:id,name,email')
            ->orderByDesc('pagado_at')
            ->orderByDesc('created_at')
            ->first();

        if ($payment instanceof SubscriptionPayment) {
            return self::fromPayment($payment, $subscription);
        }

        return self::syntheticFromSubscription($subscription);
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
            'trial_ends_at' => optional($subscription->trial_ends_at)?->toIso8601String(),
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
