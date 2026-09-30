<?php

declare(strict_types=1);

namespace App\Support\Venta;

use App\Models\ClinicSetting;
use App\Models\VentaPago;
use Illuminate\Support\Facades\Schema;

/**
 * Recargo que se suma al total cuando una parte de la venta se cobra con tarjeta.
 * El porcentaje vive en la configuración del tenant y se recuerda entre ventas.
 */
final class RecargoTarjeta
{
    public static function clamp(float $porcentaje): float
    {
        return round(min(100, max(0, $porcentaje)), 2);
    }

    public static function monto(float $baseTarjeta, float $porcentaje): float
    {
        $baseTarjeta = round($baseTarjeta, 2);
        $porcentaje = self::clamp($porcentaje);
        if ($baseTarjeta <= 0 || $porcentaje <= 0) {
            return 0.0;
        }

        return round($baseTarjeta * ($porcentaje / 100), 2);
    }

    public static function guardado(ClinicSetting $clinic): float
    {
        $raw = $clinic->getAttribute('recargo_tarjeta_porcentaje');
        if ($raw === null || $raw === '') {
            return 5.0;
        }

        return self::clamp((float) $raw);
    }

    public static function porcentajeDesdeRequest(mixed $raw, ClinicSetting $clinic): float
    {
        if ($raw === null || $raw === '') {
            return self::guardado($clinic);
        }

        return self::clamp((float) $raw);
    }

    public static function recordar(ClinicSetting $clinic, float $porcentaje): void
    {
        if (! Schema::hasColumn('cfg_clinic_settings', 'recargo_tarjeta_porcentaje')) {
            return;
        }

        $porcentaje = self::clamp($porcentaje);
        if (abs(self::guardado($clinic) - $porcentaje) < 0.001) {
            return;
        }

        $clinic->update([
            'recargo_tarjeta_porcentaje' => number_format($porcentaje, 2, '.', ''),
        ]);
    }

    public static function etiqueta(float $porcentaje): string
    {
        $texto = number_format(self::clamp($porcentaje), 2, '.', '');

        return rtrim(rtrim($texto, '0'), '.');
    }

    /**
     * Carga el recargo solo en el pago con tarjeta.
     * Las líneas quedan con su precio original: ticket, boleta y factura no lo muestran.
     *
     * @param  list<array{metodo: string, monto: float, monto_recibido: ?float, vuelto: ?float}>  $pagos
     * @return array{
     *     pagos: list<array{metodo: string, monto: float, monto_recibido: ?float, vuelto: ?float}>,
     *     monto: float,
     *     porcentaje: float,
     *     aplicado: bool
     * }
     */
    public static function aplicarAlPago(array $pagos, float $porcentaje): array
    {
        $porcentaje = self::clamp($porcentaje);
        $baseTarjeta = 0.0;
        foreach ($pagos as $pago) {
            if ($pago['metodo'] === VentaPago::METODO_TARJETA) {
                $baseTarjeta += $pago['monto'];
            }
        }

        $recargo = self::monto($baseTarjeta, $porcentaje);
        if ($recargo < 0.01) {
            return [
                'pagos' => $pagos,
                'monto' => 0.0,
                'porcentaje' => $porcentaje,
                'aplicado' => false,
            ];
        }

        foreach ($pagos as $i => $pago) {
            if ($pago['metodo'] !== VentaPago::METODO_TARJETA) {
                continue;
            }
            $pagos[$i]['monto'] = round($pago['monto'] + $recargo, 2);
        }

        return [
            'pagos' => $pagos,
            'monto' => $recargo,
            'porcentaje' => $porcentaje,
            'aplicado' => true,
        ];
    }
}
