<?php

namespace App\Support\Caja;

use App\Models\ClinicSetting;
use App\Models\Tenant;
use App\Models\Venta;
use App\Support\PlanCapabilities;

/**
 * Cuándo se puede imprimir el ticket térmico de una venta.
 */
final class VentaTicketPolicy
{
    public static function puedeImprimir(Venta $venta, ClinicSetting $clinic, ?Tenant $tenant): bool
    {
        if ($venta->estado !== Venta::ESTADO_PAGADO || $venta->estaAnulada()) {
            return false;
        }

        $requiereCpePrevio = PlanCapabilities::facturaElectronica($tenant)
            && (bool) $clinic->emite_comprobantes_sunat
            && (bool) $clinic->nubefact_configurado;

        if (! $requiereCpePrevio) {
            return true;
        }

        return in_array($venta->fel_estado, [Venta::FEL_EMITIDO, Venta::FEL_SIN_CPE], true);
    }
}
