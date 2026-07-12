<?php

namespace App\Services\Inventario;

use App\Models\ExistenciaSede;
use App\Models\MovimientoInventario;
use App\Models\ProductoLote;
use App\Models\Sede;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        ?string $trasladoGrupoId = null,
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
            null,
            $trasladoGrupoId,
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
        ?string $trasladoGrupoId = null,
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
        $fefoGrupoId = (string) Str::uuid();

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
                $fefoGrupoId,
                $trasladoGrupoId,
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
     * Traslado FEFO: sale de origen y entra en destino preservando lote/vencimiento.
     *
     * @return list<MovimientoInventario>
     */
    public function registrarTraslado(
        string $productoId,
        string $sedeOrigenId,
        string $sedeDestinoId,
        string $cantidad,
        ?string $notas,
        ?string $userId,
    ): array {
        if ($sedeOrigenId === $sedeDestinoId) {
            throw ValidationException::withMessages([
                'sede_destino_id' => 'La sede de destino debe ser distinta a la de origen.',
            ]);
        }

        return DB::transaction(function () use ($productoId, $sedeOrigenId, $sedeDestinoId, $cantidad, $notas, $userId): array {
            $origen = Sede::query()->find($sedeOrigenId);
            $destino = Sede::query()->find($sedeDestinoId);
            $origenLabel = $origen?->nombre ?? $sedeOrigenId;
            $destinoLabel = $destino?->nombre ?? $sedeDestinoId;

            $grupo = (string) Str::uuid();
            $notaBase = trim((string) ($notas ?? ''));
            $notaSalida = trim(($notaBase !== '' ? $notaBase.' · ' : '').'Traslado → '.$destinoLabel);
            $notaEntrada = trim(($notaBase !== '' ? $notaBase.' · ' : '').'Traslado ← '.$origenLabel);

            $salidas = $this->descontarFefo(
                $productoId,
                $sedeOrigenId,
                $cantidad,
                $notaSalida,
                $userId,
                null,
                $grupo,
            );

            $creados = $salidas;

            foreach ($salidas as $salida) {
                $salida->loadMissing('productoLote');
                $lote = $salida->productoLote;
                $qty = abs(round((float) (string) $salida->delta, 3));
                $venc = $lote?->fecha_vencimiento?->format('Y-m-d');

                $creados[] = $this->registrarEntrada(
                    $productoId,
                    $sedeDestinoId,
                    (string) $qty,
                    $lote?->numero_lote,
                    $venc,
                    $notaEntrada.($lote ? ' · Lote '.$lote->numero_lote : ''),
                    $userId,
                    null,
                    null,
                    $grupo,
                );
            }

            return $creados;
        });
    }

    /**
     * Ajusta existencias a una cantidad objetivo usando lotes:
     * sube → entrada SIN-LOTE; baja → descuento FEFO.
     */
    public function ajustarACantidad(
        string $productoId,
        string $sedeId,
        string $cantidadObjetivo,
        ?string $notas,
        ?string $userId,
    ): void {
        $anterior = ExistenciaSede::query()
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->value('cantidad');
        $anteriorF = round((float) (string) ($anterior ?? 0), 3);
        $nuevoF = round((float) $cantidadObjetivo, 3);
        $delta = round($nuevoF - $anteriorF, 3);

        if (abs($delta) < 0.0000001) {
            ExistenciaSede::query()->updateOrCreate(
                [
                    'producto_id' => $productoId,
                    'sede_id' => $sedeId,
                ],
                ['cantidad' => $nuevoF],
            );

            return;
        }

        $nota = $notas !== null && trim($notas) !== '' ? $notas : 'Ajuste de stock';

        if ($delta > 0) {
            $this->registrarEntrada(
                $productoId,
                $sedeId,
                (string) $delta,
                self::LOTE_SIN_ESPECIFICAR,
                null,
                $nota,
                $userId,
            );

            return;
        }

        $this->descontarFefo(
            $productoId,
            $sedeId,
            (string) abs($delta),
            $nota,
            $userId,
        );
    }

    /**
     * Movimiento manual desde kardex (entrada con lote opcional; salida/merma con FEFO).
     */
    public function registrarMovimientoManual(
        string $tipo,
        string $productoId,
        string $sedeId,
        string $cantidad,
        ?string $notas,
        ?string $userId,
        ?string $numeroLote = null,
        ?string $fechaVencimiento = null,
    ): MovimientoInventario {
        if ($tipo === MovimientoInventario::TIPO_ENTRADA) {
            return $this->registrarEntrada(
                $productoId,
                $sedeId,
                $cantidad,
                $numeroLote,
                $fechaVencimiento,
                $notas,
                $userId,
            );
        }

        if (! in_array($tipo, [MovimientoInventario::TIPO_SALIDA, MovimientoInventario::TIPO_MERMA], true)) {
            throw ValidationException::withMessages([
                'tipo' => 'Tipo de movimiento inválido.',
            ]);
        }

        $notasTipo = trim(($notas ?? '').($tipo === MovimientoInventario::TIPO_MERMA ? ' · Merma' : ''));

        $movimientos = $this->descontarFefo(
            $productoId,
            $sedeId,
            $cantidad,
            $notasTipo !== '' ? $notasTipo : null,
            $userId,
        );

        return $movimientos[0];
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
     * Revierte una salida FEFO completa: todos los movimientos del mismo `fefo_grupo_id`,
     * o solo el movimiento de referencia si no hay grupo (datos legacy).
     *
     * @return list<MovimientoInventario>
     */
    public function revertirSalidaFefoDesdeReferencia(
        MovimientoInventario $referencia,
        ?string $userId,
        ?string $notasExtra = null,
    ): array {
        $salidas = $this->salidasDelGrupoFefo($referencia);
        $revertidos = [];

        foreach ($salidas as $mov) {
            if ($this->salidaYaRevertida($mov)) {
                continue;
            }

            $revertidos[] = $this->revertirMovimiento($mov, $userId, $notasExtra);
        }

        return $revertidos;
    }

    /**
     * @return \Illuminate\Support\Collection<int, MovimientoInventario>
     */
    public function salidasDelGrupoFefo(MovimientoInventario $referencia): Collection
    {
        $grupoId = $referencia->fefo_grupo_id;

        if (! is_string($grupoId) || $grupoId === '') {
            return collect([$referencia]);
        }

        return MovimientoInventario::query()
            ->where('fefo_grupo_id', $grupoId)
            ->whereIn('tipo', [MovimientoInventario::TIPO_SALIDA, MovimientoInventario::TIPO_MERMA])
            ->where('delta', '<', 0)
            ->orderBy('created_at')
            ->get();
    }

    public function salidaYaRevertida(MovimientoInventario $movimiento): bool
    {
        $needle = (string) $movimiento->id;

        return MovimientoInventario::query()
            ->where('producto_id', $movimiento->producto_id)
            ->where('sede_id', $movimiento->sede_id)
            ->where('tipo', MovimientoInventario::TIPO_ENTRADA)
            ->where('notas', 'like', '%'.$needle.'%')
            ->exists();
    }

    /**
     * Asocia `venta_id` a todas las salidas del grupo FEFO (o al movimiento suelto).
     */
    public function vincularSalidaFefoAVenta(MovimientoInventario $referencia, string $ventaId): void
    {
        $ids = $this->salidasDelGrupoFefo($referencia)->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        MovimientoInventario::query()
            ->whereIn('id', $ids)
            ->whereNull('venta_id')
            ->update(['venta_id' => $ventaId]);
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
