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
        if ($turno->estado !== GroomingTurno::ESTADO_COMPLETADA) {
            throw ValidationException::withMessages([
                'grooming' => __('caja.ventas.grooming.no_completado'),
            ]);
        }

        if ($turno->venta_id !== null) {
            throw ValidationException::withMessages([
                'grooming' => __('caja.ventas.grooming.ya_cobrado'),
            ]);
        }

        $turno->load([
            'paciente:id,nombre,propietario_id',
            'paciente.propietario:id,nombres,apellidos,razon_social',
        ]);

        $sesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        if ($sesion === null) {
            throw ValidationException::withMessages([
                'caja' => __('caja.ventas.desde_cargo.validation.sin_sesion'),
            ]);
        }

        $paciente = $turno->paciente;
        $propietario = $paciente->propietario;
        if ($propietario === null) {
            throw ValidationException::withMessages([
                'grooming' => __('caja.ventas.grooming.sin_propietario'),
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
        if ($estancia->estado !== HotelEstancia::ESTADO_COMPLETADA) {
            throw ValidationException::withMessages([
                'hotel' => __('caja.ventas.hotel.no_completado'),
            ]);
        }

        if ($estancia->venta_id !== null) {
            throw ValidationException::withMessages([
                'hotel' => __('caja.ventas.hotel.ya_cobrado'),
            ]);
        }

        $estancia->load([
            'paciente:id,nombre,propietario_id',
            'paciente.propietario:id,nombres,apellidos,razon_social',
        ]);

        $sesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();

        if ($sesion === null) {
            throw ValidationException::withMessages([
                'caja' => __('caja.ventas.desde_cargo.validation.sin_sesion'),
            ]);
        }

        $paciente = $estancia->paciente;
        $propietario = $paciente->propietario;
        if ($propietario === null) {
            throw ValidationException::withMessages([
                'hotel' => __('caja.ventas.hotel.sin_propietario'),
            ]);
        }

        $concepto = $estancia->descripcionParaVenta();
        $noches = $estancia->nochesSugeridasParaVenta();
        $cantidad = number_format(max(1, $noches), 2, '.', '');

        $tarifa = HotelEstanciaTarifa::query()
            ->where('tipo_estancia', $estancia->tipo_estancia)
            ->where('activo', true)
            ->first();

        $precioPorNoche = '0.00';
        if ($tarifa !== null) {
            $precioPorNoche = number_format((float) (string) $tarifa->precio_lista, 2, '.', '');
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
}
