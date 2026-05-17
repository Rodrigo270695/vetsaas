<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Capacidades del tenant derivadas del plan de suscripción activo.
 */
final class PlanCapabilities
{
    /**
     * Si el plan contratado permite que la clínica active la emisión de
     * comprobantes electrónicos SUNAT (boleta/factura vía integrador).
     *
     * Sin suscripción activa (trial/active/grace) o sin plan asociado,
     * se considera false.
     */
    public static function facturaElectronica(?Tenant $tenant): bool
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

        return (bool) $plan->resolveFeature('factura_electronica');
    }
}
