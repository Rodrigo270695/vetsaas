<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use App\Models\Propietario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PacienteRequest extends FormRequest
{
    use ValidatesPlanIntLimits;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'nombre' => ['required', 'string', 'max:120'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'clear_foto' => ['nullable', 'boolean'],
            'especie' => ['nullable', 'string', 'max:80'],
            'raza' => ['nullable', 'string', 'max:120'],
            'sexo' => ['nullable', 'string', 'size:1', Rule::in(['M', 'H', 'U'])],
            'fecha_nacimiento' => ['nullable', 'date'],
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'microchip' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:80'],
            'esterilizado' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:5000'],
            'activo' => ['required', 'boolean'],
        ];

        if ($this->isMethod('POST')) {
            // Incluido en validated(): merge desde ruta anidada o envío en POST /pacientes.
            $rules['propietario_id'] = ['required', 'uuid', 'exists:propietarios,id'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $routePropietario = $this->route('propietario');
        if ($routePropietario instanceof Propietario) {
            $this->merge([
                'propietario_id' => $routePropietario->getKey(),
            ]);
        } elseif (is_string($routePropietario) && $routePropietario !== '') {
            $this->merge([
                'propietario_id' => $routePropietario,
            ]);
        }

        if ($this->has('esterilizado')) {
            $this->merge([
                'esterilizado' => $this->boolean('esterilizado'),
            ]);
        }

        if ($this->has('clear_foto')) {
            $this->merge([
                'clear_foto' => $this->boolean('clear_foto'),
            ]);
        }

        $this->merge([
            'activo' => $this->boolean('activo'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_pacientes']);
    }
}
