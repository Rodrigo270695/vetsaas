<?php

declare(strict_types=1);

namespace App\Support\Venta;

/**
 * Aplica el descuento manual después de las promociones.
 *
 * De ese modo ambos descuentos se componen sobre el importe vigente y el
 * porcentaje persistido representa el descuento efectivo frente al precio
 * de lista, sin modificar el catálogo del producto o servicio.
 */
final class DescuentoManualLinea
{
    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<array<string, mixed>>  $lineasSolicitadas
     * @return array{lineas: list<array<string, mixed>>, discount_amount: float}
     */
    public static function apply(
        array $lineas,
        array $lineasSolicitadas,
        float $igvPct,
        bool $precioIncluyeIgv,
    ): array {
        $divisor = 1 + ($igvPct / 100);
        $discountAmount = 0.0;

        foreach ($lineas as $idx => $line) {
            $manualPct = self::normalizePct($lineasSolicitadas[$idx]['descuento_pct'] ?? 0);
            $manualAmount = self::normalizeAmount($lineasSolicitadas[$idx]['descuento_monto'] ?? 0);
            if ($manualPct <= 0 && $manualAmount <= 0) {
                continue;
            }

            $qty = (float) ($line['cantidad'] ?? 0);
            $listPrice = (float) (string) ($line['precio_lista'] ?? 0);
            $originalBase = round($qty * $listPrice, 2);

            if ($qty <= 0 || $originalBase <= 0) {
                continue;
            }

            if ($precioIncluyeIgv) {
                $currentGross = round((float) ($line['subtotal'] ?? 0) * $divisor, 2);
                $appliedAmount = $manualAmount > 0
                    ? min($manualAmount, $currentGross)
                    : round($currentGross * ($manualPct / 100), 2);
                $newGross = max(0, round($currentGross - $appliedAmount, 2));
                $newSub = $divisor > 0 ? round($newGross / $divisor, 2) : $newGross;
                $effectivePct = round((1 - ($newGross / $originalBase)) * 100, 2);
                $discountAmount += $appliedAmount;
            } else {
                $currentSub = (float) ($line['subtotal'] ?? 0);
                $currentGross = round($currentSub * $divisor, 2);
                $appliedAmount = $manualAmount > 0
                    ? min($manualAmount, $currentGross)
                    : round($currentGross * ($manualPct / 100), 2);
                $newGross = max(0, round($currentGross - $appliedAmount, 2));
                $newSub = $divisor > 0 ? round($newGross / $divisor, 2) : $newGross;
                $effectivePct = round((1 - ($newSub / $originalBase)) * 100, 2);
                $discountAmount += $appliedAmount;
            }

            $line['subtotal'] = max(0, $newSub);
            $line['precio_unitario'] = $qty > 0 ? round($line['subtotal'] / $qty, 4) : 0.0;
            $line['descuento_pct'] = min(100, max(0, $effectivePct));
            $line['descuento_manual_pct'] = $manualPct;
            $line['descuento_manual_monto'] = $manualAmount;
            $lineas[$idx] = $line;
        }

        return [
            'lineas' => $lineas,
            'discount_amount' => round($discountAmount, 2),
        ];
    }

    private static function normalizePct(mixed $value): float
    {
        $pct = round((float) (string) $value, 2);

        return min(100, max(0, $pct));
    }

    private static function normalizeAmount(mixed $value): float
    {
        return max(0, round((float) (string) $value, 2));
    }
}
