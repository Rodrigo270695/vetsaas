<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use App\Support\PropietarioTipoDocumento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropietarioRequest extends FormRequest
{
    use ValidatesPlanIntLimits;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['nullable', 'string', 'max:20', Rule::in(PropietarioTipoDocumento::VALUES)],
            'numero_documento' => ['nullable', 'string', 'max:20'],
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['nullable', 'string', 'max:150'],
            'razon_social' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'telefono_alt' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'distrito_id' => ['nullable', 'integer', 'exists:distritos,id'],
            'notas' => ['nullable', 'string', 'max:5000'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo_documento');
        if ($tipo === null || $tipo === '') {
            $tipoNormalizado = null;
        } elseif (is_string($tipo)) {
            $tipoNormalizado = strtoupper(trim($tipo));
            $tipoNormalizado = $tipoNormalizado === '' ? null : $tipoNormalizado;
        } else {
            $tipoNormalizado = null;
        }

        $this->merge([
            'activo' => $this->boolean('activo'),
            'tipo_documento' => $tipoNormalizado,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_propietarios']);
    }
}
