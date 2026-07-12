<?php

namespace App\Support\Vacunas;

use App\Models\MovimientoInventario;
use App\Models\VacunaAplicada;
use App\Services\Inventario\InventarioLoteService;

/**
 * Descuenta o revierte stock al vincular una vacunación con producto + sede.
 * Una aplicación consume 1 unidad del producto en la sede indicada.
 */
final class VacunaAplicadaStockSync
{
    /** Cantidad fija por registro de vacunación (1 dosis = 1 unidad de catálogo). */
    public const CANTIDAD_POR_APLICACION = '1';

    public static function debeDescontar(VacunaAplicada $vacuna): bool
    {
        return $vacuna->producto_id !== null && $vacuna->sede_id !== null;
    }

    /**
     * Registra salida de inventario y devuelve el movimiento creado.
     */
    public static function registrarSalida(VacunaAplicada $vacuna, ?string $userId): MovimientoInventario
    {
        $vacuna->loadMissing('paciente:id,nombre');

        $notas = __('vacunaciones.stock.notas', [
            'paciente' => $vacuna->paciente?->nombre ?? '—',
            'vacuna' => $vacuna->nombre_vacuna,
            'id' => $vacuna->id,
        ]);

        $movimientos = app(InventarioLoteService::class)->descontarFefo(
            (string) $vacuna->producto_id,
            (string) $vacuna->sede_id,
            self::CANTIDAD_POR_APLICACION,
            $notas,
            $userId,
        );

        return $movimientos[0];
    }

    /**
     * Compensa en stock el movimiento de salida vinculado (incluye grupo FEFO).
     */
    public static function revertirPorMovimiento(MovimientoInventario $movimiento, ?string $userId): MovimientoInventario
    {
        $revertidos = app(InventarioLoteService::class)->revertirSalidaFefoDesdeReferencia($movimiento, $userId);

        return $revertidos !== [] ? $revertidos[array_key_last($revertidos)] : $movimiento;
    }
}
