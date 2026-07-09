<?php

declare(strict_types=1);

namespace App\Support\Venta;

/**
 * Totales de venta alineados con `resources/js/pages/caja/ventas/venta-pricing.ts`.
 *
 * Cuando el precio de lista incluye IGV, el total cobrado al cliente es la suma
 * de los importes brutos por línea (precio × cantidad, con descuentos sobre bruto).
 * El IGV es el residual (total − subtotal) para no perder céntimos al redondear.
 */
final class VentaTotales
{
    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{subtotal: float, igv: float, total: float}
     */
    public static function fromLineas(array $lineas, float $igvPct, bool $precioIncluyeIgv): array
    {
        $divisor = 1 + ($igvPct / 100);

        if ($precioIncluyeIgv) {
            $subtotal = 0.0;
            $total = 0.0;

            foreach ($lineas as $line) {
                $subtotal += (float) ($line['subtotal'] ?? 0);
                $total += self::lineGross($line, $divisor);
            }

            $subtotal = round($subtotal, 2);
            $total = round($total, 2);
            $igv = round($total - $subtotal, 2);

            return [
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
            ];
        }

        $subtotal = 0.0;
        foreach ($lineas as $line) {
            $subtotal += (float) ($line['subtotal'] ?? 0);
        }

        $subtotal = round($subtotal, 2);
        $igv = round($subtotal * ($igvPct / 100), 2);
        $total = round($subtotal + $igv, 2);

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
        ];
    }

    /**
     * Importe bruto que paga el cliente por la línea (con IGV incluido en lista).
     *
     * @param  array<string, mixed>  $line
     */
    public static function lineGross(array $line, float $divisor): float
    {
        $qty = (float) ($line['cantidad'] ?? 0);
        $listPrice = (float) (string) ($line['precio_lista'] ?? 0);

        if ($listPrice > 0 && $qty > 0) {
            $gross = round($qty * $listPrice, 2);
            $descPct = (float) ($line['descuento_pct'] ?? 0);

            if ($descPct > 0) {
                $gross = round($gross * (1 - ($descPct / 100)), 2);
            }

            return max(0.0, $gross);
        }

        return round((float) ($line['subtotal'] ?? 0) * $divisor, 2);
    }
}
