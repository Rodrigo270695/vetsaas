<?php

namespace App\Http\Requests;

use App\Models\Consulta;
use App\Models\Internamiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInternamientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hospitalizacion.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        $cid = $this->input('consulta_id');
        if ($cid === '' || $cid === null) {
            $out['consulta_id'] = null;
        }
        foreach (['veterinario_id', 'sede_id', 'ubicacion', 'alta_at'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $out[$key] = null;
            }
        }
        foreach (['diagnostico_ingreso', 'notas'] as $key) {
            $obs = $this->input($key);
            if (is_string($obs) && trim($obs) === '') {
                $out[$key] = null;
            }
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
                $v->errors()->add('consulta_id', __('hospitalizacion.validation.consulta_invalida'));
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
            'paciente_id' => [
                'required',
                'uuid',
                Rule::exists('pacientes', 'id')->where(
                    fn ($q) => $q->where('activo', true),
                ),
            ],
            'consulta_id' => ['nullable', 'uuid', 'exists:consultas,id'],
            'veterinario_id' => [
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
            'ingreso_at' => ['required', 'date'],
            'alta_at' => ['nullable', 'date'],
            'estado' => ['sometimes', 'string', Rule::in(Internamiento::ESTADOS_CREACION)],
            'motivo_ingreso' => ['required', 'string', 'max:500'],
            'ubicacion' => ['nullable', 'string', 'max:120'],
            'diagnostico_ingreso' => ['nullable', 'string', 'max:20000'],
            'notas' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
