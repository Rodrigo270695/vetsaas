<?php

namespace App\Http\Requests;

use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\FelSerie;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\Tenant;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caja_sesion_id' => ['required', 'uuid', 'exists:caja_sesiones,id'],
            'propietario_id' => ['required', 'uuid', 'exists:propietarios,id'],
            'paciente_id' => [
                'nullable',
                'uuid',
                Rule::exists('pacientes', 'id')->where(
                    fn ($q) => $q->where('propietario_id', $this->input('propietario_id')),
                ),
            ],
            'consulta_id' => ['nullable', 'uuid', 'exists:consultas,id'],
            'consulta_cargo_id' => ['nullable', 'uuid', 'exists:consulta_cargos,id'],
            'grooming_turno_id' => ['nullable', 'uuid', 'exists:grooming_turnos,id'],
            'hotel_estancia_id' => ['nullable', 'uuid', 'exists:hotel_estancias,id'],
            'lineas' => ['required', 'array', 'min:1', 'max:80'],
            'lineas.*.producto_id' => ['nullable', 'uuid', 'exists:productos,id'],
            'lineas.*.concepto' => ['nullable', 'string', 'max:300'],
            'lineas.*.precio_lista' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.tipo_linea' => ['nullable', 'string', Rule::in(['servicio', 'producto', 'otro'])],
            'lineas.*.consulta_cargo_linea_id' => ['nullable', 'uuid', 'exists:consulta_cargo_lineas,id'],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'metodo_pago' => ['required', 'string', Rule::in([
                'efectivo',
                'yape',
                'plin',
                'tarjeta',
                'transferencia',
            ])],
            'monto_recibido' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'tipo_comprobante_sunat' => ['nullable', 'integer', Rule::in([
                FelSerie::TIPO_FACTURA,
                FelSerie::TIPO_BOLETA,
            ])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tenant = Tenant::query()->find($this->user()?->tenant_id);
        $clinic = ClinicSetting::current();
        $puedeElegirSunat = PlanCapabilities::facturaElectronica($tenant)
            && (bool) $clinic->emite_comprobantes_sunat;

        if (! $puedeElegirSunat) {
            $this->merge(['tipo_comprobante_sunat' => null]);

            return;
        }

        $tipo = $this->input('tipo_comprobante_sunat');
        if ($tipo === null || $tipo === '' || $tipo === FelSerie::TIPO_TICKET || $tipo === '0') {
            $this->merge(['tipo_comprobante_sunat' => null]);

            return;
        }

        $this->merge(['tipo_comprobante_sunat' => (int) $tipo]);
    }

    public function attributes(): array
    {
        return [
            'caja_sesion_id' => 'sesión de caja',
            'propietario_id' => 'cliente',
            'paciente_id' => 'paciente',
            'lineas' => 'líneas',
            'metodo_pago' => 'método de pago',
            'monto_recibido' => 'monto recibido',
            'tipo_comprobante_sunat' => 'tipo de comprobante',
            'grooming_turno_id' => 'turno de grooming',
            'hotel_estancia_id' => 'estancia de hotel',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $sesionId = $this->input('caja_sesion_id');
            if (! is_string($sesionId) || $sesionId === '') {
                return;
            }

            $sesion = CajaSesion::query()->find($sesionId);
            if ($sesion === null) {
                return;
            }

            if (! $sesion->estaAbierta()) {
                $v->errors()->add('caja_sesion_id', __('caja.ventas.validation.sesion_cerrada'));

                return;
            }

            if ((string) $sesion->opened_by_id !== (string) $this->user()?->id) {
                $v->errors()->add('caja_sesion_id', __('caja.ventas.validation.sesion_no_tuya'));

                return;
            }

            if ($this->input('metodo_pago') === 'efectivo') {
                $mr = $this->input('monto_recibido');
                if ($mr === null || $mr === '') {
                    $v->errors()->add('monto_recibido', __('caja.ventas.validation.monto_recibido_efectivo'));

                    return;
                }
            }

            $gId = $this->input('grooming_turno_id');
            $hId = $this->input('hotel_estancia_id');
            if (is_string($gId) && $gId !== '' && is_string($hId) && $hId !== '') {
                $v->errors()->add('hotel_estancia_id', __('caja.ventas.hotel.origen_mixto'));

                return;
            }

            $groomingId = $this->input('grooming_turno_id');
            if (is_string($groomingId) && $groomingId !== '') {
                $turno = GroomingTurno::query()->find($groomingId);
                if ($turno === null) {
                    $v->errors()->add('grooming_turno_id', __('caja.ventas.grooming.turno_invalido'));

                    return;
                }
                if ($turno->estado !== GroomingTurno::ESTADO_COMPLETADA) {
                    $v->errors()->add('grooming_turno_id', __('caja.ventas.grooming.no_completado'));

                    return;
                }
                if ($turno->venta_id !== null) {
                    $v->errors()->add('grooming_turno_id', __('caja.ventas.grooming.ya_cobrado'));

                    return;
                }
                $pid = $this->input('paciente_id');
                if (is_string($pid) && $pid !== '' && $pid !== $turno->paciente_id) {
                    $v->errors()->add('paciente_id', __('caja.ventas.grooming.turno_invalido'));

                    return;
                }
            }

            $hotelEstanciaId = $this->input('hotel_estancia_id');
            if (is_string($hotelEstanciaId) && $hotelEstanciaId !== '') {
                $estancia = HotelEstancia::query()->find($hotelEstanciaId);
                if ($estancia === null) {
                    $v->errors()->add('hotel_estancia_id', __('caja.ventas.hotel.estancia_invalida'));

                    return;
                }
                if ($estancia->estado !== HotelEstancia::ESTADO_COMPLETADA) {
                    $v->errors()->add('hotel_estancia_id', __('caja.ventas.hotel.no_completado'));

                    return;
                }
                if ($estancia->venta_id !== null) {
                    $v->errors()->add('hotel_estancia_id', __('caja.ventas.hotel.ya_cobrado'));

                    return;
                }
                $pid = $this->input('paciente_id');
                if (is_string($pid) && $pid !== '' && $pid !== $estancia->paciente_id) {
                    $v->errors()->add('paciente_id', __('caja.ventas.hotel.estancia_invalida'));

                    return;
                }
            }

            foreach ($this->input('lineas', []) as $idx => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $pid = $row['producto_id'] ?? null;
                $concepto = trim((string) ($row['concepto'] ?? ''));
                $tieneProducto = is_string($pid) && $pid !== '';
                $tieneConcepto = $concepto !== '';

                if (! $tieneProducto && ! $tieneConcepto) {
                    $v->errors()->add("lineas.{$idx}.concepto", __('caja.ventas.validation.linea_sin_concepto'));
                }

                if (! $tieneProducto && $tieneConcepto && ! isset($row['precio_lista'])) {
                    $v->errors()->add("lineas.{$idx}.precio_lista", __('caja.ventas.validation.linea_sin_precio'));
                }
            }
        });
    }
}
