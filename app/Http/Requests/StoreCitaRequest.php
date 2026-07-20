<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AssignsAuthenticatedVeterinario;
use App\Support\Citas\CitaInicioValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCitaRequest extends FormRequest
{
    use AssignsAuthenticatedVeterinario;

    public function authorize(): bool
    {
        return $this->user()?->can('citas.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        foreach (['veterinario_id', 'sede_id'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $out[$key] = null;
            }
        }
        $m = $this->input('motivo');
        if (is_string($m) && trim($m) === '') {
            $out['motivo'] = null;
        }
        $n = $this->input('notas');
        if (is_string($n) && trim($n) === '') {
            $out['notas'] = null;
        }
        if ($out !== []) {
            $this->merge($out);
        }

        $this->mergeAuthenticatedVeterinario();
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
            'inicio_at' => ['required', 'date'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'notas' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $raw = $this->input('inicio_at');
            if (! is_string($raw)) {
                return;
            }

            if (CitaInicioValidator::isPast($raw)) {
                $validator->errors()->add('inicio_at', __('citas.validation.inicio_pasado'));
            }
        });
    }
}
