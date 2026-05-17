<?php

declare(strict_types=1);

namespace App\Support\ConsultaCargo;

/**
 * Calcula subtotal (base), IGV y total a partir de líneas de cargo y la política de IGV de la clínica.
 */
final class ConsultaCargoTotales
{
    /**
     * @param  list<array{cantidad: float|int|string, precio_unitario: float|int|string, descuento_importe?: float|int|string|null}>  $lineas
     * @return array{subtotal_sin_igv: string, igv_importe: string, total: string}
     */
    public static function fromLineas(
        array $lineas,
        bool $precioIncluyeIgv,
        float $igvPorcentaje,
    ): array {
        $sumaBruta = 0.0;
        foreach ($lineas as $linea) {
            $cant = (float) $linea['cantidad'];
            $pu = (float) $linea['precio_unitario'];
            $desc = (float) ($linea['descuento_importe'] ?? 0);
            $lineaBruta = max(0.0, $cant * $pu - $desc);
            $sumaBruta += $lineaBruta;
        }

        $sumaBruta = round($sumaBruta, 2);
        $tasa = max(0.0, $igvPorcentaje);

        if ($precioIncluyeIgv) {
            $total = $sumaBruta;
            if ($tasa <= 0.0) {
                $sub = $total;
                $igv = 0.0;
            } else {
                $sub = round($total / (1 + $tasa / 100), 2);
                $igv = round($total - $sub, 2);
            }
        } else {
            $sub = $sumaBruta;
            $igv = round($sub * ($tasa / 100), 2);
            $total = round($sub + $igv, 2);
        }

        return [
            'subtotal_sin_igv' => self::fmt($sub),
            'igv_importe' => self::fmt($igv),
            'total' => self::fmt($total),
        ];
    }

    private static function fmt(float $v): string
    {
        return number_format(round($v, 2), 2, '.', '');
    }
}
