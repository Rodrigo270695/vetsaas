<?php

namespace App\Services\Inventario;

use App\Models\MovimientoInventario;
use App\Models\ProductoLote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de stock por lote con criterio FEFO (First Expired, First Out).
 */
final class InventarioLoteService
{
    public const LOTE_SIN_ESPECIFICAR = 'SIN-LOTE';

    public static function normalizarNumeroLote(?string $numero): string
    {
        $n = trim((string) $numero);

        return $n === '' ? self::LOTE_SIN_ESPECIFICAR : mb_substr($n, 0, 128);
    }

    /**
     * Registra entrada de mercadería en un lote y actualiza existencias agregadas.
     */
    public function registrarEntrada(
        string $productoId,
        string $sedeId,
        string $cantidad,
        ?string $numeroLote,
        ?string $fechaVencimiento,
        ?string $notas,
        ?string $userId,
        ?string $compraId = null,
        ?string $compraLineaId = null,
    ): MovimientoInventario {
        $cantidadNum = round((float) $cantidad, 3);
        if ($cantidadNum <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad debe ser mayor a cero.',
            ]);
        }

        $loteKey = self::normalizarNumeroLote($numeroLote);
        $venc = $fechaVencimiento !== null && $fechaVencimiento !== '' ? $fechaVencimiento : null;

        $lote = ProductoLote::query()
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->where('numero_lote', $loteKey)
            ->where(function ($q) use ($venc): void {
                if ($venc === null) {
                    $q->whereNull('fecha_vencimiento');
                } else {
                    $q->whereDate('fecha_vencimiento', $venc);
                }
            })
            ->lockForUpdate()
            ->first();

        if ($lote === null) {
            $lote = ProductoLote::query()->create([
                'producto_id' => $productoId,
                'sede_id' => $sedeId,
                'numero_lote' => $loteKey,
                'fecha_vencimiento' => $venc,
                'cantidad' => 0,
                'compra_linea_id' => $compraLineaId,
            ]);
        }

        $lote->update([
            'cantidad' => round((float) (string) $lote->cantidad + $cantidadNum, 3),
        ]);

        return MovimientoInventario::aplicar(
            $productoId,
            $sedeId,
            MovimientoInventario::TIPO_ENTRADA,
            (string) $cantidadNum,
            $notas,
            $userId,
            $compraId,
            null,
            (string) $lote->id,
        );
    }

    /**
     * Descuenta stock usando FEFO. Devuelve uno o más movimientos de salida.
     *
     * @return list<MovimientoInventario>
     */
    public function descontarFefo(
        string $productoId,
        string $sedeId,
        string $cantidad,
        ?string $notas,
        ?string $userId,
        ?string $ventaId = null,
    ): array {
        $pendiente = round((float) $cantidad, 3);
        if ($pendiente <= 0) {
            return [];
        }

        $this->asegurarLotesDesdeExistencia($productoId, $sedeId);

        $lotes = ProductoLote::query()
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->where('cantidad', '>', 0)
            ->orderByRaw('fecha_vencimiento IS NULL')
            ->orderBy('fecha_vencimiento')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        $movimientos = [];

        foreach ($lotes as $lote) {
            if ($pendiente <= 0) {
                break;
            }

            $disponible = round((float) (string) $lote->cantidad, 3);
            if ($disponible <= 0) {
                continue;
            }

            $tomar = min($pendiente, $disponible);
            $lote->update(['cantidad' => round($disponible - $tomar, 3)]);

            $notasLote = trim(($notas ?? '').' · Lote '.$lote->numero_lote);

            $movimientos[] = MovimientoInventario::aplicar(
                $productoId,
                $sedeId,
                MovimientoInventario::TIPO_SALIDA,
                (string) (-1 * $tomar),
                $notasLote,
                $userId,
                null,
                $ventaId,
                (string) $lote->id,
            );

            $pendiente = round($pendiente - $tomar, 3);
        }

        if ($pendiente > 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente para completar la salida (faltan '.number_format($pendiente, 3, '.', '').' u.).',
            ]);
        }

        return $movimientos;
    }

    /**
     * Revierte un movimiento (entrada ↔ salida) ajustando el lote vinculado.
     */
    public function revertirMovimiento(MovimientoInventario $movimiento, ?string $userId, ?string $notasExtra = null): MovimientoInventario
    {
        $delta = round((float) (string) $movimiento->delta, 3);
        $cantidad = abs($delta);
        $tipoReverso = $delta < 0
            ? MovimientoInventario::TIPO_ENTRADA
            : MovimientoInventario::TIPO_SALIDA;

        if ($movimiento->producto_lote_id !== null) {
            $lote = ProductoLote::query()
                ->whereKey($movimiento->producto_lote_id)
                ->lockForUpdate()
                ->first();

            if ($lote !== null) {
                $nuevaCant = round((float) (string) $lote->cantidad + ($delta < 0 ? $cantidad : -$cantidad), 3);
                if ($nuevaCant < 0) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'No se puede revertir: el lote quedaría negativo.',
                    ]);
                }
                $lote->update(['cantidad' => $nuevaCant]);
            }
        }

        $notas = trim(__('inventario.lotes.reversion', ['id' => $movimiento->id]).($notasExtra ? ' · '.$notasExtra : ''));

        return MovimientoInventario::aplicar(
            $movimiento->producto_id,
            $movimiento->sede_id,
            $tipoReverso,
            (string) ($delta < 0 ? $cantidad : -$cantidad),
            $notas,
            $userId,
            null,
            null,
            $movimiento->producto_lote_id,
        );
    }

    /**
     * Revierte entradas de una compra anulada (por movimientos vinculados).
     */
    public function revertirEntradasCompra(string $compraId, ?string $userId, string $notasBase): void
    {
        $movimientos = MovimientoInventario::query()
            ->where('compra_id', $compraId)
            ->where('tipo', MovimientoInventario::TIPO_ENTRADA)
            ->orderBy('created_at')
            ->get();

        foreach ($movimientos as $mov) {
            $this->revertirMovimiento($mov, $userId, $notasBase);
        }
    }

    /**
     * @return Collection<int, ProductoLote>
     */
    public function lotesDisponibles(string $productoId, string $sedeId): Collection
    {
        return ProductoLote::query()
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->where('cantidad', '>', 0)
            ->orderByRaw('fecha_vencimiento IS NULL')
            ->orderBy('fecha_vencimiento')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Si hay existencia agregada pero sin lotes, crea bucket LEGACY.
     */
    private function asegurarLotesDesdeExistencia(string $productoId, string $sedeId): void
    {
        $tieneLotes = ProductoLote::query()
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->where('cantidad', '>', 0)
            ->exists();

        if ($tieneLotes) {
            return;
        }

        $existencia = \App\Models\ExistenciaSede::query()
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->value('cantidad');

        $qty = round((float) (string) ($existencia ?? 0), 3);
        if ($qty <= 0) {
            return;
        }

        ProductoLote::query()->create([
            'producto_id' => $productoId,
            'sede_id' => $sedeId,
            'numero_lote' => 'STOCK-INICIAL',
            'fecha_vencimiento' => null,
            'cantidad' => $qty,
        ]);
    }
}
