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
     *         descuento_importe: string,
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

        $lineasIniciales = $this->mapLineasCargoParaVenta($cargo, $cargo->lineas, $stocks);

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

        $lineasIniciales = $this->mapLineasCargoParaVenta($cargo, $cargo->lineas, $stocks);

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

            $lineasIniciales = $this->mapLineasCargoParaVenta($cargo, $cargo->lineas, $stocks);

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
                    'descuento_importe' => '0.00',
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

            $lineasIniciales = $this->mapLineasCargoParaVenta($cargo, $cargo->lineas, $stocks);

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
                    'descuento_importe' => '0.00',
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

        $lineasIniciales = $this->mapLineasCargoParaVenta($cargo, $lineasCobro, $stocks);

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

    /**
     * Combina varias pre-cuentas confirmadas (mismo propietario) en un solo prefill de venta.
     *
     * @param  list<string>  $cargoIds
     * @return array{
     *     consulta_id: ?string,
     *     consulta_cargo_id: string,
     *     consulta_cargo_ids: list<string>,
     *     grooming_turno_id: ?string,
     *     hotel_estancia_id: ?string,
     *     propietario_id: string,
     *     paciente_id: ?string,
     *     paciente_nombre: ?string,
     *     consulta_atendido_at: ?string,
     *     cargo_total: string,
     *     adelanto_monto: ?string,
     *     adelanto_venta_numero: ?string,
     *     lineas_iniciales: list<array<string, mixed>>,
     * }
     */
    public function buildFromCargos(array $cargoIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $cargoIds,
            static fn ($id): bool => is_string($id) && $id !== '',
        )));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'cargo_ids' => __('caja.ventas.desde_cargo.validation.sin_cargo'),
            ]);
        }

        if (count($ids) > 30) {
            throw ValidationException::withMessages([
                'cargo_ids' => __('caja.ventas.multi.max_cargos'),
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

        /** @var \Illuminate\Database\Eloquent\Collection<int, ConsultaCargo> $cargos */
        $cargos = ConsultaCargo::query()
            ->whereIn('id', $ids)
            ->with([
                'lineas' => fn ($q) => $q->orderBy('orden'),
                'consulta.historiaClinica.paciente' => fn ($q) => $q->withTrashed(),
                'consulta.historiaClinica.paciente.propietario' => fn ($q) => $q->withTrashed(),
                'groomingTurno.paciente' => fn ($q) => $q->withTrashed(),
                'groomingTurno.paciente.propietario' => fn ($q) => $q->withTrashed(),
                'groomingTurno.adelantoVenta:id,numero',
                'hotelEstancia.paciente' => fn ($q) => $q->withTrashed(),
                'hotelEstancia.paciente.propietario' => fn ($q) => $q->withTrashed(),
                'internamiento.paciente' => fn ($q) => $q->withTrashed(),
                'internamiento.paciente.propietario' => fn ($q) => $q->withTrashed(),
                'vacunaAplicada.paciente' => fn ($q) => $q->withTrashed(),
                'vacunaAplicada.paciente.propietario' => fn ($q) => $q->withTrashed(),
            ])
            ->get()
            ->keyBy('id');

        if ($cargos->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'cargo_ids' => __('caja.ventas.desde_cargo.validation.cargo_invalido'),
            ]);
        }

        $propietarioId = null;
        $pacienteIds = [];
        $pacienteNombres = [];
        $consultaIds = [];
        $groomingIds = [];
        $hotelIds = [];
        $adelantoTotal = 0.0;
        $adelantoNumeros = [];
        $total = 0.0;
        $atendidoAt = null;
        $lineasIniciales = [];
        $productoIds = [];

        foreach ($ids as $id) {
            /** @var ConsultaCargo $cargo */
            $cargo = $cargos->get($id);
            if ($cargo->estado !== ConsultaCargo::ESTADO_CONFIRMADO) {
                throw ValidationException::withMessages([
                    'cargo_ids' => __('caja.ventas.desde_cargo.validation.no_confirmado'),
                ]);
            }
            if ($cargo->venta_id !== null) {
                throw ValidationException::withMessages([
                    'cargo_ids' => __('caja.ventas.desde_cargo.validation.ya_cobrado'),
                ]);
            }
            if ($cargo->lineas->isEmpty()) {
                throw ValidationException::withMessages([
                    'cargo_ids' => __('caja.ventas.desde_cargo.validation.sin_lineas'),
                ]);
            }

            [$propId, $pacId, $pacNombre, $fecha] = $this->resolverActoresCargo($cargo);
            if ($propId === null || $propId === '') {
                throw ValidationException::withMessages([
                    'cargo_ids' => __('caja.ventas.grooming.sin_propietario'),
                ]);
            }

            if ($propietarioId === null) {
                $propietarioId = $propId;
            } elseif ($propietarioId !== $propId) {
                throw ValidationException::withMessages([
                    'cargo_ids' => __('caja.ventas.multi.mismo_propietario'),
                ]);
            }

            if ($pacId !== null) {
                $pacienteIds[$pacId] = true;
                if ($pacNombre !== null && $pacNombre !== '') {
                    $pacienteNombres[$pacId] = $pacNombre;
                }
            }

            if ($cargo->consulta_id) {
                $consultaIds[] = $cargo->consulta_id;
            }
            if ($cargo->grooming_turno_id) {
                $groomingIds[] = $cargo->grooming_turno_id;
                $turno = $cargo->groomingTurno;
                if ($turno !== null && $turno->tieneAdelanto()) {
                    $adelantoTotal += (float) (string) $turno->adelanto_monto;
                    if ($turno->adelantoVenta?->numero) {
                        $adelantoNumeros[] = (string) $turno->adelantoVenta->numero;
                    }
                }
            }
            if ($cargo->hotel_estancia_id) {
                $hotelIds[] = $cargo->hotel_estancia_id;
            }

            $total += (float) (string) $cargo->total;
            if ($fecha !== null && ($atendidoAt === null || $fecha > $atendidoAt)) {
                $atendidoAt = $fecha;
            }

            foreach ($cargo->lineas as $ln) {
                if ($ln->producto_id !== null) {
                    $productoIds[$ln->producto_id] = true;
                }
            }
        }

        $stocks = $productoIds === []
            ? []
            : DB::table('existencias_sede')
                ->where('sede_id', $sesion->sede_id)
                ->whereIn('producto_id', array_keys($productoIds))
                ->pluck('cantidad', 'producto_id')
                ->all();

        foreach ($ids as $id) {
            /** @var ConsultaCargo $cargo */
            $cargo = $cargos->get($id);
            $lineasCargo = $cargo->lineas;
            // Misma regla que buildFromVacuna: productos a precio 0 del paquete
            // ya descontaron stock al confirmar; no van al carrito de cobro.
            if ($cargo->vacuna_aplicada_id) {
                $lineasCobro = $lineasCargo->filter(function (ConsultaCargoLinea $ln): bool {
                    if ($ln->tipo_linea === ConsultaCargoLinea::TIPO_SERVICIO) {
                        return true;
                    }

                    return (float) (string) $ln->precio_unitario > 0.0001;
                })->values();
                if ($lineasCobro->isNotEmpty()) {
                    $lineasCargo = $lineasCobro;
                }
            }
            foreach ($this->mapLineasCargoParaVenta($cargo, $lineasCargo, $stocks) as $linea) {
                $lineasIniciales[] = $linea;
            }
        }

        $pacienteUnico = count($pacienteIds) === 1 ? array_key_first($pacienteIds) : null;
        $pacienteNombre = $pacienteUnico !== null
            ? ($pacienteNombres[$pacienteUnico] ?? null)
            : (count($pacienteNombres) > 1
                ? implode(', ', array_values($pacienteNombres))
                : (array_values($pacienteNombres)[0] ?? null));

        return [
            'consulta_id' => $consultaIds[0] ?? null,
            'consulta_cargo_id' => $ids[0],
            'consulta_cargo_ids' => $ids,
            'grooming_turno_id' => $groomingIds[0] ?? null,
            'hotel_estancia_id' => $hotelIds[0] ?? null,
            'propietario_id' => (string) $propietarioId,
            'paciente_id' => $pacienteUnico,
            'paciente_nombre' => $pacienteNombre,
            'consulta_atendido_at' => $atendidoAt,
            'cargo_total' => number_format($total, 2, '.', ''),
            'adelanto_monto' => $adelantoTotal > 0.009
                ? number_format($adelantoTotal, 2, '.', '')
                : null,
            'adelanto_venta_numero' => $adelantoNumeros !== []
                ? implode(', ', array_unique($adelantoNumeros))
                : null,
            'lineas_iniciales' => $lineasIniciales,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string} propietario_id, paciente_id, paciente_nombre, fecha iso
     */
    private function resolverActoresCargo(ConsultaCargo $cargo): array
    {
        if ($cargo->vacuna_aplicada_id && $cargo->vacunaAplicada) {
            $pac = $cargo->vacunaAplicada->paciente;

            return [
                $pac?->propietario_id,
                $pac?->id,
                $pac?->nombre,
                $cargo->vacunaAplicada->aplicada_at?->toIso8601String(),
            ];
        }

        if ($cargo->grooming_turno_id && $cargo->groomingTurno) {
            $pac = $cargo->groomingTurno->paciente;

            return [
                $pac?->propietario_id,
                $pac?->id,
                $pac?->nombre,
                $cargo->groomingTurno->inicio_at?->toIso8601String(),
            ];
        }

        if ($cargo->hotel_estancia_id && $cargo->hotelEstancia) {
            $pac = $cargo->hotelEstancia->paciente;

            return [
                $pac?->propietario_id,
                $pac?->id,
                $pac?->nombre,
                $cargo->hotelEstancia->ingreso_at?->toIso8601String(),
            ];
        }

        if ($cargo->consulta_id && $cargo->consulta) {
            $pac = $cargo->consulta->historiaClinica?->paciente;

            return [
                $pac?->propietario_id,
                $pac?->id,
                $pac?->nombre,
                $cargo->consulta->atendido_at?->toIso8601String(),
            ];
        }

        if ($cargo->internamiento_id && $cargo->internamiento) {
            $pac = $cargo->internamiento->paciente;

            return [
                $pac?->propietario_id,
                $pac?->id,
                $pac?->nombre,
                $cargo->internamiento->ingreso_at?->toIso8601String(),
            ];
        }

        return [null, null, null, null];
    }

    /**
     * Detecta si los `precio_unitario` del cargo están guardados como precio bruto
     * (política “precios incluyen IGV”) o como base imponible.
     *
     * Compara la suma de líneas con `total` vs `subtotal_sin_igv` del header.
     */
    private function cargoPreciosSonBrutos(ConsultaCargo $cargo): bool
    {
        $suma = 0.0;
        foreach ($cargo->lineas as $ln) {
            $suma += max(
                0.0,
                (float) (string) $ln->cantidad * (float) (string) $ln->precio_unitario
                - (float) (string) ($ln->descuento_importe ?? 0),
            );
        }

        $suma = round($suma, 2);
        $total = round((float) (string) $cargo->total, 2);
        $sub = round((float) (string) $cargo->subtotal_sin_igv, 2);

        return abs($suma - $total) <= abs($suma - $sub);
    }

    /**
     * Convierte el PU del cargo al `precio_lista` que espera la pantalla de venta
     * según la política IGV actual de la clínica.
     *
     * Evita el desfase “precuenta 59 / cobro 50” cuando el cargo se confirmó con
     * precios sin IGV y la caja hoy asume precios con IGV incluido (o viceversa).
     */
    private function precioListaParaVenta(
        float $precioUnitario,
        bool $storedGross,
        bool $destinoIncluyeIgv,
        float $igvPct,
    ): string {
        if ($precioUnitario <= 0.0001) {
            return number_format(0, 2, '.', '');
        }

        if ($storedGross === $destinoIncluyeIgv) {
            return number_format(round($precioUnitario, 2), 2, '.', '');
        }

        $factor = 1 + max(0.0, $igvPct) / 100;
        if ($factor <= 0) {
            return number_format(round($precioUnitario, 2), 2, '.', '');
        }

        if ($destinoIncluyeIgv && ! $storedGross) {
            return number_format(round($precioUnitario * $factor, 2), 2, '.', '');
        }

        return number_format(round($precioUnitario / $factor, 2), 2, '.', '');
    }

    /**
     * El `descuento_monto` del POS siempre reduce lo que paga el cliente (bruto).
     * En precuenta, `descuento_importe` está en la misma base que `precio_unitario`
     * (bruto o neto según cómo se guardó el cargo).
     */
    private function descuentoMontoParaVenta(
        float $descuentoImporte,
        bool $storedGross,
        float $igvPct,
    ): string {
        if ($descuentoImporte <= 0.0001) {
            return number_format(0, 2, '.', '');
        }

        if ($storedGross) {
            return number_format(round($descuentoImporte, 2), 2, '.', '');
        }

        $factor = 1 + max(0.0, $igvPct) / 100;

        return number_format(round($descuentoImporte * $factor, 2), 2, '.', '');
    }

    /**
     * @param  iterable<int, ConsultaCargoLinea>  $lineas
     * @param  array<string|int, mixed>  $stocks
     * @return list<array{
     *     producto_id: ?string,
     *     tipo_linea: string,
     *     concepto: string,
     *     cantidad: string,
     *     precio_lista: string,
     *     descuento_importe: string,
     *     stock_sede: string,
     *     consulta_cargo_linea_id: string,
     * }>
     */
    private function mapLineasCargoParaVenta(ConsultaCargo $cargo, iterable $lineas, array $stocks): array
    {
        $cfg = ClinicSetting::current();
        $incluyeDestino = (bool) $cfg->precio_incluye_igv;
        $igvPct = $cfg->igvPorcentajeEfectivo();
        $storedGross = $this->cargoPreciosSonBrutos($cargo);

        $out = [];
        foreach ($lineas as $ln) {
            $stock = '0';
            if ($ln->producto_id !== null) {
                $stock = (string) ($stocks[$ln->producto_id] ?? '0');
            }

            $out[] = [
                'producto_id' => $ln->producto_id,
                'tipo_linea' => $ln->tipo_linea,
                'concepto' => $ln->concepto,
                'cantidad' => (string) $ln->cantidad,
                'precio_lista' => $this->precioListaParaVenta(
                    (float) (string) $ln->precio_unitario,
                    $storedGross,
                    $incluyeDestino,
                    $igvPct,
                ),
                'descuento_importe' => $this->descuentoMontoParaVenta(
                    (float) (string) ($ln->descuento_importe ?? 0),
                    $storedGross,
                    $igvPct,
                ),
                'stock_sede' => $stock,
                'consulta_cargo_linea_id' => $ln->id,
            ];
        }

        return $out;
    }
}
