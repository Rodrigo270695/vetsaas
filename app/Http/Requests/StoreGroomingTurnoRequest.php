<?php

namespace App\Http\Requests;

use App\Grooming\GroomingCatalogoServicio;
use App\Http\Requests\Concerns\AssignsAuthenticatedResponsable;
use App\Models\CajaSesion;
use App\Models\VentaPago;
use App\Support\Grooming\GroomingTurnoServicioRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGroomingTurnoRequest extends FormRequest
{
    use AssignsAuthenticatedResponsable;

    public function authorize(): bool
    {
        return $this->user()?->can('grooming.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        foreach (['responsable_id', 'sede_id', 'adelanto_monto', 'adelanto_monto_recibido'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $out[$key] = null;
            }
        }
        $n = $this->input('notas');
        if (is_string($n) && trim($n) === '') {
            $out['notas'] = null;
        }
        $s = $this->input('servicio');
        if (is_string($s)) {
            $out['servicio'] = trim($s);
        }
        $sd = $this->input('servicio_detalle');
        if (is_string($sd)) {
            $trim = trim($sd);
            $out['servicio_detalle'] = $trim === '' ? null : $trim;
        }

        $svc = $out['servicio'] ?? (is_string($s) ? trim($s) : null);
        if ($svc !== GroomingCatalogoServicio::OTRO_PERSONALIZADO) {
            $out['servicio_detalle'] = null;
        }
        if ($out !== []) {
            $this->merge($out);
        }

        $this->mergeAuthenticatedResponsable();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = tenant_id();

        return [
            'paciente_id' => [
                'required',
                'uuid',
                Rule::exists('pacientes', 'id')->where(
                    fn ($q) => $q->where('activo', true),
                ),
            ],
            'responsable_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId),
                ),
            ],
            'sede_id' => [
                'nullable',
                'uuid',
                Rule::exists('sedes', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)->where('activa', true),
                ),
            ],
            'inicio_at' => ['required', 'date'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            ...GroomingTurnoServicioRules::servicioFields(),
            'notas' => ['nullable', 'string', 'max:20000'],
            'adelanto_monto' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'adelanto_metodo_pago' => [
                'nullable',
                'required_with:adelanto_monto',
                'string',
                Rule::in(VentaPago::METODOS),
            ],
            'adelanto_monto_recibido' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $monto = $this->input('adelanto_monto');
            if ($monto === null || $monto === '') {
                return;
            }

            $user = $this->user();
            if ($user === null || ! $user->can('ventas.create')) {
                $v->errors()->add('adelanto_monto', __('grooming.validation.adelanto_sin_permiso'));

                return;
            }

            $sesionAbierta = CajaSesion::query()
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->where('opened_by_id', $user->getAuthIdentifier())
                ->exists();

            if (! $sesionAbierta) {
                $v->errors()->add('adelanto_monto', __('caja.ventas.desde_cargo.validation.sin_sesion'));
            }
        });
    }
}
