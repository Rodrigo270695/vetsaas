<?php

namespace App\Support\PlanTratamiento;

use App\Models\ConsultaPlanTratamientoLinea;
use App\Models\MovimientoInventario;
use App\Services\Inventario\InventarioLoteService;

/**
 * Descuenta o revierte stock al vincular una línea del plan con producto + cantidad + sede.
 */
final class PlanTratamientoStockSync
{
    public function __construct(
        private readonly InventarioLoteService $lotes,
    ) {}

    public static function debeDescontar(ConsultaPlanTratamientoLinea $linea, ?string $sedeId): bool
    {
        if ($sedeId === null || $sedeId === '') {
            return false;
        }

        if ($linea->producto_id === null) {
            return false;
        }

        $cant = (float) (string) ($linea->cantidad ?? 0);

        return $cant > 0;
    }

    /**
     * @return list<MovimientoInventario>
     */
    public function registrarSalida(
        ConsultaPlanTratamientoLinea $linea,
        string $sedeId,
        ?string $userId,
    ): array {
        $cantidad = number_format((float) (string) $linea->cantidad, 3, '.', '');

        $notas = __('historias-clinicas.plan.stock.notas', [
            'medicamento' => $linea->medicamento,
            'consulta' => $linea->plan?->consulta_id ?? '—',
        ]);

        return $this->lotes->descontarFefo(
            (string) $linea->producto_id,
            $sedeId,
            $cantidad,
            $notas,
            $userId,
        );
    }

    public function revertirLinea(ConsultaPlanTratamientoLinea $linea, ?string $userId): void
    {
        if ($linea->movimiento_inventario_id === null) {
            return;
        }

        $mov = MovimientoInventario::query()->find($linea->movimiento_inventario_id);
        if ($mov === null) {
            return;
        }

        $this->lotes->revertirMovimiento($mov, $userId);
    }
}
