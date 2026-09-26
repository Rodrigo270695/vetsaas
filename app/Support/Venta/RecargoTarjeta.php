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
     * Suma el recargo al precio de las líneas ya vendidas y lo carga en el pago con tarjeta.
     * No crea un ítem propio: ticket, boleta y factura solo muestran el producto o servicio.
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
        if ($recargo < 0.01 || $lineas === []) {
            return ['lineas' => $lineas, 'pagos' => $pagos, 'aplicado' => false];
        }

        $antes = VentaTotales::fromLineas($lineas, $igvPct, $precioIncluyeIgv);
        $objetivo = round($antes['total'] + $recargo, 2);
        $divisor = 1 + ($igvPct / 100);
        $indices = self::indicesParaRecargo($lineas, $igvTipo, $precioIncluyeIgv, $divisor);
        if ($indices === []) {
            return ['lineas' => $lineas, 'pagos' => $pagos, 'aplicado' => false];
        }

        $pesos = [];
        $sumaPesos = 0.0;
        foreach ($indices as $i) {
            $peso = self::pesoLinea($lineas[$i], $precioIncluyeIgv, $divisor);
            $pesos[$i] = $peso;
            $sumaPesos += $peso;
        }

        if ($sumaPesos <= 0) {
            return ['lineas' => $lineas, 'pagos' => $pagos, 'aplicado' => false];
        }

        $asignado = 0.0;
        $ultimo = $indices[array_key_last($indices)];
        foreach ($indices as $i) {
            $share = $i === $ultimo
                ? round($recargo - $asignado, 2)
                : round($recargo * ($pesos[$i] / $sumaPesos), 2);
            $asignado = round($asignado + $share, 2);
            $lineas[$i] = self::sumarBruto($lineas[$i], $share, $divisor, $precioIncluyeIgv);
        }

        for ($n = 0; $n < 6; $n++) {
            $total = VentaTotales::fromLineas($lineas, $igvPct, $precioIncluyeIgv)['total'];
            $drift = round($objetivo - $total, 2);
            if (abs($drift) < 0.009) {
                break;
            }
            $lineas[$ultimo] = self::sumarBruto($lineas[$ultimo], $drift, $divisor, $precioIncluyeIgv);
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

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<int>
     */
    private static function indicesParaRecargo(
        array $lineas,
        string $igvTipo,
        bool $precioIncluyeIgv,
        float $divisor,
    ): array {
        $positivos = [];
        $mismoTipo = [];
        foreach ($lineas as $i => $line) {
            if (self::pesoLinea($line, $precioIncluyeIgv, $divisor) <= 0) {
                continue;
            }
            $positivos[] = $i;
            $tipo = isset($line['igv_tipo_snapshot']) && is_string($line['igv_tipo_snapshot'])
                ? $line['igv_tipo_snapshot']
                : '';
            if ($tipo === $igvTipo) {
                $mismoTipo[] = $i;
            }
        }

        return $mismoTipo !== [] ? $mismoTipo : $positivos;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function pesoLinea(array $line, bool $precioIncluyeIgv, float $divisor): float
    {
        if ($precioIncluyeIgv) {
            return max(0.0, VentaTotales::lineGross($line, $divisor));
        }

        return max(0.0, (float) ($line['subtotal'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private static function sumarBruto(
        array $line,
        float $share,
        float $divisor,
        bool $precioIncluyeIgv,
    ): array {
        if (abs($share) < 0.001) {
            return $line;
        }

        $qty = (float) ($line['cantidad'] ?? 0);
        if ($qty <= 0) {
            return $line;
        }

        $desc = (float) ($line['descuento_pct'] ?? 0);
        $factor = $qty * (1 - ($desc / 100));
        if ($factor <= 0) {
            return $line;
        }

        if ($precioIncluyeIgv) {
            $newGross = max(0.0, round(VentaTotales::lineGross($line, $divisor) + $share, 2));
            $line['precio_lista'] = round($newGross / $factor, 2);
            $actual = VentaTotales::lineGross($line, $divisor);
            $drift = round($newGross - $actual, 2);
            if (abs($drift) >= 0.01) {
                $line['precio_lista'] = round((float) $line['precio_lista'] + ($drift / $factor), 2);
            }
            $grossFinal = VentaTotales::lineGross($line, $divisor);
            $line['subtotal'] = $divisor > 0 ? round($grossFinal / $divisor, 2) : $grossFinal;
        } else {
            $neto = $divisor > 0 ? round($share / $divisor, 2) : $share;
            if (abs($neto) < 0.01) {
                $neto = $share > 0 ? 0.01 : -0.01;
            }
            $line['subtotal'] = max(0.0, round((float) ($line['subtotal'] ?? 0) + $neto, 2));
            $line['precio_lista'] = round((float) $line['subtotal'] / $factor, 2);
        }

        $line['precio_unitario'] = round((float) $line['subtotal'] / $qty, 4);

        return $line;
    }
}
