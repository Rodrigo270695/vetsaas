<?php

namespace App\Http\Requests;

use App\Models\GroomingTurno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoGroomingTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('grooming.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notificar_whatsapp')) {
            $v = $this->input('notificar_whatsapp');
            $this->merge([
                'notificar_whatsapp' => filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(GroomingTurno::ESTADOS)],
            'telefono' => ['nullable', 'string', 'max:20'],
            'notificar_whatsapp' => ['nullable', 'boolean'],
            'fotos' => ['nullable', 'array', 'max:8'],
            'fotos.*' => ['image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
