<?php

namespace App\Http\Requests;

use App\Models\Consulta;
use App\Models\VacunaAplicada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVacunaAplicadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vacunaciones.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        foreach (['producto_id', 'veterinario_id', 'sede_id', 'consulta_id'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $out[$key] = null;
            }
        }
        $nd = $this->input('numero_dosis');
        if ($nd === '' || $nd === null) {
            $out['numero_dosis'] = null;
        }
        $fp = $this->input('fecha_proxima_sugerida');
        if ($fp === '' || $fp === null) {
            $out['fecha_proxima_sugerida'] = null;
        }
        $esq = $this->input('esquema_antigenos');
        if ($esq === null || $esq === '') {
            $out['esquema_antigenos'] = null;
        } elseif (is_string($esq)) {
            $t = trim($esq);
            $out['esquema_antigenos'] = $t === '' ? null : $t;
        }
        if ($out !== []) {
            $this->merge($out);
        }
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $cid = $this->input('consulta_id');
            if ($cid === null || $cid === '') {
                return;
            }
            $pid = $this->input('paciente_id');
            $consulta = Consulta::query()->with('historiaClinica:id,paciente_id')->find($cid);
            if ($consulta === null || $consulta->historiaClinica === null
                || (string) $consulta->historiaClinica->paciente_id !== (string) $pid) {
                $v->errors()->add('consulta_id', __('vacunaciones.validation.consulta_invalida'));

                return;
            }
            if ($consulta->cerrada_at !== null) {
                $v->errors()->add('consulta_id', __('vacunaciones.validation.consulta_cerrada'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = tenant_id();

        return [
            'paciente_id' => ['required', 'uuid', 'exists:pacientes,id'],
            'consulta_id' => ['nullable', 'uuid', 'exists:consultas,id'],
            'producto_id' => [
                'nullable',
                'uuid',
                Rule::exists('productos', 'id')->where(
                    fn ($q) => $q->where('medicamento', true)->where('activo', true),
                ),
            ],
            'nombre_vacuna' => ['required', 'string', 'max:500'],
            'aplicada_at' => ['required', 'date'],
            'categoria_registro' => ['required', 'string', Rule::in(VacunaAplicada::CATEGORIAS_REGISTRO)],
            'esquema_antigenos' => ['nullable', 'string', 'max:2000'],
            'fecha_proxima_sugerida' => ['nullable', 'date'],
            'numero_dosis' => ['nullable', 'integer', 'min:1', 'max:99'],
            'lote' => ['nullable', 'string', 'max:128'],
            'notas' => ['nullable', 'string', 'max:20000'],
            'veterinario_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId),
                ),
            ],
            'sede_id' => [
                $this->filled('producto_id') ? 'required' : 'nullable',
                'uuid',
                Rule::exists('sedes', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)->where('activa', true),
                ),
            ],
        ];
    }
}
