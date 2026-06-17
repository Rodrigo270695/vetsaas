<?php

namespace App\Hotel;

use App\Models\ClinicSetting;

/**
 * Catálogo fijo (legacy) vs tipos de estancia editables por clínica (`hotel_tipos_estancia`).
 */
final class HotelCatalogoMode
{
    public static function usaCatalogoPersonalizado(?ClinicSetting $clinic = null): bool
    {
        $clinic = $clinic ?? ClinicSetting::current();

        return (bool) ($clinic->hotel_catalogo_personalizado ?? false);
    }
}
