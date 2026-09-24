<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInternamientoTratamientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hospitalizacion.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $detalle = $this->input('detalle');

        if (is_string($detalle) && trim($detalle) === '') {
            $this->merge(['detalle' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registrado_at' => ['required', 'date'],
            'servicio_clinico_id' => ['required', 'uuid', Rule::exists('servicios_clinicos', 'id')],
            'detalle' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
