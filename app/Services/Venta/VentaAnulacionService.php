<?php

declare(strict_types=1);

namespace App\Services\Venta;

use App\Models\ConsultaCargo;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\MovimientoInventario;
use App\Models\Venta;
use App\Models\VentaLinea;
use App\Models\ConsultaCargoLinea;
use App\Services\Fel\FelAnulacionComprobanteService;
use App\Services\Fel\FelNotaCreditoComprobanteService;
use App\Services\Inventario\InventarioLoteService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class VentaAnulacionService
{
    public function __construct(
        private readonly FelAnulacionComprobanteService $felAnulacion,
        private readonly FelNotaCreditoComprobanteService $felNotaCredito,
        private readonly InventarioLoteService $lotes,
    ) {}

    /**
     * @param  array{motivo: string}  $input
     */
    public function anular(Venta $venta, array $input, Authenticatable $user): Venta
    {
        $motivo = trim((string) ($input['motivo'] ?? ''));
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => __('caja.ventas.anulacion.motivo_requerido'),
            ]);
        }

        return DB::transaction(function () use ($venta, $motivo, $user): Venta {
            $venta = Venta::query()
                ->whereKey($venta->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($venta->estaAnulada()) {
                throw ValidationException::withMessages([
                    'venta' => __('caja.ventas.anulacion.ya_anulada'),
                ]);
            }

            if ($venta->estado !== Venta::ESTADO_PAGADO) {
                throw ValidationException::withMessages([
                    'venta' => __('caja.ventas.anulacion.solo_pagadas'),
                ]);
            }

            $venta->load(['lineas', 'felDocument']);

            if ($this->felAnulacion->requiereAnulacionSunat($venta)) {
                $this->anularFelEnSunat($venta, $motivo);
                $venta->refresh();
            } elseif (in_array($venta->fel_estado, [Venta::FEL_PENDIENTE, Venta::FEL_RECHAZADO], true)) {
                $venta->update(['fel_estado' => Venta::FEL_ANULADO]);
            } elseif ($venta->fel_estado === Venta::FEL_EMITIDO) {
                throw ValidationException::withMessages([
                    'venta' => __('caja.ventas.anulacion.fel_emitido_sin_documento'),
                ]);
            }

            $this->revertirStock($venta, $user);
            $this->liberarVinculos($venta);

            $venta->update([
                'estado' => Venta::ESTADO_ANULADO,
                'anulado_at' => now(),
                'anulado_por_id' => (string) $user->getAuthIdentifier(),
                'motivo_anulacion' => mb_substr($motivo, 0, 2000),
            ]);

            return $venta->fresh(['lineas', 'felDocument']);
        });
    }

    private function anularFelEnSunat(Venta $venta, string $motivo): void
    {
        try {
            $this->felAnulacion->anularEnSunat($venta);
        } catch (RuntimeException) {
            $this->felNotaCredito->emitirPorAnulacionVenta($venta, $motivo);
        }
    }

    private function revertirStock(Venta $venta, Authenticatable $user): void
    {
        $movimientos = MovimientoInventario::query()
            ->where('venta_id', (string) $venta->id)
            ->where('tipo', MovimientoInventario::TIPO_SALIDA)
            ->orderBy('created_at')
            ->get();

        foreach ($movimientos as $movimiento) {
            $this->lotes->revertirMovimiento(
                $movimiento,
                (string) $user->getAuthIdentifier(),
                __('caja.ventas.anulacion.movimiento_notas', ['numero' => $venta->numero]),
            );
        }
    }

    private function liberarVinculos(Venta $venta): void
    {
        if ($venta->consulta_cargo_id !== null) {
            ConsultaCargo::query()
                ->whereKey($venta->consulta_cargo_id)
                ->where('venta_id', $venta->id)
                ->update(['venta_id' => null]);
        }

        GroomingTurno::query()
            ->where('venta_id', $venta->id)
            ->update(['venta_id' => null]);

        HotelEstancia::query()
            ->where('venta_id', $venta->id)
            ->update(['venta_id' => null]);
    }
}
