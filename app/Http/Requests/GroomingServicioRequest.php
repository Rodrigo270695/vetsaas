<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroomingServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->method()) {
            'POST' => $this->canCreate(),
            'PUT', 'PATCH' => $this->canUpdate(),
            default => false,
        };
    }

    private function canCreate(): bool
    {
        $user = $this->user();

        return ($user?->can('grooming.create') ?? false) || ($user?->can('tarifas.create') ?? false);
    }

    private function canUpdate(): bool
    {
        $user = $this->user();

        return ($user?->can('grooming.update') ?? false) || ($user?->can('tarifas.update') ?? false);
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');
        if (is_string($nombre)) {
            $this->merge(['nombre' => trim($nombre)]);
        }

        $categoria = $this->input('categoria');
        if (is_string($categoria)) {
            $trim = trim($categoria);
            $this->merge(['categoria' => $trim === '' ? null : $trim]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'precio_lista' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'moneda' => ['nullable', 'string', Rule::in(['PEN', 'USD'])],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
