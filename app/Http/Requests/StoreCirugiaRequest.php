<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AssignsAuthenticatedVeterinario;
use App\Models\Cirugia;
use App\Models\Consulta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCirugiaRequest extends FormRequest
{
    use AssignsAuthenticatedVeterinario;

    public function authorize(): bool
    {
        return $this->user()?->can('cirugias.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        $cid = $this->input('consulta_id');
        if ($cid === '' || $cid === null) {
            $out['consulta_id'] = null;
        }
        foreach (['veterinario_id', 'sede_id', 'tipo_anestesia'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $out[$key] = null;
            }
        }
        $obs = $this->input('observaciones');
        if (is_string($obs) && trim($obs) === '') {
            $out['observaciones'] = null;
        }
        if ($out !== []) {
            $this->merge($out);
        }

        $this->mergeAuthenticatedVeterinario();
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
                $v->errors()->add('consulta_id', __('cirugia.validation.consulta_invalida'));
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
            'programada_at' => ['required', 'date'],
            'estado' => ['sometimes', 'string', Rule::in(Cirugia::ESTADOS_CREACION)],
            'nombre_procedimiento' => ['required', 'string', 'max:500'],
            'tipo_anestesia' => ['nullable', 'string', 'max:120'],
            'observaciones' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
