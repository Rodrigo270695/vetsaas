<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Registra un pago recibido fuera de Orvae y renueva el período real.
 *
 * La renovación mueve current_period_*; por eso el cupo de comprobantes,
 * calculado por fechas de emisión dentro del período, empieza nuevamente.
 */
final class ManualSubscriptionRenewalService
{
    public function __construct(
        private readonly SubscriptionRenewalService $renewals,
    ) {}

    /**
     * @return array{subscription: Subscription, payment: SubscriptionPayment, already_processed: bool}
     */
    public function renew(
        string $subscriptionId,
        float $amount,
        string $method,
        string $idempotencyKey,
        User $actor,
        string $reference,
        ?string $note = null,
    ): array {
        return DB::transaction(function () use (
            $subscriptionId,
            $amount,
            $method,
            $idempotencyKey,
            $actor,
            $reference,
            $note,
        ): array {
            $subscription = Subscription::query()
                ->with(['tenant', 'plan'])
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            if ($subscription->estado === 'cancelled') {
                throw new InvalidArgumentException('No se puede renovar una suscripción cancelada.');
            }

            if ($subscription->plan === null || $subscription->plan->isFree()) {
                throw new InvalidArgumentException('La renovación manual solo aplica a planes de pago.');
            }

            $tenant = $subscription->tenant;
            if (! $tenant instanceof Tenant) {
                throw new InvalidArgumentException('La suscripción no tiene un tenant válido.');
            }

            $normalizedReference = mb_strtolower(
                trim((string) preg_replace('/\s+/', ' ', $reference)),
            );
            if ($normalizedReference === '') {
                throw new InvalidArgumentException('La referencia del pago es obligatoria.');
            }

            $transactionId = 'manual-renewal:'.hash(
                'sha256',
                $method.'|'.$normalizedReference,
            );
            $existingPayment = SubscriptionPayment::query()
                ->where('subscription_id', $subscription->id)
                ->where('pasarela_transaction_id', $transactionId)
                ->first();

            if ($existingPayment instanceof SubscriptionPayment) {
                return [
                    'subscription' => $subscription->fresh(['plan']) ?? $subscription,
                    'payment' => $existingPayment,
                    'already_processed' => true,
                ];
            }

            $previousPeriod = [
                'start' => optional($subscription->current_period_start)?->toIso8601String(),
                'end' => optional($subscription->current_period_end)?->toIso8601String(),
                'next_charge' => optional($subscription->proximo_cobro_at)?->toIso8601String(),
            ];
            $paidAt = now();

            $renewed = $this->renewals->renewExisting($subscription, $tenant, [
                'plan_slug' => $subscription->plan->codigo,
                'ciclo' => $subscription->ciclo,
                'precio_pactado' => (float) $subscription->precio_pactado,
                'payment' => [
                    'monto' => $amount,
                    'total' => $amount,
                    'moneda' => 'PEN',
                    'igv_monto' => 0,
                    'descuento_monto' => 0,
                    'estado' => 'procesado',
                    'pasarela' => 'manual',
                    'transaction_id' => $transactionId,
                    'pagado_at' => $paidAt,
                    'internal_note' => $note,
                    'raw_response' => [
                        'manual' => true,
                        'method' => $method,
                        'reference' => trim($reference),
                        'request_id' => $idempotencyKey,
                        'registered_by' => [
                            'id' => $actor->id,
                            'name' => $actor->name,
                            'email' => $actor->email,
                        ],
                        'previous_period' => $previousPeriod,
                    ],
                ],
            ]);

            $payment = SubscriptionPayment::query()
                ->where('subscription_id', $subscription->id)
                ->where('pasarela_transaction_id', $transactionId)
                ->firstOrFail();

            return [
                'subscription' => $renewed,
                'payment' => $payment,
                'already_processed' => false,
            ];
        }, 3);
    }
}
