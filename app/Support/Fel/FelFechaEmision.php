<?php

declare(strict_types=1);

namespace App\Support\Fel;

use App\Models\FelSerie;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Plazo SUNAT para fechar un CPE hacia atrás el día en que se envía.
 *
 * Factura: hasta 3 días calendario (RS 000003-2023/SUNAT).
 * Boleta enviada suelta: hasta 5 días calendario (RS 114-2019/SUNAT).
 */
final class FelFechaEmision
{
    public const ZONA = 'America/Lima';

    public static function diasMaximos(int $tipo): int
    {
        return match ($tipo) {
            FelSerie::TIPO_FACTURA => 3,
            FelSerie::TIPO_BOLETA => 5,
            default => 0,
        };
    }

    public static function hoy(): CarbonInterface
    {
        return now(self::ZONA)->startOfDay();
    }

    public static function minima(int $tipo): CarbonInterface
    {
        return self::hoy()->subDays(self::diasMaximos($tipo));
    }

    /**
     * Fecha de calendario aceptada, o null si el valor está fuera de plazo.
     */
    public static function resolver(int $tipo, mixed $fecha): ?CarbonInterface
    {
        $hoy = self::hoy();

        if ($fecha === null || $fecha === '') {
            return $hoy->copy();
        }

        try {
            $elegida = $fecha instanceof CarbonInterface
                ? $fecha->timezone(self::ZONA)->startOfDay()
                : Carbon::parse((string) $fecha, self::ZONA)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($elegida->gt($hoy) || $elegida->lt(self::minima($tipo))) {
            return null;
        }

        return $elegida;
    }

    public static function esPosteriorAHoy(mixed $fecha): bool
    {
        if ($fecha === null || $fecha === '') {
            return false;
        }

        try {
            $elegida = $fecha instanceof CarbonInterface
                ? $fecha->timezone(self::ZONA)->startOfDay()
                : Carbon::parse((string) $fecha, self::ZONA)->startOfDay();
        } catch (\Throwable) {
            return true;
        }

        return $elegida->gt(self::hoy());
    }

    public static function mensajeFueraDePlazo(int $tipo): string
    {
        $etiqueta = $tipo === FelSerie::TIPO_FACTURA ? 'factura' : 'boleta';

        return __('caja.ventas.fel.fecha_emision_fuera', [
            'tipo' => $etiqueta,
            'dias' => self::diasMaximos($tipo),
        ]);
    }

    /**
     * Instante que se manda a Lucode. Hoy conserva la hora actual; un día anterior va al mediodía.
     */
    public static function instanteEmision(int $tipo, mixed $fecha): CarbonInterface
    {
        $dia = self::resolver($tipo, $fecha);
        if ($dia === null) {
            throw new RuntimeException(self::mensajeFueraDePlazo($tipo));
        }

        $ahora = now(self::ZONA);
        if ($dia->isSameDay($ahora)) {
            return $ahora;
        }

        return $dia->setTime(12, 0, 0);
    }
}
