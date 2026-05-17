<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación unificada para crear y editar sedes.
 *
 * Nota: `codigo` NO está en las reglas porque se genera automáticamente
 * en el backend (`Sede::generateNextCode($tenantId)`) al crear, y no se permite
 * modificarlo en la edición.
 */
class SedeRequest extends FormRequest
{
    use ValidatesPlanIntLimits;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            // Ubicación: el frontend manda solo `distrito_id` (cascada
            // departamento → provincia → distrito). Los strings se
            // hidratan en el controller desde la BD para mantener el
            // cache denormalizado consistente.
            'distrito_id' => ['nullable', 'integer', 'exists:distritos,id'],
            'serie_factura' => ['nullable', 'string', 'max:4'],
            'serie_boleta' => ['nullable', 'string', 'max:4'],
            'activa' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'email' => 'correo',
            'distrito_id' => 'distrito',
            'serie_factura' => 'serie factura',
            'serie_boleta' => 'serie boleta',
            'activa' => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activa' => $this->boolean('activa'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_sedes']);
    }
}
