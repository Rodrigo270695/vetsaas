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
     * Suma el recargo al total y lo carga en el pago con tarjeta.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<array{metodo: string, monto: float, monto_recibido: ?float, vuelto: ?float}>  $pagos
     * @return array{
     *     lineas: list<array<string, mixed>>,
     *     pagos: list<array{metodo: string, monto: float, monto_recibido: ?float, vuelto: ?float}>,
     *     aplicado: bool
     * }
     */
    public static function anexar(
        array $lineas,
        array $pagos,
        float $porcentaje,
        float $igvPct,
        bool $precioIncluyeIgv,
        string $igvTipo,
    ): array {
        $baseTarjeta = 0.0;
        foreach ($pagos as $pago) {
            if ($pago['metodo'] === VentaPago::METODO_TARJETA) {
                $baseTarjeta += $pago['monto'];
            }
        }

        $recargo = self::monto($baseTarjeta, $porcentaje);
        if ($recargo < 0.01) {
            return ['lineas' => $lineas, 'pagos' => $pagos, 'aplicado' => false];
        }

        $antes = VentaTotales::fromLineas($lineas, $igvPct, $precioIncluyeIgv);
        $objetivo = round($antes['total'] + $recargo, 2);
        $divisor = 1 + ($igvPct / 100);
        $sub = $divisor > 0 ? round($recargo / $divisor, 2) : $recargo;
        $linea = [
            'producto_id' => null,
            'tipo_linea' => 'servicio',
            'consulta_cargo_linea_id' => null,
            'descripcion_snapshot' => __('caja.ventas.recargo_tarjeta_linea', [
                'pct' => self::etiqueta($porcentaje),
            ]),
            'igv_tipo_snapshot' => $igvTipo,
            'cantidad' => 1.0,
            'precio_lista' => $precioIncluyeIgv ? $recargo : $sub,
            'precio_unitario' => round($sub, 4),
            'descuento_pct' => 0.0,
            'subtotal' => $sub,
            'promotion_id' => null,
        ];

        $cuadrado = false;
        for ($i = 0; $i < 4; $i++) {
            $probe = $lineas;
            $probe[] = $linea;
            $total = VentaTotales::fromLineas($probe, $igvPct, $precioIncluyeIgv)['total'];
            $drift = round($objetivo - $total, 2);
            if (abs($drift) < 0.009) {
                $lineas = $probe;
                $cuadrado = true;
                break;
            }

            if ($precioIncluyeIgv) {
                $linea['precio_lista'] = round((float) $linea['precio_lista'] + $drift, 2);
                $nuevoSub = $divisor > 0
                    ? round((float) $linea['precio_lista'] / $divisor, 2)
                    : (float) $linea['precio_lista'];
            } else {
                $ajuste = $divisor > 0 ? round($drift / $divisor, 2) : $drift;
                if (abs($ajuste) < 0.01) {
                    $ajuste = $drift > 0 ? 0.01 : -0.01;
                }
                $nuevoSub = round((float) $linea['subtotal'] + $ajuste, 2);
                $linea['precio_lista'] = $nuevoSub;
            }

            $linea['subtotal'] = $nuevoSub;
            $linea['precio_unitario'] = round($nuevoSub, 4);
        }

        if (! $cuadrado) {
            $lineas[] = $linea;
        }

        $delta = round(VentaTotales::fromLineas($lineas, $igvPct, $precioIncluyeIgv)['total'] - $antes['total'], 2);
        foreach ($pagos as $i => $pago) {
            if ($pago['metodo'] !== VentaPago::METODO_TARJETA) {
                continue;
            }
            $pagos[$i]['monto'] = round($pago['monto'] + $delta, 2);
        }

        return ['lineas' => $lineas, 'pagos' => $pagos, 'aplicado' => true];
    }
}
