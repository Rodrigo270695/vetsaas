<?php

declare(strict_types=1);

namespace App\Services\Venta;

use App\Models\ConsultaCargo;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\MovimientoInventario;
use App\Models\Venta;
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
        } catch (RuntimeException $bajaError) {
            try {
                $this->felNotaCredito->emitirPorAnulacionVenta($venta, $motivo);
            } catch (RuntimeException $ncError) {
                throw ValidationException::withMessages([
                    'venta' => __('caja.ventas.anulacion.fel_o_nc_error', [
                        'baja' => $bajaError->getMessage(),
                        'nc' => $ncError->getMessage(),
                    ]),
                ]);
            }
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
        $cargos = ConsultaCargo::query()
            ->where('venta_id', $venta->id)
            ->orderBy('created_at')
            ->get();

        foreach ($cargos as $cargo) {
            if ($this->origenYaTienePrecuentaPendiente($cargo)) {
                continue;
            }

            $cargo->update(['venta_id' => null]);
        }

        GroomingTurno::query()
            ->where('venta_id', $venta->id)
            ->update(['venta_id' => null]);

        GroomingTurno::query()
            ->where('adelanto_venta_id', $venta->id)
            ->update([
                'adelanto_venta_id' => null,
                'adelanto_monto' => null,
                'adelanto_at' => null,
            ]);

        HotelEstancia::query()
            ->where('venta_id', $venta->id)
            ->update(['venta_id' => null]);
    }

    /**
     * Solo puede haber una pre-cuenta pendiente (venta_id null) por origen.
     * Si ya abrieron otra hoja tras cobrar, no reabrimos esta al anular.
     */
    private function origenYaTienePrecuentaPendiente(ConsultaCargo $cargo): bool
    {
        foreach (['consulta_id', 'internamiento_id', 'grooming_turno_id', 'hotel_estancia_id', 'vacuna_aplicada_id'] as $col) {
            $origenId = $cargo->{$col};
            if (! is_string($origenId) || $origenId === '') {
                continue;
            }

            return ConsultaCargo::query()
                ->where($col, $origenId)
                ->whereNull('venta_id')
                ->whereKeyNot($cargo->id)
                ->exists();
        }

        return false;
    }
}
