<?php

namespace App\Support\Fel;

use App\Models\FelSerie;
use App\Models\Venta;
use RuntimeException;

/**
 * Resuelve la serie SUNAT activa de {@see FelSerie} para la sede de la venta.
 */
final class FelSerieResolver
{
    /**
     * Código de serie que se usaría al emitir (sin bloquear filas).
     */
    public function codigoSerieParaVenta(Venta $venta, int $tipoComprobante): ?string
    {
        return $this->queryParaVenta($venta, $tipoComprobante, false)->value('serie');
    }

    public function resolverParaVenta(Venta $venta, int $tipoComprobante, bool $forUpdate = false): FelSerie
    {
        $query = $this->queryParaVenta($venta, $tipoComprobante, $forUpdate);

        $serie = $query->first();

        if ($serie === null) {
            $tipo = match ($tipoComprobante) {
                FelSerie::TIPO_FACTURA => 'factura',
                FelSerie::TIPO_BOLETA => 'boleta',
                FelSerie::TIPO_NOTA_CREDITO => 'nota de crédito',
                default => 'comprobante',
            };

            throw new RuntimeException(__('caja.ventas.fel.sin_serie', ['tipo' => $tipo]));
        }

        return $serie;
    }

    private function queryParaVenta(Venta $venta, int $tipoComprobante, bool $forUpdate)
    {
        $venta->loadMissing('sede');

        $query = FelSerie::query()
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('activo', true)
            ->where('sede_id', $venta->sede_id)
            ->orderBy('serie');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query;
    }
}
