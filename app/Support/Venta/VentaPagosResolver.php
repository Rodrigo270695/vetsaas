<?php

declare(strict_types=1);

namespace App\Support\Venta;

use App\Models\VentaPago;
use Illuminate\Validation\ValidationException;

/**
 * Normaliza y valida el desglose de pagos de una venta (simple o mixto).
 */
final class VentaPagosResolver
{
    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{metodo: string, monto: float, monto_recibido: ?float, vuelto: ?float}>
     */
    public static function fromValidated(array $validated, float $totalVenta): array
    {
        $raw = $validated['pagos'] ?? null;
        if (! is_array($raw) || $raw === []) {
            $metodo = (string) ($validated['metodo_pago'] ?? '');
            if (! in_array($metodo, VentaPago::METODOS, true)) {
                throw ValidationException::withMessages([
                    'metodo_pago' => __('caja.ventas.validation.metodo_pago_invalido'),
                ]);
            }
            $raw = [[
                'metodo' => $metodo,
                'monto' => $totalVenta,
                'monto_recibido' => $validated['monto_recibido'] ?? null,
            ]];
        }

        $lineas = [];
        $metodosVistos = [];

        foreach (array_values($raw) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $metodo = (string) ($row['metodo'] ?? '');
            if (! in_array($metodo, VentaPago::METODOS, true)) {
                throw ValidationException::withMessages([
                    "pagos.{$i}.metodo" => __('caja.ventas.validation.metodo_pago_invalido'),
                ]);
            }
            if (isset($metodosVistos[$metodo])) {
                throw ValidationException::withMessages([
                    "pagos.{$i}.metodo" => __('caja.ventas.validation.pago_metodo_duplicado'),
                ]);
            }
            $metodosVistos[$metodo] = true;

            $monto = round((float) (string) ($row['monto'] ?? 0), 2);
            if ($monto <= 0) {
                throw ValidationException::withMessages([
                    "pagos.{$i}.monto" => __('caja.ventas.validation.pago_monto_invalido'),
                ]);
            }

            $montoRecibido = null;
            $vuelto = null;
            if ($metodo === VentaPago::METODO_EFECTIVO) {
                $mrRaw = $row['monto_recibido'] ?? null;
                if ($mrRaw === null || $mrRaw === '') {
                    // Pago mixto: si no indican billete, se asume exacto.
                    $montoRecibido = $monto;
                } else {
                    $montoRecibido = round((float) (string) $mrRaw, 2);
                }
                if ($montoRecibido + 0.0001 < $monto) {
                    throw ValidationException::withMessages([
                        count($raw) === 1 ? 'monto_recibido' : "pagos.{$i}.monto_recibido"
                            => __('caja.ventas.validation.monto_insuficiente'),
                    ]);
                }
                $vuelto = round(max(0, $montoRecibido - $monto), 2);
            }

            $lineas[] = [
                'metodo' => $metodo,
                'monto' => $monto,
                'monto_recibido' => $montoRecibido,
                'vuelto' => $vuelto,
            ];
        }

        if ($lineas === []) {
            throw ValidationException::withMessages([
                'pagos' => __('caja.ventas.validation.pagos_requeridos'),
            ]);
        }

        $suma = round(array_sum(array_column($lineas, 'monto')), 2);
        if (abs($suma - round($totalVenta, 2)) > 0.01) {
            throw ValidationException::withMessages([
                'pagos' => __('caja.ventas.validation.pagos_no_cuadran', [
                    'suma' => number_format($suma, 2, '.', ''),
                    'total' => number_format($totalVenta, 2, '.', ''),
                ]),
            ]);
        }

        return $lineas;
    }

    /**
     * @param  list<array{metodo: string, monto: float, monto_recibido: ?float, vuelto: ?float}>  $lineas
     */
    public static function metodoResumen(array $lineas): string
    {
        if (count($lineas) === 1) {
            return $lineas[0]['metodo'];
        }

        return 'mixto';
    }

    /**
     * @param  list<array{metodo: string, monto: float, monto_recibido: ?float, vuelto: ?float}>  $lineas
     * @return array{monto_recibido: ?float, vuelto: ?float}
     */
    public static function efectivoSnapshot(array $lineas): array
    {
        foreach ($lineas as $linea) {
            if ($linea['metodo'] === VentaPago::METODO_EFECTIVO) {
                return [
                    'monto_recibido' => $linea['monto_recibido'],
                    'vuelto' => $linea['vuelto'],
                ];
            }
        }

        return ['monto_recibido' => null, 'vuelto' => null];
    }
}
