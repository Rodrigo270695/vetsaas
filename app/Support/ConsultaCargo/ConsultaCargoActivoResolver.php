<?php

declare(strict_types=1);

namespace App\Support\ConsultaCargo;

use App\Models\ClinicSetting;
use App\Models\ConsultaCargo;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve la pre-cuenta activa (sin venta) de un origen, o crea borrador nuevo.
 * Las cobradas (venta_id) quedan históricas y no bloquean una nueva precuenta.
 */
final class ConsultaCargoActivoResolver
{
    /**
     * @param  'consulta_id'|'internamiento_id'|'grooming_turno_id'|'hotel_estancia_id'|'vacuna_aplicada_id'  $fk
     */
    public static function resolveOrCreate(string $fk, string $origenId, ClinicSetting $cfg): ConsultaCargo
    {
        $existing = ConsultaCargo::query()
            ->where($fk, $origenId)
            ->whereNull('venta_id')
            ->orderByDesc('updated_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return ConsultaCargo::query()->create([
            $fk => $origenId,
            'estado' => ConsultaCargo::ESTADO_BORRADOR,
            'moneda' => $cfg->moneda,
            'subtotal_sin_igv' => 0,
            'igv_importe' => 0,
            'total' => 0,
            'created_by_id' => Auth::id(),
            'updated_by_id' => Auth::id(),
        ]);
    }
}
