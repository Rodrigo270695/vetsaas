<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Decide si un tenant puede usar la aplicación (login y sesiones activas).
 *
 * Bloquea acceso cuando la suscripción está suspendida/cancelada, o cuando
 * el período/trial ya expiró y también venció la ventana de gracia.
 * Durante `grace` (o active recién vencido dentro de `billing.grace_days`)
 * el tenant conserva acceso para poder pagar.
 */
final class TenantSubscriptionAccess
{
    public const DENIAL_SUSPENDED = 'suspended';

    public const DENIAL_CANCELLED = 'cancelled';

    public const DENIAL_EXPIRED = 'expired';

    public function allowsAccess(Tenant $tenant): bool
    {
        return $this->resolveDenial($tenant) === null;
    }

    /**
     * @return self::DENIAL_*|null
     */
    public function resolveDenial(Tenant $tenant): ?string
    {
        if ($tenant->estado === 'cancelled') {
            return self::DENIAL_CANCELLED;
        }

        if (
            $tenant->estado === 'suspended'
            && ! $tenant->relationLoaded('subscriptions')
            && $tenant->getKey() === null
        ) {
            return self::DENIAL_SUSPENDED;
        }

        $subscription = $this->latestBillingSubscription($tenant);

        if ($tenant->estado === 'suspended') {
            return $subscription?->estado === 'suspended'
                ? self::DENIAL_EXPIRED
                : self::DENIAL_SUSPENDED;
        }

        if ($subscription instanceof Subscription) {
            if ($subscription->estado === 'cancelled') {
                return self::DENIAL_CANCELLED;
            }

            if ($subscription->estado === 'suspended') {
                return self::DENIAL_SUSPENDED;
            }

            if ($subscription->estado === 'grace') {
                if ($this->isWithinGraceWindow($subscription)) {
                    return null;
                }

                return self::DENIAL_EXPIRED;
            }

            if ($subscription->estado === 'trial') {
                $trialEnd = $subscription->trial_ends_at ?? $tenant->trial_ends_at;

                if ($this->isPast($trialEnd)) {
                    return self::DENIAL_EXPIRED;
                }

                return null;
            }

            if ($subscription->estado === 'active') {
                $periodEnd = $subscription->current_period_end ?? $subscription->proximo_cobro_at;

                if (! $this->isPast($periodEnd)) {
                    return null;
                }

                // Plan de pago recién vencido: acceso mientras corre la gracia
                // (aunque el supervisor aún no haya marcado estado=grace).
                if ($this->isBillableOverdueWithinGrace($subscription, $periodEnd)) {
                    return null;
                }

                return self::DENIAL_EXPIRED;
            }

            return self::DENIAL_EXPIRED;
        }

        if ($tenant->estado === 'trial' && $this->isPast($tenant->trial_ends_at)) {
            return self::DENIAL_EXPIRED;
        }

        return null;
    }

    public function loginDeniedMessage(?string $denial): string
    {
        return match ($denial) {
            self::DENIAL_CANCELLED => 'Esta clínica fue cancelada. Contacta a soporte si crees que es un error.',
            self::DENIAL_SUSPENDED => 'El acceso está suspendido por falta de pago. Renueva tu plan en Orvae para reactivar la cuenta.',
            self::DENIAL_EXPIRED => 'El plan de tu clínica ha vencido. Renueva tu suscripción en Orvae para volver a ingresar.',
            default => 'No puedes acceder a esta clínica en este momento.',
        };
    }

    private function latestBillingSubscription(Tenant $tenant): ?Subscription
    {
        $subscriptions = $tenant->relationLoaded('subscriptions')
            ? $tenant->getRelation('subscriptions')
            : $tenant->subscriptions()->orderByDesc('created_at')->get();

        if ($subscriptions->isEmpty()) {
            return null;
        }

        $latest = $subscriptions->sortByDesc('created_at')->first();

        if (! $latest instanceof Subscription) {
            return null;
        }

        if ($latest->estado === 'cancelled') {
            $nonCancelled = $subscriptions
                ->filter(static fn (Subscription $sub): bool => $sub->estado !== 'cancelled')
                ->sortByDesc('created_at')
                ->first();

            return $nonCancelled instanceof Subscription ? $nonCancelled : $latest;
        }

        return $latest;
    }

    private function isWithinGraceWindow(Subscription $subscription): bool
    {
        if ($subscription->grace_ends_at !== null) {
            return ! $this->isPast($subscription->grace_ends_at);
        }

        $periodEnd = $subscription->proximo_cobro_at
            ?? $subscription->current_period_end
            ?? $subscription->trial_ends_at;

        if ($periodEnd === null) {
            return false;
        }

        return ! $this->isPast(Carbon::parse($periodEnd)->addDays($subscription->effectiveGraceDays()));
    }

    private function isBillableOverdueWithinGrace(Subscription $subscription, mixed $periodEnd): bool
    {
        if ((float) $subscription->precio_pactado <= 0) {
            return false;
        }

        if ($periodEnd === null) {
            return false;
        }

        return ! $this->isPast(Carbon::parse($periodEnd)->addDays($subscription->effectiveGraceDays()));
    }

    private function isPast(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return Carbon::parse($value)->isPast();
    }
}
