<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInternamientoEvolucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hospitalizacion.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        foreach (['veterinario_id', 'peso_kg', 'temperatura_c', 'fc_lpm', 'fr_rpm'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $out[$key] = null;
            }
        }
        $trat = $this->input('tratamiento');
        if (is_string($trat) && trim($trat) === '') {
            $out['tratamiento'] = null;
        }
        if ($out !== []) {
            $this->merge($out);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = tenant_id();

        return [
            'registrado_at' => ['required', 'date'],
            'veterinario_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId),
                ),
            ],
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'temperatura_c' => ['nullable', 'numeric', 'min:20', 'max:45'],
            'fc_lpm' => ['nullable', 'integer', 'min:0', 'max:500'],
            'fr_rpm' => ['nullable', 'integer', 'min:0', 'max:200'],
            'evolucion' => ['required', 'string', 'max:20000'],
            'tratamiento' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
