<?php

namespace App\Support\ConsultaCargo;

use App\Models\ConsultaCargoLinea;
use App\Models\MovimientoInventario;
use App\Services\Inventario\InventarioLoteService;

/**
 * Descuenta o revierte stock al confirmar cargos de consulta con productos de inventario.
 */
final class ConsultaCargoStockSync
{
    public function __construct(
        private readonly InventarioLoteService $lotes,
    ) {}

    public static function debeDescontar(ConsultaCargoLinea $linea, ?string $sedeId): bool
    {
        if ($sedeId === null || $sedeId === '') {
            return false;
        }

        if ($linea->tipo_linea !== ConsultaCargoLinea::TIPO_PRODUCTO || $linea->producto_id === null) {
            return false;
        }

        $cant = (float) (string) $linea->cantidad;

        return $cant > 0;
    }

    /**
     * @return list<MovimientoInventario>
     */
    public function registrarSalida(
        ConsultaCargoLinea $linea,
        string $sedeId,
        ?string $userId,
    ): array {
        $linea->loadMissing('cargo:id,consulta_id');

        $cantidad = number_format((float) (string) $linea->cantidad, 3, '.', '');

        $notas = __('consulta-cargos.stock.notas', [
            'concepto' => $linea->concepto,
            'consulta' => $linea->cargo?->consulta_id ?? '—',
        ]);

        return $this->lotes->descontarFefo(
            (string) $linea->producto_id,
            $sedeId,
            $cantidad,
            $notas,
            $userId,
        );
    }

    public function revertirLinea(ConsultaCargoLinea $linea, ?string $userId): void
    {
        if ($linea->movimiento_inventario_id === null) {
            return;
        }

        $mov = MovimientoInventario::query()->find($linea->movimiento_inventario_id);
        if ($mov === null) {
            return;
        }

        $this->lotes->revertirSalidaFefoDesdeReferencia($mov, $userId);
    }
}
