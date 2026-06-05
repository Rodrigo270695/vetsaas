<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantManager;

/**
 * Mantiene `tenants.estado` y `tenants.trial_ends_at` alineados con la
 * suscripción viva del tenant. El plan solo existe en `subscriptions`;
 * el listado de Tenants muestra estado/trial desde `tenants`.
 */
class SubscriptionTenantSync
{
    public function __construct(private TenantManager $tenantManager) {}

    public function sync(Subscription $subscription): void
    {
        $subscription->loadMissing('tenant');
        $tenant = $subscription->tenant;

        if ($tenant === null || ! $this->shouldSync($subscription, $tenant)) {
            return;
        }

        $tenant->update($this->buildTenantPayload($subscription, $tenant));

        $this->tenantManager->flushCacheFor($tenant->fresh() ?? $tenant);
    }

    /**
     * Repara desajustes históricos (p. ej. tras editar solo en Suscripciones).
     *
     * @return int Tenants actualizados
     */
    public function syncAllLiving(): int
    {
        $count = 0;

        Tenant::query()
            ->where('estado', '!=', 'cancelled')
            ->chunkById(100, function ($tenants) use (&$count): void {
                foreach ($tenants as $tenant) {
                    $subscription = $tenant->activeSubscription();

                    if ($subscription === null) {
                        continue;
                    }

                    $beforeEstado = $tenant->estado;
                    $beforeTrial = $tenant->trial_ends_at?->toIso8601String();

                    $this->sync($subscription);

                    $tenant->refresh();

                    if ($tenant->estado !== $beforeEstado
                        || $tenant->trial_ends_at?->toIso8601String() !== $beforeTrial) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function shouldSync(Subscription $subscription, Tenant $tenant): bool
    {
        if (in_array($subscription->estado, ['trial', 'active', 'grace', 'suspended'], true)) {
            return true;
        }

        if ($subscription->estado !== 'cancelled') {
            return false;
        }

        return ! $tenant->subscriptions()
            ->whereKeyNot($subscription->id)
            ->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTenantPayload(Subscription $subscription, Tenant $tenant): array
    {
        $estado = match ($subscription->estado) {
            'grace' => 'active',
            'suspended' => 'suspended',
            'cancelled' => 'cancelled',
            default => $subscription->estado,
        };

        $payload = [
            'estado' => $estado,
            'trial_ends_at' => $subscription->estado === 'trial' ? $subscription->trial_ends_at : null,
        ];

        if ($subscription->estado === 'suspended') {
            if ($tenant->suspended_at === null) {
                $payload['suspended_at'] = now();
            }

            if ($tenant->suspension_reason === null || $tenant->suspension_reason === '') {
                $payload['suspension_reason'] = 'Suscripción suspendida por impago (periodo de gracia vencido sin cobro registrado).';
            }
        } elseif (in_array($subscription->estado, ['trial', 'active', 'grace'], true)) {
            $payload['suspended_at'] = null;
            $payload['suspension_reason'] = null;
        }

        if ($subscription->estado === 'cancelled') {
            $payload['cancelled_at'] = $subscription->cancelled_at ?? now();
            $payload['cancel_reason'] = $subscription->cancel_reason;
        }

        return $payload;
    }
}
