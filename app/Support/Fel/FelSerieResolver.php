<?php

namespace App\Support\Fel;

use App\Models\FelSerie;
use App\Models\Sede;
use App\Models\Venta;
use RuntimeException;

/**
 * Resuelve la serie SUNAT a usar al emitir: primero la sede de la venta,
 * luego el catálogo legacy {@see FelSerie} (B001/F001 sembrados).
 */
final class FelSerieResolver
{
    public function resolverParaVenta(Venta $venta, int $tipoComprobante, bool $forUpdate = false): FelSerie
    {
        $venta->loadMissing('sede');

        $codigoSede = $this->codigoSerieDesdeSede($venta->sede, $tipoComprobante);

        if ($codigoSede !== null) {
            return $this->obtenerOCrearSerie($tipoComprobante, $codigoSede, $forUpdate);
        }

        $query = FelSerie::query()
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('activo', true)
            ->orderBy('serie');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $serie = $query->first();

        if ($serie === null) {
            $tipo = $tipoComprobante === FelSerie::TIPO_FACTURA ? 'factura' : 'boleta';

            throw new RuntimeException(__('caja.ventas.fel.sin_serie', ['tipo' => $tipo]));
        }

        return $serie;
    }

    private function codigoSerieDesdeSede(?Sede $sede, int $tipoComprobante): ?string
    {
        if ($sede === null || ! $sede->activa) {
            return null;
        }

        $raw = $tipoComprobante === FelSerie::TIPO_FACTURA
            ? $sede->serie_factura
            : $sede->serie_boleta;

        $codigo = SunatSerieCodigo::normalizar((string) $raw);

        return $codigo;
    }

    private function obtenerOCrearSerie(int $tipoComprobante, string $codigoSerie, bool $forUpdate): FelSerie
    {
        $query = FelSerie::query()
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('serie', $codigoSerie);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $existente = $query->first();

        if ($existente !== null) {
            if (! $existente->activo) {
                $existente->update(['activo' => true]);
            }

            return $existente;
        }

        return FelSerie::query()->create([
            'tipo_comprobante' => $tipoComprobante,
            'serie' => $codigoSerie,
            'ultimo_correlativo' => 0,
            'activo' => true,
        ]);
    }
}
