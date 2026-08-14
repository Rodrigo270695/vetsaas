<?php

namespace App\Services\Venta;

use App\Models\CajaSesion;
use App\Models\Cita;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\ConsultaCargo;
use App\Models\ConsultaCargoLinea;
use App\Models\FelSerie;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Tenant;
use App\Models\Venta;
use App\Models\VentaLinea;
use App\Models\VentaPago;
use App\Services\Fel\FelEmisionVentaService;
use App\Services\Inventario\InventarioLoteService;
use App\Support\Fel\ApisunatCredentialResolver;
use App\Support\PlanCapabilities;
use App\Support\Venta\DescuentoManualLinea;
use App\Support\Venta\VentaPagosResolver;
use App\Support\Venta\VentaTotales;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VentaCheckoutService
{
    public function __construct(
        private readonly InventarioLoteService $lotes,
    ) {}

    /**
     * Registra una venta de adelanto (anticipo) ligada a un turno de grooming.
     * No marca el cobro final del turno ni del cargo.
     *
     * @param  array{caja_sesion_id: string, monto: float|string, metodo_pago: string, monto_recibido?: float|string|null, notas?: string|null}  $validated
     */
    public function registrarAdelantoGrooming(
        GroomingTurno $turno,
        array $validated,
        Authenticatable $user,
    ): Venta {
        $clinic = ClinicSetting::current();
        $igvPct = $clinic->igvPorcentajeEfectivo();
        $igvTipo = $clinic->igvAfectacion();
        $precioIncluyeIgv = (bool) $clinic->precio_incluye_igv;
        $moneda = (string) $clinic->moneda;
        if ($moneda !== 'PEN' && $moneda !== 'USD') {
            $moneda = 'PEN';
        }

        return DB::transaction(function () use ($turno, $validated, $user, $igvPct, $igvTipo, $precioIncluyeIgv, $moneda): Venta {
            $turnoLocked = GroomingTurno::query()
                ->whereKey($turno->id)
                ->lockForUpdate()
                ->firstOrFail();

            $turnoLocked->load([
                'paciente' => fn ($q) => $q->withTrashed()->select('id', 'nombre', 'propietario_id'),
                'paciente.propietario' => fn ($q) => $q->withTrashed()->select('id'),
                'cargo:id,grooming_turno_id,venta_id',
            ]);

            if (! $turnoLocked->permiteAdelanto()) {
                throw ValidationException::withMessages([
                    'grooming_turno_id' => __('caja.ventas.grooming.adelanto_no_permitido'),
                ]);
            }

            $paciente = $turnoLocked->paciente;
            $propietarioId = $paciente?->propietario_id;
            if ($paciente === null || ! is_string($propietarioId) || $propietarioId === '') {
                throw ValidationException::withMessages([
                    'grooming_turno_id' => __('caja.ventas.grooming.sin_propietario'),
                ]);
            }

            $sesionId = $validated['caja_sesion_id'] ?? null;
            if (is_string($sesionId) && $sesionId !== '') {
                $sesion = CajaSesion::query()
                    ->whereKey($sesionId)
                    ->lockForUpdate()
                    ->firstOrFail();
            } else {
                $sesion = CajaSesion::query()
                    ->where('estado', CajaSesion::ESTADO_ABIERTA)
                    ->where('opened_by_id', $user->getAuthIdentifier())
                    ->lockForUpdate()
                    ->first();
                if ($sesion === null) {
                    throw ValidationException::withMessages([
                        'caja_sesion_id' => __('caja.ventas.desde_cargo.validation.sin_sesion'),
                    ]);
                }
            }

            if (! $sesion->estaAbierta() || (string) $sesion->opened_by_id !== (string) $user->getAuthIdentifier()) {
                throw ValidationException::withMessages([
                    'caja_sesion_id' => __('caja.ventas.validation.sesion_invalida'),
                ]);
            }

            $monto = round((float) (string) $validated['monto'], 2);
            if ($monto < 0.01) {
                throw ValidationException::withMessages([
                    'monto' => __('caja.ventas.grooming.adelanto_monto_invalido'),
                ]);
            }

            $concepto = mb_substr(
                __('caja.ventas.grooming.adelanto_concepto', [
                    'servicio' => $turnoLocked->descripcionParaVenta(),
                    'paciente' => $paciente->nombre,
                ]),
                0,
                300,
            );

            $cantidad = 1.0;
            $precioLista = $monto;
            if ($precioIncluyeIgv) {
                $divisorIgv = 1 + ($igvPct / 100);
                $lineGross = $monto;
                $lineSub = $divisorIgv > 0 ? round($lineGross / $divisorIgv, 2) : $lineGross;
                $puSinIgv = round($lineSub / $cantidad, 4);
            } else {
                $puSinIgv = round($precioLista, 4);
                $lineSub = round($cantidad * $puSinIgv, 2);
            }

            $lineasCalc = [[
                'producto_id' => null,
                'tipo_linea' => 'servicio',
                'consulta_cargo_linea_id' => null,
                'descripcion_snapshot' => $concepto,
                'igv_tipo_snapshot' => $igvTipo,
                'cantidad' => $cantidad,
                'precio_lista' => $precioLista,
                'precio_unitario' => $puSinIgv,
                'descuento_pct' => 0.0,
                'subtotal' => $lineSub,
                'promotion_id' => null,
            ]];

            $totales = VentaTotales::fromLineas($lineasCalc, $igvPct, $precioIncluyeIgv);
            $total = (float) $totales['total'];

            $pagosPayload = [
                'metodo_pago' => $validated['metodo_pago'],
                'monto_recibido' => $validated['monto_recibido'] ?? null,
                'pagos' => [[
                    'metodo' => $validated['metodo_pago'],
                    'monto' => $total,
                    'monto_recibido' => $validated['monto_recibido'] ?? null,
                ]],
            ];
            $pagosLineas = VentaPagosResolver::fromValidated($pagosPayload, $total);
            $metodo = VentaPagosResolver::metodoResumen($pagosLineas);
            $efectivoSnap = VentaPagosResolver::efectivoSnapshot($pagosLineas);

            $anio = (int) now()->year;
            $ultimaVentaAnio = Venta::withTrashed()
                ->where('anio', $anio)
                ->orderByDesc('correlativo')
                ->lockForUpdate()
                ->first();
            $correlativo = ((int) ($ultimaVentaAnio?->correlativo ?? 0)) + 1;
            $numero = sprintf('VTA-%d-%05d', $anio, $correlativo);

            $notas = trim((string) ($validated['notas'] ?? ''));
            $notaAdelanto = __('caja.ventas.grooming.adelanto_nota_venta');
            $notasFinal = $notas !== '' ? $notaAdelanto.' '.$notas : $notaAdelanto;

            $venta = Venta::query()->create([
                'numero' => $numero,
                'anio' => $anio,
                'correlativo' => $correlativo,
                'propietario_id' => $propietarioId,
                'paciente_id' => $paciente->id,
                'consulta_id' => null,
                'consulta_cargo_id' => null,
                'caja_sesion_id' => $sesion->id,
                'sede_id' => $sesion->sede_id,
                'moneda' => $moneda,
                'estado' => Venta::ESTADO_PAGADO,
                'subtotal' => number_format((float) $totales['subtotal'], 2, '.', ''),
                'igv_monto' => number_format((float) $totales['igv'], 2, '.', ''),
                'descuento_monto' => '0.00',
                'promotion_id' => null,
                'promotion_name_snapshot' => null,
                'total' => number_format($total, 2, '.', ''),
                'metodo_pago' => $metodo,
                'monto_recibido' => $efectivoSnap['monto_recibido'] !== null
                    ? number_format($efectivoSnap['monto_recibido'], 2, '.', '')
                    : null,
                'vuelto' => $efectivoSnap['vuelto'] !== null
                    ? number_format($efectivoSnap['vuelto'], 2, '.', '')
                    : null,
                'fecha_pago' => now(),
                'notas' => mb_substr($notasFinal, 0, 2000),
                'fel_estado' => Venta::FEL_SIN_CPE,
                'tipo_comprobante_sunat' => null,
                'fel_document_id' => null,
                'created_by_id' => $user->getAuthIdentifier(),
            ]);

            foreach ($pagosLineas as $orden => $pago) {
                VentaPago::query()->create([
                    'venta_id' => $venta->id,
                    'metodo' => $pago['metodo'],
                    'monto' => number_format($pago['monto'], 2, '.', ''),
                    'monto_recibido' => $pago['monto_recibido'] !== null
                        ? number_format($pago['monto_recibido'], 2, '.', '')
                        : null,
                    'vuelto' => $pago['vuelto'] !== null
                        ? number_format($pago['vuelto'], 2, '.', '')
                        : null,
                    'orden' => $orden,
                ]);
            }

            foreach ($lineasCalc as $lc) {
                VentaLinea::query()->create([
                    'venta_id' => $venta->id,
                    'tipo_linea' => $lc['tipo_linea'],
                    'producto_id' => null,
                    'consulta_cargo_linea_id' => null,
                    'descripcion_snapshot' => $lc['descripcion_snapshot'],
                    'igv_tipo_snapshot' => $lc['igv_tipo_snapshot'],
                    'cantidad' => number_format($lc['cantidad'], 3, '.', ''),
                    'precio_unitario' => number_format($lc['precio_unitario'], 4, '.', ''),
                    'descuento_pct' => '0.00',
                    'subtotal' => number_format($lc['subtotal'], 2, '.', ''),
                    'promotion_id' => null,
                ]);
            }

            $turnoLocked->update([
                'adelanto_venta_id' => $venta->id,
                'adelanto_monto' => number_format($monto, 2, '.', ''),
                'adelanto_at' => now(),
                'updated_by_id' => $user->getAuthIdentifier(),
            ]);

            return $venta->fresh(['lineas', 'pagos']);
        });
    }

    /**
     * Registra una venta pagada, líneas, correlativo y salidas de inventario.
     *
     * @param  array<string, mixed>  $validated
     */
    public function registrar(array $validated, Authenticatable $user, ?Tenant $tenant): Venta
    {
        $clinic = ClinicSetting::current();
        $igvPct = $clinic->igvPorcentajeEfectivo();
        $igvTipo = $clinic->igvAfectacion();
        $precioIncluyeIgv = (bool) $clinic->precio_incluye_igv;
        $moneda = (string) $clinic->moneda;
        if ($moneda !== 'PEN' && $moneda !== 'USD') {
            $moneda = 'PEN';
        }

        $tipoComprobante = array_key_exists('tipo_comprobante_sunat', $validated)
            && $validated['tipo_comprobante_sunat'] !== null
            ? (int) $validated['tipo_comprobante_sunat']
            : null;

        $felPendiente = (bool) $clinic->emite_comprobantes_sunat
            && ApisunatCredentialResolver::estaConfigurado($clinic)
            && FelSerie::esTipoSunat($tipoComprobante)
            && $this->planPermiteTipoComprobante($tenant, $tipoComprobante);

        $venta = DB::transaction(function () use ($validated, $user, $igvPct, $igvTipo, $precioIncluyeIgv, $moneda, $felPendiente, $tipoComprobante): Venta {
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
                $cargoGroomingListo = $groomingTurnoLocked !== null
                    && ConsultaCargo::query()
                        ->where('grooming_turno_id', $groomingTurnoLocked->id)
                        ->where('estado', ConsultaCargo::ESTADO_CONFIRMADO)
                        ->whereNull('venta_id')
                        ->exists();
                if ($groomingTurnoLocked === null
                    || (! $cargoGroomingListo && $groomingTurnoLocked->venta_id !== null)
                    || (! $cargoGroomingListo && $groomingTurnoLocked->estado !== GroomingTurno::ESTADO_COMPLETADA)) {
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
                $cargoHotelListo = $hotelEstanciaLocked !== null
                    && ConsultaCargo::query()
                        ->where('hotel_estancia_id', $hotelEstanciaLocked->id)
                        ->where('estado', ConsultaCargo::ESTADO_CONFIRMADO)
                        ->whereNull('venta_id')
                        ->exists();
                if ($hotelEstanciaLocked === null
                    || (! $cargoHotelListo && $hotelEstanciaLocked->venta_id !== null)
                    || (! $cargoHotelListo && $hotelEstanciaLocked->estado !== HotelEstancia::ESTADO_COMPLETADA)) {
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

            $cargosVinculados = $this->resolverCargosVinculados($validated);
            $cargoVinculado = $cargosVinculados[0] ?? null;

            $cargoLineasConStock = [];
            if ($cargosVinculados !== []) {
                $cargoIds = array_column($cargosVinculados, 'consulta_cargo_id');
                $cargoLineasConStock = ConsultaCargoLinea::query()
                    ->whereIn('consulta_cargo_id', $cargoIds)
                    ->whereNotNull('movimiento_inventario_id')
                    ->pluck('movimiento_inventario_id', 'id')
                    ->all();
            }

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
                    'igv_tipo_snapshot' => $igvTipo,
                    'cantidad' => $cantidad,
                    'precio_lista' => $precioLista,
                    'precio_unitario' => $puSinIgv,
                    'descuento_pct' => 0.0,
                    'subtotal' => $lineSub,
                    'promotion_id' => null,
                ];
            }

            $groomingServiceSlug = null;
            if ($groomingTurnoLocked !== null) {
                $groomingServiceSlug = $groomingTurnoLocked->servicio;
                if (! is_string($validated['paciente_id'] ?? null) || $validated['paciente_id'] === '') {
                    $validated['paciente_id'] = $groomingTurnoLocked->paciente_id;
                }
            }

            $promoResult = app(PromotionCheckoutService::class)->evaluate(
                [
                    'propietario_id' => $validated['propietario_id'],
                    'paciente_id' => $validated['paciente_id'] ?? null,
                    'grooming_turno_id' => $validated['grooming_turno_id'] ?? null,
                    'grooming_service_slug' => $groomingServiceSlug,
                    'hotel_estancia_id' => $validated['hotel_estancia_id'] ?? null,
                    'promotion_code' => $validated['promotion_code'] ?? null,
                ],
                $lineasCalc,
                $igvPct,
                $precioIncluyeIgv,
            );

            if ($groomingTurnoLocked !== null && $groomingTurnoLocked->tieneAdelanto()) {
                $adelanto = (float) (string) $groomingTurnoLocked->adelanto_monto;
                $validated['lineas'] = $this->inyectarDescuentoAdelanto($validated['lineas'], $adelanto);
            }

            $manualResult = DescuentoManualLinea::apply(
                $promoResult->lineas,
                $validated['lineas'],
                $igvPct,
                $precioIncluyeIgv,
            );
            $lineasCalc = $manualResult['lineas'];
            $descuentoMonto = round(
                (float) $promoResult->discount_amount + $manualResult['discount_amount'],
                2,
            );

            $totales = VentaTotales::fromLineas($lineasCalc, $igvPct, $precioIncluyeIgv);
            $subtotalVenta = $totales['subtotal'];
            $igvMonto = $totales['igv'];
            $total = $totales['total'];

            $pagosLineas = VentaPagosResolver::fromValidated($validated, (float) $total);
            $metodo = $pagosLineas === []
                ? 'adelanto'
                : VentaPagosResolver::metodoResumen($pagosLineas);
            $efectivoSnap = VentaPagosResolver::efectivoSnapshot($pagosLineas);
            $montoRecibido = $efectivoSnap['monto_recibido'];
            $vuelto = $efectivoSnap['vuelto'];

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
                'descuento_monto' => number_format((float) $descuentoMonto, 2, '.', ''),
                'promotion_id' => $promoResult->promotion_id,
                'promotion_name_snapshot' => $promoResult->promotion_name,
                'total' => number_format($total, 2, '.', ''),
                'metodo_pago' => $metodo,
                'monto_recibido' => $montoRecibido !== null ? number_format($montoRecibido, 2, '.', '') : null,
                'vuelto' => $vuelto !== null ? number_format($vuelto, 2, '.', '') : null,
                'fecha_pago' => now(),
                'notas' => $validated['notas'] ?? null,
                'fel_estado' => $felPendiente ? Venta::FEL_PENDIENTE : Venta::FEL_SIN_CPE,
                'tipo_comprobante_sunat' => $tipoComprobante,
                'fel_document_id' => null,
                'created_by_id' => $user->getAuthIdentifier(),
            ]);

            foreach ($pagosLineas as $orden => $pago) {
                VentaPago::query()->create([
                    'venta_id' => $venta->id,
                    'metodo' => $pago['metodo'],
                    'monto' => number_format($pago['monto'], 2, '.', ''),
                    'monto_recibido' => $pago['monto_recibido'] !== null
                        ? number_format($pago['monto_recibido'], 2, '.', '')
                        : null,
                    'vuelto' => $pago['vuelto'] !== null
                        ? number_format($pago['vuelto'], 2, '.', '')
                        : null,
                    'orden' => $orden,
                ]);
            }

            if ($cargosVinculados !== []) {
                $cargoIds = array_column($cargosVinculados, 'consulta_cargo_id');
                ConsultaCargo::query()
                    ->whereIn('id', $cargoIds)
                    ->whereNull('venta_id')
                    ->update(['venta_id' => $venta->id]);

                $consultaIds = array_values(array_unique(array_filter(
                    array_column($cargosVinculados, 'consulta_id'),
                    static fn ($id): bool => is_string($id) && $id !== '',
                )));

                foreach ($consultaIds as $consultaId) {
                    $consulta = Consulta::query()
                        ->lockForUpdate()
                        ->find($consultaId);

                    if ($consulta === null) {
                        continue;
                    }

                    $consulta->update([
                        'cerrada_at' => $consulta->cerrada_at ?? now(),
                        'cerrada_por_id' => $consulta->cerrada_por_id
                            ?? $user->getAuthIdentifier(),
                        'updated_by_id' => $user->getAuthIdentifier(),
                    ]);

                    if (is_string($consulta->cita_id) && $consulta->cita_id !== '') {
                        Cita::query()
                            ->whereKey($consulta->cita_id)
                            ->where('estado', Cita::ESTADO_EN_ATENCION)
                            ->update([
                                'estado' => Cita::ESTADO_COMPLETADA,
                                'updated_by_id' => $user->getAuthIdentifier(),
                            ]);
                    }
                }

                // Marcar turnos/estancias de todos los cargos (no solo el primero del form).
                $groomingIds = ConsultaCargo::query()
                    ->whereIn('id', $cargoIds)
                    ->whereNotNull('grooming_turno_id')
                    ->pluck('grooming_turno_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                if ($groomingIds !== []) {
                    GroomingTurno::query()
                        ->whereIn('id', $groomingIds)
                        ->whereNull('venta_id')
                        ->update(['venta_id' => $venta->id]);
                }

                $hotelIds = ConsultaCargo::query()
                    ->whereIn('id', $cargoIds)
                    ->whereNotNull('hotel_estancia_id')
                    ->pluck('hotel_estancia_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                if ($hotelIds !== []) {
                    HotelEstancia::query()
                        ->whereIn('id', $hotelIds)
                        ->whereNull('venta_id')
                        ->update(['venta_id' => $venta->id]);
                }
            }

            if ($groomingTurnoLocked !== null && $groomingTurnoLocked->venta_id === null) {
                $groomingTurnoLocked->update(['venta_id' => $venta->id]);
            }

            if ($hotelEstanciaLocked !== null && $hotelEstanciaLocked->venta_id === null) {
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
                    'descuento_pct' => number_format((float) ($lc['descuento_pct'] ?? 0), 2, '.', ''),
                    'promotion_id' => $lc['promotion_id'] ?? null,
                    'subtotal' => number_format($lc['subtotal'], 2, '.', ''),
                ]);

                if ($lc['producto_id'] === null) {
                    continue;
                }

                $cargoLineaId = $lc['consulta_cargo_linea_id'] ?? null;
                if (
                    is_string($cargoLineaId)
                    && $cargoLineaId !== ''
                    && isset($cargoLineasConStock[$cargoLineaId])
                ) {
                    $movRef = MovimientoInventario::query()->find($cargoLineasConStock[$cargoLineaId]);
                    if ($movRef !== null) {
                        $this->lotes->vincularSalidaFefoAVenta($movRef, (string) $venta->id);
                    }

                    continue;
                }

                $notasMov = __('caja.ventas.movimiento_notas', ['numero' => $numero]);

                try {
                    $this->lotes->descontarFefo(
                        $lc['producto_id'],
                        (string) $sesion->sede_id,
                        (string) ((float) $lc['cantidad']),
                        $notasMov,
                        (string) $user->getAuthIdentifier(),
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

            if ($promoResult->promotion_id !== null) {
                app(PromotionCheckoutService::class)->recordUse($promoResult->promotion_id);
            }

            $venta = $venta->fresh(['lineas', 'propietario', 'paciente']);

            return $venta;
        });

        if ($felPendiente) {
            try {
                app(FelEmisionVentaService::class)->emitir(
                    $venta->fresh(['lineas', 'propietario', 'felDocument']),
                );
            } catch (\RuntimeException) {
                // El servicio deja la venta en rechazado o pendiente; no revertimos el cobro.
            }
        }

        return $venta->fresh(['lineas', 'propietario', 'felDocument']);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{consulta_id: ?string, consulta_cargo_id: string}>
     */
    private function resolverCargosVinculados(array $validated): array
    {
        $ids = [];
        $multi = $validated['consulta_cargo_ids'] ?? null;
        if (is_array($multi)) {
            foreach ($multi as $id) {
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
        }

        $single = $validated['consulta_cargo_id'] ?? null;
        if (is_string($single) && $single !== '') {
            $ids[] = $single;
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        $out = [];
        $propietarioVenta = $validated['propietario_id'] ?? null;

        foreach ($ids as $cargoId) {
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
            if (
                count($ids) === 1
                && is_string($consultaId)
                && $consultaId !== ''
                && $cargo->consulta_id !== null
                && $consultaId !== $cargo->consulta_id
            ) {
                throw ValidationException::withMessages([
                    'consulta_id' => __('caja.ventas.desde_cargo.validation.consulta_no_coincide'),
                ]);
            }

            $out[] = [
                'consulta_id' => $cargo->consulta_id,
                'consulta_cargo_id' => $cargo->id,
            ];
        }

        // Misma dueña: si viene propietario_id, todos los cargos deben coincidir (vía relaciones).
        if (is_string($propietarioVenta) && $propietarioVenta !== '' && count($out) > 1) {
            $this->assertCargosMismoPropietario($ids, $propietarioVenta);
        }

        return $out;
    }

    /**
     * @param  list<string>  $cargoIds
     */
    private function assertCargosMismoPropietario(array $cargoIds, string $propietarioId): void
    {
        $cargos = ConsultaCargo::query()
            ->whereIn('id', $cargoIds)
            ->with([
                'consulta.historiaClinica.paciente:id,propietario_id',
                'groomingTurno.paciente:id,propietario_id',
                'hotelEstancia.paciente:id,propietario_id',
                'internamiento.paciente:id,propietario_id',
                'vacunaAplicada.paciente:id,propietario_id',
            ])
            ->get();

        foreach ($cargos as $cargo) {
            $pid = $cargo->vacunaAplicada?->paciente?->propietario_id
                ?? $cargo->groomingTurno?->paciente?->propietario_id
                ?? $cargo->hotelEstancia?->paciente?->propietario_id
                ?? $cargo->consulta?->historiaClinica?->paciente?->propietario_id
                ?? $cargo->internamiento?->paciente?->propietario_id;

            if ($pid !== null && (string) $pid !== $propietarioId) {
                throw ValidationException::withMessages([
                    'consulta_cargo_ids' => __('caja.ventas.multi.mismo_propietario'),
                ]);
            }
        }
    }

    /**
     * @deprecated Usar {@see resolverCargosVinculados}
     *
     * @param  array<string, mixed>  $validated
     * @return array{consulta_id: ?string, consulta_cargo_id: string}|null
     */
    private function resolverCargoVinculado(array $validated): ?array
    {
        return $this->resolverCargosVinculados($validated)[0] ?? null;
    }

    /**
     * Reparte el adelanto como descuento_monto sobre las líneas (precio lista × cantidad).
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function inyectarDescuentoAdelanto(array $lineas, float $adelanto): array
    {
        $restante = round(max(0, $adelanto), 2);
        if ($restante < 0.01) {
            return $lineas;
        }

        foreach ($lineas as $i => $row) {
            if ($restante < 0.01) {
                break;
            }
            $qty = (float) (string) ($row['cantidad'] ?? 0);
            $precio = (float) (string) ($row['precio_lista'] ?? 0);
            $lineGross = round($qty * $precio, 2);
            $existing = (float) (string) ($row['descuento_monto'] ?? 0);
            $capacidad = max(0, round($lineGross - $existing, 2));
            if ($capacidad < 0.01) {
                continue;
            }
            $aplicar = min($restante, $capacidad);
            $lineas[$i]['descuento_monto'] = round($existing + $aplicar, 2);
            $restante = round($restante - $aplicar, 2);
        }

        return $lineas;
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

    private function planPermiteTipoComprobante(?Tenant $tenant, ?int $tipo): bool
    {
        return match ($tipo) {
            FelSerie::TIPO_FACTURA => PlanCapabilities::facturasElectronicas($tenant),
            FelSerie::TIPO_BOLETA => PlanCapabilities::boletasElectronicas($tenant),
            default => false,
        };
    }
}
