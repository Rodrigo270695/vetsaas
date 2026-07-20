<?php

namespace App\Support\Citas;

use Illuminate\Support\Carbon;

/**
 * Regla de negocio: no se agenda en el pasado, comparando al minuto
 * (si son las 15:38:40, 15:38 sigue siendo válido; 15:37 no).
 */
final class CitaInicioValidator
{
    public static function isPast(?string $rawInicio, ?Carbon $now = null): bool
    {
        if ($rawInicio === null || trim($rawInicio) === '') {
            return false;
        }

        try {
            $inicio = Carbon::parse($rawInicio, config('app.timezone'))->startOfMinute();
        } catch (\Throwable) {
            return false;
        }

        $now ??= now();

        return $inicio->lt($now->copy()->timezone((string) config('app.timezone'))->startOfMinute());
    }
}
