<?php

namespace App\Services\Venta;

use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\ConsultaCargo;
use App\Models\ConsultaCargoLinea;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Tenant;
use App\Jobs\EmitirFelVentaJob;
use App\Models\Venta;
use App\Models\VentaLinea;
use App\Support\PlanCapabilities;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VentaCheckoutService
{
    /**
     * Registra una venta pagada, líneas, correlativo y salidas de inventario.
     *
     * @param  array<string, mixed>  $validated
     */
    public function registrar(array $validated, Authenticatable $user, ?Tenant $tenant): Venta
    {
        $clinic = ClinicSetting::current();
        $igvPct = (float) (string) $clinic->igv_porcentaje;
        $precioIncluyeIgv = (bool) $clinic->precio_incluye_igv;
        $moneda = (string) $clinic->moneda;
        if ($moneda !== 'PEN' && $moneda !== 'USD') {
            $moneda = 'PEN';
        }

        $felPendiente = PlanCapabilities::facturaElectronica($tenant)
            && (bool) $clinic->emite_comprobantes_sunat
            && (bool) $clinic->nubefact_configurado;

        $tenantSlug = $tenant?->slug;

        return DB::transaction(function () use ($validated, $user, $igvPct, $precioIncluyeIgv, $moneda, $felPendiente, $tenantSlug): Venta {
            $sesion = CajaSesion::query()
                ->whereKey($validated['caja_sesion_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $sesion->estaAbierta() || (string) $sesion->opened_by_id !== (string) $user->getAuthIdentifier()) {
                throw ValidationException::withMessages([
                    'caja_sesion_id' => __('caja.ventas.validation.sesion_invalida'),
                ]);
            }

            $groomingTurnoLocked = null;
            $groomingId = $validated['grooming_turno_id'] ?? null;
            if (is_string($groomingId) && $groomingId !== '') {
                $groomingTurnoLocked = GroomingTurno::query()->whereKey($groomingId)->lockForUpdate()->first();
                if ($groomingTurnoLocked === null
                    || $groomingTurnoLocked->venta_id !== null
                    || $groomingTurnoLocked->estado !== GroomingTurno::ESTADO_COMPLETADA) {
                    throw ValidationException::withMessages([
                        'grooming_turno_id' => __('caja.ventas.grooming.turno_invalido'),
                    ]);
                }
                $pacId = $validated['paciente_id'] ?? null;
                if (is_string($pacId) && $pacId !== '' && $pacId !== $groomingTurnoLocked->paciente_id) {
                    throw ValidationException::withMessages([
                        'paciente_id' => __('caja.ventas.grooming.turno_invalido'),
                    ]);
                }
            }

            $hotelEstanciaLocked = null;
            $hotelEstanciaId = $validated['hotel_estancia_id'] ?? null;
            if (is_string($hotelEstanciaId) && $hotelEstanciaId !== '') {
                $hotelEstanciaLocked = HotelEstancia::query()->whereKey($hotelEstanciaId)->lockForUpdate()->first();
                if ($hotelEstanciaLocked === null
                    || $hotelEstanciaLocked->venta_id !== null
                    || $hotelEstanciaLocked->estado !== HotelEstancia::ESTADO_COMPLETADA) {
                    throw ValidationException::withMessages([
                        'hotel_estancia_id' => __('caja.ventas.hotel.estancia_invalida'),
                    ]);
                }
                $pacIdH = $validated['paciente_id'] ?? null;
                if (is_string($pacIdH) && $pacIdH !== '' && $pacIdH !== $hotelEstanciaLocked->paciente_id) {
                    throw ValidationException::withMessages([
                        'paciente_id' => __('caja.ventas.hotel.estancia_invalida'),
                    ]);
                }
            }

            $cargoVinculado = $this->resolverCargoVinculado($validated);

            $productoIds = collect($validated['lineas'])
                ->pluck('producto_id')
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->unique()
                ->values()
                ->all();

            $productos = $productoIds === []
                ? collect()
                : Producto::query()
                ->whereIn('id', $productoIds)
                ->where('activo', true)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lineasCalc = [];
            $subtotalVenta = 0.0;
            $totalVenta = 0.0;
            $divisorIgv = 1 + ($igvPct / 100);

            foreach ($validated['lineas'] as $idx => $row) {
                $pid = isset($row['producto_id']) && is_string($row['producto_id']) && $row['producto_id'] !== ''
                    ? $row['producto_id']
                    : null;
                $cantidad = (float) (string) $row['cantidad'];
                $tipoLinea = isset($row['tipo_linea']) && is_string($row['tipo_linea'])
                    ? $row['tipo_linea']
                    : ($pid !== null ? ConsultaCargoLinea::TIPO_PRODUCTO : ConsultaCargoLinea::TIPO_SERVICIO);

                if ($pid !== null) {
                    $producto = $productos->get($pid);
                    if ($producto === null) {
                        throw ValidationException::withMessages([
                            "lineas.{$idx}.producto_id" => __('caja.ventas.validation.producto_inactivo'),
                        ]);
                    }

                    $precioLista = isset($row['precio_lista']) && $row['precio_lista'] !== ''
                        ? (float) (string) $row['precio_lista']
                        : (float) (string) ($producto->precio_venta ?? 0);
                    $descripcion = mb_substr((string) $producto->nombre, 0, 300);
                } else {
                    $concepto = trim((string) ($row['concepto'] ?? ''));
                    if ($concepto === '') {
                        throw ValidationException::withMessages([
                            "lineas.{$idx}.concepto" => __('caja.ventas.validation.linea_sin_concepto'),
                        ]);
                    }

                    $precioLista = (float) (string) ($row['precio_lista'] ?? 0);
                    $descripcion = mb_substr($concepto, 0, 300);
                }

                if ($precioIncluyeIgv) {
                    $lineGross = round($cantidad * $precioLista, 2);
                    if ($divisorIgv > 0) {
                        $lineSub = round($lineGross / $divisorIgv, 2);
                    } else {
                        $lineSub = $lineGross;
                    }
                    $puSinIgv = $cantidad > 0 ? round($lineSub / $cantidad, 4) : 0.0;
                    $subtotalVenta += $lineSub;
                    $totalVenta += $lineGross;
                } else {
                    $puSinIgv = $this->precioUnitarioSinIgv($precioLista, $igvPct, false);
                    $lineSub = round($cantidad * $puSinIgv, 2);
                    $subtotalVenta += $lineSub;
                }

                $lineasCalc[] = [
                    'producto_id' => $pid,
                    'tipo_linea' => $tipoLinea,
                    'consulta_cargo_linea_id' => isset($row['consulta_cargo_linea_id']) && is_string($row['consulta_cargo_linea_id'])
                        ? $row['consulta_cargo_linea_id']
                        : null,
                    'descripcion_snapshot' => $descripcion,
                    'igv_tipo_snapshot' => 'gravado',
                    'cantidad' => $cantidad,
                    'precio_unitario' => $puSinIgv,
                    'descuento_pct' => 0.0,
                    'subtotal' => $lineSub,
                ];
            }

            if ($precioIncluyeIgv) {
                $subtotalVenta = round($subtotalVenta, 2);
                $total = round($totalVenta, 2);
                $igvMonto = round($total - $subtotalVenta, 2);
            } else {
                $subtotalVenta = round($subtotalVenta, 2);
                $igvMonto = round($subtotalVenta * ($igvPct / 100), 2);
                $total = round($subtotalVenta + $igvMonto, 2);
            }

            $metodo = (string) $validated['metodo_pago'];
            $montoRecibido = null;
            $vuelto = null;
            if ($metodo === 'efectivo') {
                $montoRecibido = round((float) (string) $validated['monto_recibido'], 2);
                if ($montoRecibido + 0.0001 < $total) {
                    throw ValidationException::withMessages([
                        'monto_recibido' => __('caja.ventas.validation.monto_insuficiente'),
                    ]);
                }
                $vuelto = round(max(0, $montoRecibido - $total), 2);
            }

            $anio = (int) now()->year;
            // PostgreSQL no permite FOR UPDATE con agregados (max); se bloquea la última fila del año.
            $ultimaVentaAnio = Venta::withTrashed()
                ->where('anio', $anio)
                ->orderByDesc('correlativo')
                ->lockForUpdate()
                ->first();
            $correlativo = ((int) ($ultimaVentaAnio?->correlativo ?? 0)) + 1;
            $numero = sprintf('VTA-%d-%05d', $anio, $correlativo);

            $venta = Venta::query()->create([
                'numero' => $numero,
                'anio' => $anio,
                'correlativo' => $correlativo,
                'propietario_id' => $validated['propietario_id'],
                'paciente_id' => $validated['paciente_id'] ?? null,
                'consulta_id' => $cargoVinculado['consulta_id'] ?? ($validated['consulta_id'] ?? null),
                'consulta_cargo_id' => $cargoVinculado['consulta_cargo_id'] ?? ($validated['consulta_cargo_id'] ?? null),
                'caja_sesion_id' => $sesion->id,
                'sede_id' => $sesion->sede_id,
                'moneda' => $moneda,
                'estado' => Venta::ESTADO_PAGADO,
                'subtotal' => number_format($subtotalVenta, 2, '.', ''),
                'igv_monto' => number_format($igvMonto, 2, '.', ''),
                'descuento_monto' => '0.00',
                'total' => number_format($total, 2, '.', ''),
                'metodo_pago' => $metodo,
                'monto_recibido' => $montoRecibido !== null ? number_format($montoRecibido, 2, '.', '') : null,
                'vuelto' => $vuelto !== null ? number_format($vuelto, 2, '.', '') : null,
                'fecha_pago' => now(),
                'notas' => $validated['notas'] ?? null,
                'fel_estado' => $felPendiente ? Venta::FEL_PENDIENTE : Venta::FEL_SIN_CPE,
                'fel_document_id' => null,
                'created_by_id' => $user->getAuthIdentifier(),
            ]);

            if ($cargoVinculado !== null) {
                ConsultaCargo::query()
                    ->whereKey($cargoVinculado['consulta_cargo_id'])
                    ->update(['venta_id' => $venta->id]);
            }

            if ($groomingTurnoLocked !== null) {
                $groomingTurnoLocked->update(['venta_id' => $venta->id]);
            }

            if ($hotelEstanciaLocked !== null) {
                $hotelEstanciaLocked->update(['venta_id' => $venta->id]);
            }

            foreach ($lineasCalc as $idx => $lc) {
                VentaLinea::query()->create([
                    'venta_id' => $venta->id,
                    'tipo_linea' => $lc['tipo_linea'],
                    'producto_id' => $lc['producto_id'],
                    'consulta_cargo_linea_id' => $lc['consulta_cargo_linea_id'],
                    'descripcion_snapshot' => $lc['descripcion_snapshot'],
                    'igv_tipo_snapshot' => $lc['igv_tipo_snapshot'],
                    'cantidad' => number_format($lc['cantidad'], 3, '.', ''),
                    'precio_unitario' => number_format($lc['precio_unitario'], 4, '.', ''),
                    'descuento_pct' => '0.00',
                    'subtotal' => number_format($lc['subtotal'], 2, '.', ''),
                ]);

                if ($lc['producto_id'] === null) {
                    continue;
                }

                $notasMov = __('caja.ventas.movimiento_notas', ['numero' => $numero]);

                try {
                    MovimientoInventario::aplicar(
                        $lc['producto_id'],
                        (string) $sesion->sede_id,
                        MovimientoInventario::TIPO_SALIDA,
                        (string) (-1 * (float) $lc['cantidad']),
                        $notasMov,
                        (string) $user->getAuthIdentifier(),
                        null,
                        (string) $venta->id,
                    );
                } catch (ValidationException $e) {
                    $errores = $e->errors();
                    $mensaje = $errores['cantidad'][0] ?? __('caja.ventas.validation.stock_insuficiente', [
                        'producto' => $lc['descripcion_snapshot'],
                    ]);

                    throw ValidationException::withMessages([
                        "lineas.{$idx}.cantidad" => $mensaje,
                    ]);
                }
            }

            $venta = $venta->fresh(['lineas', 'propietario', 'paciente']);

            if ($felPendiente && $tenantSlug !== null && $tenantSlug !== '') {
                EmitirFelVentaJob::dispatch($venta->id, $tenantSlug)->afterCommit();
            }

            return $venta;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{consulta_id: string, consulta_cargo_id: string}|null
     */
    private function resolverCargoVinculado(array $validated): ?array
    {
        $cargoId = $validated['consulta_cargo_id'] ?? null;
        if (! is_string($cargoId) || $cargoId === '') {
            return null;
        }

        $cargo = ConsultaCargo::query()->lockForUpdate()->find($cargoId);
        if ($cargo === null) {
            throw ValidationException::withMessages([
                'consulta_cargo_id' => __('caja.ventas.desde_cargo.validation.cargo_invalido'),
            ]);
        }

        if ($cargo->estado !== ConsultaCargo::ESTADO_CONFIRMADO) {
            throw ValidationException::withMessages([
                'consulta_cargo_id' => __('caja.ventas.desde_cargo.validation.no_confirmado'),
            ]);
        }

        if ($cargo->venta_id !== null) {
            throw ValidationException::withMessages([
                'consulta_cargo_id' => __('caja.ventas.desde_cargo.validation.ya_cobrado'),
            ]);
        }

        $consultaId = $validated['consulta_id'] ?? null;
        if (is_string($consultaId) && $consultaId !== '' && $cargo->consulta_id !== null && $consultaId !== $cargo->consulta_id) {
            throw ValidationException::withMessages([
                'consulta_id' => __('caja.ventas.desde_cargo.validation.consulta_no_coincide'),
            ]);
        }

        return [
            'consulta_id' => $cargo->consulta_id ?? (is_string($consultaId) && $consultaId !== '' ? $consultaId : null),
            'consulta_cargo_id' => $cargo->id,
        ];
    }

    private function precioUnitarioSinIgv(float $precioLista, float $igvPct, bool $precioIncluyeIgv): float
    {
        if ($precioLista <= 0) {
            return 0.0;
        }

        if (! $precioIncluyeIgv) {
            return round($precioLista, 4);
        }

        $divisor = 1 + ($igvPct / 100);
        if ($divisor <= 0) {
            return round($precioLista, 4);
        }

        return round($precioLista / $divisor, 4);
    }
}
