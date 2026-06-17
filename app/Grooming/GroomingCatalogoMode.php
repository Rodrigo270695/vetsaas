<?php

namespace App\Grooming;

use App\Models\ClinicSetting;

/**
 * Catálogo fijo (legacy) vs servicios editables por clínica (`grooming_servicios`).
 */
final class GroomingCatalogoMode
{
    /** Tenant piloto donde se activa el catálogo personalizado al migrar. */
    public const PILOT_TENANT_SLUG = 'clinica-grupo-maclabi';

    public static function usaCatalogoPersonalizado(?ClinicSetting $clinic = null): bool
    {
        $clinic = $clinic ?? ClinicSetting::current();

        return (bool) ($clinic->grooming_catalogo_personalizado ?? false);
    }
}
