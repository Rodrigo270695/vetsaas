<?php

namespace App\Support\Venta;

use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\ConsultaCargo;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\HotelEstanciaTarifa;
use App\Models\Internamiento;
use App\Models\ConsultaCargoLinea;
use App\Models\VacunaAplicada;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VentaDesdeCargoPrefill
{
    /**
     * @return array{
     *     consulta_id: string,
     *     consulta_cargo_id: string,
     *     propietario_id: string,
     *     paciente_id: ?string,
     *     paciente_nombre: ?string,
     *     consulta_atendido_at: ?string,
     *     cargo_total: string,
     *     lineas_iniciales: list<array{
     *         producto_id: ?string,
     *         tipo_linea: string,
     *         concepto: string,
     *         cantidad: string,
     *         precio_lista: string,
     *         stock_sede: string,
     *         consulta_cargo_linea_id: string,
     *     }>,
     * }
     */
    public function build(Consulta $consulta): array
    {
        $consulta->load([
            'historiaClinica.paciente:id,nombre,propietario_id',
            'historiaClinica.paciente.propietario:id,nombres,apellidos,razon_social',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden'),
        ]);

        $cargo = $consulta->cargo;
        if ($cargo === null) {
            throw ValidationException::withMessages([
                'consulta' => __('caja.ventas.desde_cargo.validation.sin_cargo'),
            ]);
        }

        if ($cargo->estado !== ConsultaCargo::ESTADO_CONFIRMADO) {
            throw ValidationException::withMessages([
                'consulta' => __('caja.ventas.desde_cargo.validation.no_confirmado'),
            ]);
        }

        if ($cargo->venta_id !== null) {
            throw ValidationException::withMessages([
                'consulta' => __('caja.ventas.desde_cargo.validation.ya_cobrado'),
            ]);
        }

        if ($cargo->lineas->isEmpty()) {
            throw ValidationException::withMessages([
                'consulta' => __('caja.ventas.desde_cargo.validation.sin_lineas'),
            ]);
        }

        $sesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        if ($sesion === null) {
            throw ValidationException::withMessages([
                'caja' => __('caja.ventas.desde_cargo.validation.sin_sesion'),
            ]);
        }

        $paciente = $consulta->historiaClinica->paciente;
        $propietario = $paciente->propietario;

        $productoIds = $cargo->lineas
            ->pluck('producto_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $stocks = $productoIds === []
            ? []
            : DB::table('existencias_sede')
                ->where('sede_id', $sesion->sede_id)
                ->whereIn('producto_id', $productoIds)
                ->pluck('cantidad', 'producto_id')
                ->all();

        $lineasIniciales = $cargo->lineas->map(function (ConsultaCargoLinea $ln) use ($stocks): array {
            $precioLista = (string) $ln->precio_unitario;
            $stock = '0';
            if ($ln->producto_id !== null) {
                $stock = (string) ($stocks[$ln->producto_id] ?? '0');
            }

            return [
                'producto_id' => $ln->producto_id,
                'tipo_linea' => $ln->tipo_linea,
                'concepto' => $ln->concepto,
                'cantidad' => (string) $ln->cantidad,
                'precio_lista' => $precioLista,
                'stock_sede' => $stock,
                'consulta_cargo_linea_id' => $ln->id,
            ];
        })->values()->all();

        return [
            'consulta_id' => $consulta->id,
            'consulta_cargo_id' => $cargo->id,
            'propietario_id' => (string) $propietario->id,
            'paciente_id' => $paciente->id,
            'paciente_nombre' => $paciente->nombre,
            'consulta_atendido_at' => $consulta->atendido_at?->toIso8601String(),
            'cargo_total' => (string) $cargo->total,
            'lineas_iniciales' => $lineasIniciales,
        ];
    }

    /**
     * @return array{
     *     consulta_id: ?string,
     *     consulta_cargo_id: string,
     *     propietario_id: string,
     *     paciente_id: string,
     *     paciente_nombre: string,
     *     consulta_atendido_at: ?string,
     *     cargo_total: string,
     *     lineas_iniciales: list<array<string, mixed>>,
     * }
     */
    public function buildFromInternamiento(Internamiento $internamiento): array
    {
        $internamiento->load([
            'paciente:id,nombre,propietario_id',
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden'),
        ]);

        $cargo = $internamiento->cargo;
        if ($cargo === null) {
            throw ValidationException::withMessages([
                'internamiento' => __('caja.ventas.desde_cargo.validation.sin_cargo'),
            ]);
        }

        if ($cargo->estado !== ConsultaCargo::ESTADO_CONFIRMADO) {
            throw ValidationException::withMessages([
                'internamiento' => __('caja.ventas.desde_cargo.validation.no_confirmado'),
            ]);
        }

        if ($cargo->venta_id !== null) {
            throw ValidationException::withMessages([
                'internamiento' => __('caja.ventas.desde_cargo.validation.ya_cobrado'),
            ]);
        }

        if ($cargo->lineas->isEmpty()) {
            throw ValidationException::withMessages([
                'internamiento' => __('caja.ventas.desde_cargo.validation.sin_lineas'),
            ]);
        }

        $sesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        if ($sesion === null) {
            throw ValidationException::withMessages([
                'caja' => __('caja.ventas.desde_cargo.validation.sin_sesion'),
            ]);
        }

        $paciente = $internamiento->paciente;
        $propietario = $paciente->propietario;

        $productoIds = $cargo->lineas
            ->pluck('producto_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $stocks = $productoIds === []
            ? []
            : DB::table('existencias_sede')
                ->where('sede_id', $sesion->sede_id)
                ->whereIn('producto_id', $productoIds)
                ->pluck('cantidad', 'producto_id')
                ->all();

        $lineasIniciales = $cargo->lineas->map(function (ConsultaCargoLinea $ln) use ($stocks): array {
            $precioLista = (string) $ln->precio_unitario;
            $stock = '0';
            if ($ln->producto_id !== null) {
                $stock = (string) ($stocks[$ln->producto_id] ?? '0');
            }

            return [
                'producto_id' => $ln->producto_id,
                'tipo_linea' => $ln->tipo_linea,
                'concepto' => $ln->concepto,
                'cantidad' => (string) $ln->cantidad,
                'precio_lista' => $precioLista,
                'stock_sede' => $stock,
                'consulta_cargo_linea_id' => $ln->id,
            ];
        })->values()->all();

        return [
            'consulta_id' => $internamiento->consulta_id,
            'consulta_cargo_id' => $cargo->id,
            'propietario_id' => (string) $propietario->id,
            'paciente_id' => $paciente->id,
            'paciente_nombre' => $paciente->nombre,
            'consulta_atendido_at' => $internamiento->ingreso_at->toIso8601String(),
            'cargo_total' => (string) $cargo->total,
            'lineas_iniciales' => $lineasIniciales,
        ];
    }

    /**
     * Prellenado de venta desde un turno de grooming (sin pre‑cuenta clínica):
     * una línea de servicio con el concepto del tipo realizado; **precio lista** desde
     * `grooming_servicio_tarifas` si hay tarifa **activa** para el mismo `servicio`, si no **0.00** (editable en caja).
     *
     * @return array{
     *     consulta_id: null,
     *     consulta_cargo_id: null,
     *     grooming_turno_id: string,
     *     hotel_estancia_id: null,
     *     propietario_id: string,
     *     paciente_id: string,
     *     paciente_nombre: string,
     *     consulta_atendido_at: string,
     *     cargo_total: string,
     *     lineas_iniciales: list<array{
     *         producto_id: null,
     *         tipo_linea: string,
     *         concepto: string,
     *         cantidad: string,
     *         precio_lista: string,
     *         stock_sede: string,
     *         consulta_cargo_linea_id: null,
     *     }>,
     * }
     */
    public function buildFromGrooming(GroomingTurno $turno): array
    {
        $turno->load([
            'paciente' => fn ($q) => $q->withTrashed()->select('id', 'nombre', 'propietario_id'),
            'paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
            'cargo.lineas' => fn ($q) => $q->orderBy('orden'),
            'adelantoVenta:id,numero',
        ]);

        $paciente = $turno->paciente;
        if ($paciente === null) {
            throw ValidationException::withMessages([
                'grooming' => __('caja.ventas.grooming.turno_invalido'),
            ]);
        }

        $propietario = $paciente->propietario;
        if ($propietario === null) {
            throw ValidationException::withMessages([
                'grooming' => __('caja.ventas.grooming.sin_propietario'),
            ]);
        }

        $cargo = $turno->cargo;
        if ($cargo !== null) {
            if ($cargo->estado !== ConsultaCargo::ESTADO_CONFIRMADO) {
                throw ValidationException::withMessages([
                    'grooming' => __('caja.ventas.desde_cargo.validation.no_confirmado'),
                ]);
            }

            // Fuente de verdad del cobro por pre-cuenta: cargo.venta_id (no turno.venta_id).
            if ($cargo->venta_id !== null) {
                throw ValidationException::withMessages([
                    'grooming' => __('caja.ventas.desde_cargo.validation.ya_cobrado'),
                ]);
            }

            if ($cargo->lineas->isEmpty()) {
                throw ValidationException::withMessages([
                    'grooming' => __('caja.ventas.desde_cargo.validation.sin_lineas'),
                ]);
            }

            $sesion = CajaSesion::query()
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->where('opened_by_id', Auth::id())
                ->first();

            $productoIds = $cargo->lineas
                ->pluck('producto_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $stocks = ($sesion === null || $productoIds === [])
                ? []
                : DB::table('existencias_sede')
                    ->where('sede_id', $sesion->sede_id)
                    ->whereIn('producto_id', $productoIds)
                    ->pluck('cantidad', 'producto_id')
                    ->all();

            $lineasIniciales = $cargo->lineas->map(function (ConsultaCargoLinea $ln) use ($stocks): array {
                $stock = '0';
                if ($ln->producto_id !== null) {
                    $stock = (string) ($stocks[$ln->producto_id] ?? '0');
                }

                return [
                    'producto_id' => $ln->producto_id,
                    'tipo_linea' => $ln->tipo_linea,
                    'concepto' => $ln->concepto,
                    'cantidad' => (string) $ln->cantidad,
                    'precio_lista' => (string) $ln->precio_unitario,
                    'stock_sede' => $stock,
                    'consulta_cargo_linea_id' => $ln->id,
                ];
            })->values()->all();

            return [
                'consulta_id' => null,
                'consulta_cargo_id' => $cargo->id,
                'grooming_turno_id' => $turno->id,
                'hotel_estancia_id' => null,
                'propietario_id' => (string) $propietario->id,
                'paciente_id' => $paciente->id,
                'paciente_nombre' => $paciente->nombre,
                'consulta_atendido_at' => $turno->inicio_at->toIso8601String(),
                'cargo_total' => (string) $cargo->total,
                'adelanto_monto' => $turno->tieneAdelanto() ? (string) $turno->adelanto_monto : null,
                'adelanto_venta_numero' => $turno->tieneAdelanto() ? ($turno->adelantoVenta?->numero) : null,
                'lineas_iniciales' => $lineasIniciales,
            ];
        }

        // Flujo legacy (sin pre-cuenta): el cobro vive en el turno.
        if ($turno->venta_id !== null) {
            throw ValidationException::withMessages([
                'grooming' => __('caja.ventas.grooming.ya_cobrado'),
            ]);
        }

        if ($turno->estado !== GroomingTurno::ESTADO_COMPLETADA) {
            throw ValidationException::withMessages([
                'grooming' => __('caja.ventas.grooming.no_completado'),
            ]);
        }

        $concepto = $turno->descripcionParaVenta();

        $tarifa = GroomingServicioTarifa::query()
            ->where('servicio', $turno->servicio)
            ->where('activo', true)
            ->first();
        $precioLista = '0.00';

        if ($turno->grooming_servicio_id !== null) {
            $servicioPersonalizado = GroomingServicio::query()
                ->whereKey($turno->grooming_servicio_id)
                ->where('activo', true)
                ->first();
            if ($servicioPersonalizado !== null) {
                $precioLista = number_format((float) (string) $servicioPersonalizado->precio_lista, 2, '.', '');
            }
        } elseif ($tarifa !== null) {
            $precioLista = number_format((float) (string) $tarifa->precio_lista, 2, '.', '');
        }

        return [
            'consulta_id' => null,
            'consulta_cargo_id' => null,
            'grooming_turno_id' => $turno->id,
            'hotel_estancia_id' => null,
            'propietario_id' => (string) $propietario->id,
            'paciente_id' => $paciente->id,
            'paciente_nombre' => $paciente->nombre,
            'consulta_atendido_at' => $turno->inicio_at->toIso8601String(),
            'cargo_total' => $precioLista,
            'adelanto_monto' => $turno->tieneAdelanto() ? (string) $turno->adelanto_monto : null,
            'adelanto_venta_numero' => $turno->tieneAdelanto() ? ($turno->adelantoVenta?->numero) : null,
            'lineas_iniciales' => [
                [
                    'producto_id' => null,
                    'tipo_linea' => ConsultaCargoLinea::TIPO_SERVICIO,
                    'concepto' => $concepto,
                    'cantidad' => '1.00',
                    'precio_lista' => $precioLista,
                    'stock_sede' => '0',
                    'consulta_cargo_linea_id' => null,
                ],
            ],
        ];
    }

    /**
     * Prellenado de venta desde una estancia de hotel/guardería (sin pre‑cuenta clínica):
     * línea de servicio con concepto según tipo; cantidad = noches sugeridas; precio lista 0 hasta caja.
     *
     * @return array{
     *     consulta_id: null,
     *     consulta_cargo_id: null,
     *     grooming_turno_id: null,
     *     hotel_estancia_id: string,
     *     propietario_id: string,
     *     paciente_id: string,
     *     paciente_nombre: string,
     *     consulta_atendido_at: string,
     *     cargo_total: string,
     *     lineas_iniciales: list<array{
     *         producto_id: null,
     *         tipo_linea: string,
     *         concepto: string,
     *         cantidad: string,
     *         precio_lista: string,
     *         stock_sede: string,
     *         consulta_cargo_linea_id: null,
     *     }>,
     * }
     */
    public function buildFromHotelEstancia(HotelEstancia $estancia): array
    {
        $estancia->load([
            'paciente' => fn ($q) => $q->withTrashed()->select('id', 'nombre', 'propietario_id'),
            'paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
            'cargo.lineas' => fn ($q) => $q->orderBy('orden'),
        ]);

        $paciente = $estancia->paciente;
        if ($paciente === null) {
            throw ValidationException::withMessages([
                'hotel' => __('caja.ventas.hotel.estancia_invalida'),
            ]);
        }

        $propietario = $paciente->propietario;
        if ($propietario === null) {
            throw ValidationException::withMessages([
                'hotel' => __('caja.ventas.hotel.sin_propietario'),
            ]);
        }

        $cargo = $estancia->cargo;
        if ($cargo !== null) {
            if ($cargo->estado !== ConsultaCargo::ESTADO_CONFIRMADO) {
                throw ValidationException::withMessages([
                    'hotel' => __('caja.ventas.desde_cargo.validation.no_confirmado'),
                ]);
            }

            // Fuente de verdad del cobro por pre-cuenta: cargo.venta_id (no estancia.venta_id).
            if ($cargo->venta_id !== null) {
                throw ValidationException::withMessages([
                    'hotel' => __('caja.ventas.desde_cargo.validation.ya_cobrado'),
                ]);
            }

            if ($cargo->lineas->isEmpty()) {
                throw ValidationException::withMessages([
                    'hotel' => __('caja.ventas.desde_cargo.validation.sin_lineas'),
                ]);
            }

            $sesion = CajaSesion::query()
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->where('opened_by_id', Auth::id())
                ->first();

            $productoIds = $cargo->lineas
                ->pluck('producto_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $stocks = ($sesion === null || $productoIds === [])
                ? []
                : DB::table('existencias_sede')
                    ->where('sede_id', $sesion->sede_id)
                    ->whereIn('producto_id', $productoIds)
                    ->pluck('cantidad', 'producto_id')
                    ->all();

            $lineasIniciales = $cargo->lineas->map(function (ConsultaCargoLinea $ln) use ($stocks): array {
                $stock = '0';
                if ($ln->producto_id !== null) {
                    $stock = (string) ($stocks[$ln->producto_id] ?? '0');
                }

                return [
                    'producto_id' => $ln->producto_id,
                    'tipo_linea' => $ln->tipo_linea,
                    'concepto' => $ln->concepto,
                    'cantidad' => (string) $ln->cantidad,
                    'precio_lista' => (string) $ln->precio_unitario,
                    'stock_sede' => $stock,
                    'consulta_cargo_linea_id' => $ln->id,
                ];
            })->values()->all();

            return [
                'consulta_id' => null,
                'consulta_cargo_id' => $cargo->id,
                'grooming_turno_id' => null,
                'hotel_estancia_id' => $estancia->id,
                'propietario_id' => (string) $propietario->id,
                'paciente_id' => $paciente->id,
                'paciente_nombre' => $paciente->nombre,
                'consulta_atendido_at' => $estancia->ingreso_at->toIso8601String(),
                'cargo_total' => (string) $cargo->total,
                'lineas_iniciales' => $lineasIniciales,
            ];
        }

        // Flujo legacy (sin pre-cuenta): el cobro vive en la estancia.
        if ($estancia->venta_id !== null) {
            throw ValidationException::withMessages([
                'hotel' => __('caja.ventas.hotel.ya_cobrado'),
            ]);
        }

        if ($estancia->estado !== HotelEstancia::ESTADO_COMPLETADA) {
            throw ValidationException::withMessages([
                'hotel' => __('caja.ventas.hotel.no_completado'),
            ]);
        }

        $concepto = $estancia->descripcionParaVenta();
        $noches = $estancia->nochesSugeridasParaVenta();
        $cantidad = number_format(max(1, $noches), 2, '.', '');

        $precioPorNoche = '0.00';

        if ($estancia->hotel_tipo_id !== null) {
            $tipoPersonalizado = \App\Models\HotelTipoEstancia::query()
                ->whereKey($estancia->hotel_tipo_id)
                ->where('activo', true)
                ->first();
            if ($tipoPersonalizado !== null) {
                $precioPorNoche = number_format((float) (string) $tipoPersonalizado->precio_lista, 2, '.', '');
            }
        } else {
            $tarifa = HotelEstanciaTarifa::query()
                ->where('tipo_estancia', $estancia->tipo_estancia)
                ->where('activo', true)
                ->first();

            if ($tarifa !== null) {
                $precioPorNoche = number_format((float) (string) $tarifa->precio_lista, 2, '.', '');
            }
        }

        $totalSugerido = number_format((float) $precioPorNoche * max(1, $noches), 2, '.', '');

        return [
            'consulta_id' => null,
            'consulta_cargo_id' => null,
            'grooming_turno_id' => null,
            'hotel_estancia_id' => $estancia->id,
            'propietario_id' => (string) $propietario->id,
            'paciente_id' => $paciente->id,
            'paciente_nombre' => $paciente->nombre,
            'consulta_atendido_at' => $estancia->ingreso_at->toIso8601String(),
            'cargo_total' => $totalSugerido,
            'lineas_iniciales' => [
                [
                    'producto_id' => null,
                    'tipo_linea' => ConsultaCargoLinea::TIPO_SERVICIO,
                    'concepto' => $concepto,
                    'cantidad' => $cantidad,
                    'precio_lista' => $precioPorNoche,
                    'stock_sede' => '0',
                    'consulta_cargo_linea_id' => null,
                ],
            ],
        ];
    }

    /**
     * Prefill POS desde vacunación (precuenta confirmada).
     * Las líneas producto a precio 0 (stock del paquete) no van al carrito:
     * el stock ya se descontó al confirmar cargos.
     *
     * @return array<string, mixed>
     */
    public function buildFromVacuna(VacunaAplicada $vacuna): array
    {
        $vacuna->load([
            'paciente' => fn ($q) => $q->withTrashed()->select('id', 'nombre', 'propietario_id'),
            'paciente.propietario' => fn ($q) => $q->withTrashed()->select('id', 'nombres', 'apellidos', 'razon_social'),
            'cargo.lineas' => fn ($q) => $q->orderBy('orden'),
        ]);

        $paciente = $vacuna->paciente;
        if ($paciente === null) {
            throw ValidationException::withMessages([
                'vacuna' => __('caja.ventas.vacuna.aplicacion_invalida'),
            ]);
        }

        $propietario = $paciente->propietario;
        if ($propietario === null) {
            throw ValidationException::withMessages([
                'vacuna' => __('caja.ventas.vacuna.sin_propietario'),
            ]);
        }

        $cargo = $vacuna->cargo;
        if ($cargo === null) {
            throw ValidationException::withMessages([
                'vacuna' => __('caja.ventas.desde_cargo.validation.cargo_invalido'),
            ]);
        }

        if ($cargo->estado !== ConsultaCargo::ESTADO_CONFIRMADO) {
            throw ValidationException::withMessages([
                'vacuna' => __('caja.ventas.desde_cargo.validation.no_confirmado'),
            ]);
        }

        if ($cargo->venta_id !== null) {
            throw ValidationException::withMessages([
                'vacuna' => __('caja.ventas.desde_cargo.validation.ya_cobrado'),
            ]);
        }

        if ($cargo->lineas->isEmpty()) {
            throw ValidationException::withMessages([
                'vacuna' => __('caja.ventas.desde_cargo.validation.sin_lineas'),
            ]);
        }

        $sesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        $lineasCobro = $cargo->lineas->filter(function (ConsultaCargoLinea $ln): bool {
            if ($ln->tipo_linea === ConsultaCargoLinea::TIPO_SERVICIO) {
                return true;
            }

            return (float) (string) $ln->precio_unitario > 0.0001;
        })->values();

        if ($lineasCobro->isEmpty()) {
            $lineasCobro = $cargo->lineas;
        }

        $productoIds = $lineasCobro
            ->pluck('producto_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $stocks = ($sesion === null || $productoIds === [])
            ? []
            : DB::table('existencias_sede')
                ->where('sede_id', $sesion->sede_id)
                ->whereIn('producto_id', $productoIds)
                ->pluck('cantidad', 'producto_id')
                ->all();

        $lineasIniciales = $lineasCobro->map(function (ConsultaCargoLinea $ln) use ($stocks): array {
            $stock = '0';
            if ($ln->producto_id !== null) {
                $stock = (string) ($stocks[$ln->producto_id] ?? '0');
            }

            return [
                'producto_id' => $ln->producto_id,
                'tipo_linea' => $ln->tipo_linea,
                'concepto' => $ln->concepto,
                'cantidad' => (string) $ln->cantidad,
                'precio_lista' => (string) $ln->precio_unitario,
                'stock_sede' => $stock,
                'consulta_cargo_linea_id' => $ln->id,
            ];
        })->values()->all();

        return [
            'consulta_id' => null,
            'consulta_cargo_id' => $cargo->id,
            'grooming_turno_id' => null,
            'hotel_estancia_id' => null,
            'vacuna_aplicada_id' => $vacuna->id,
            'propietario_id' => (string) $propietario->id,
            'paciente_id' => $paciente->id,
            'paciente_nombre' => $paciente->nombre,
            'consulta_atendido_at' => $vacuna->aplicada_at->toIso8601String(),
            'cargo_total' => (string) $cargo->total,
            'adelanto_monto' => null,
            'adelanto_venta_numero' => null,
            'lineas_iniciales' => $lineasIniciales,
        ];
    }
}
