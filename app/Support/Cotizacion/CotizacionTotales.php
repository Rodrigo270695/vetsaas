<?php

declare(strict_types=1);

namespace App\Support\Cotizacion;

/**
 * Totales de cotización alineados con `resources/js/pages/caja/cotizaciones/cotizacion-totales.ts`.
 *
 * El precio unitario es el de lista de la clínica: con IGV incluido si
 * `precio_incluye_igv`, sin IGV en caso contrario.
 */
final class CotizacionTotales
{
    public static function lineaTotal(float $precio, float $cantidad, float $descuentoPct): float
    {
        if ($precio <= 0 || $cantidad <= 0) {
            return 0.0;
        }

        $factor = 1 - (min(100.0, max(0.0, $descuentoPct)) / 100);

        return max(0.0, round($precio * $cantidad * $factor, 2));
    }

    /**
     * @param  list<array{precio_unitario: float|int|string, cantidad: float|int|string, descuento_pct?: float|int|string|null}>  $lineas
     * @return array{subtotal: float, igv: float, descuento: float, total: float}
     */
    public static function calcular(array $lineas, float $igvPct, bool $precioIncluyeIgv): array
    {
        $neto = 0.0;
        $bruto = 0.0;

        foreach ($lineas as $linea) {
            $precio = (float) $linea['precio_unitario'];
            $cantidad = (float) $linea['cantidad'];
            $bruto += round($precio * $cantidad, 2);
            $neto += self::lineaTotal($precio, $cantidad, (float) ($linea['descuento_pct'] ?? 0));
        }

        $neto = round($neto, 2);
        $descuento = round(max(0.0, $bruto - $neto), 2);

        if ($precioIncluyeIgv) {
            $subtotal = $igvPct > 0 ? round($neto / (1 + $igvPct / 100), 2) : $neto;

            return [
                'subtotal' => $subtotal,
                'igv' => round($neto - $subtotal, 2),
                'descuento' => $descuento,
                'total' => $neto,
            ];
        }

        $igv = round($neto * ($igvPct / 100), 2);

        return [
            'subtotal' => $neto,
            'igv' => $igv,
            'descuento' => $descuento,
            'total' => round($neto + $igv, 2),
        ];
    }
}
