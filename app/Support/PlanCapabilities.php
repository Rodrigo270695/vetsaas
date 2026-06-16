<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Capacidades del tenant derivadas del plan de suscripción activo.
 */
final class PlanCapabilities
{
    /**
     * Si el plan permite emitir boletas electrónicas (consumidores finales / DNI).
     */
    public static function boletasElectronicas(?Tenant $tenant): bool
    {
        return self::resolveBool($tenant, 'boletas_electronicas');
    }

    /**
     * Si el plan permite emitir facturas electrónicas (clientes con RUC).
     */
    public static function facturasElectronicas(?Tenant $tenant): bool
    {
        return self::resolveBool($tenant, 'facturas_electronicas');
    }

    /**
     * @deprecated Usar boletasElectronicas() o facturasElectronicas() según el tipo.
     */
    public static function facturaElectronica(?Tenant $tenant): bool
    {
        return self::boletasElectronicas($tenant) || self::facturasElectronicas($tenant);
    }

    private static function resolveBool(?Tenant $tenant, string $feature): bool
    {
        if ($tenant === null) {
            return false;
        }

        $subscription = $tenant->activeSubscription();
        if ($subscription === null) {
            return false;
        }

        $plan = $subscription->plan;
        if ($plan === null) {
            return false;
        }

        return (bool) $plan->resolveFeature($feature);
    }
}
