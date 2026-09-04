<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoAutorizacionPlantillaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('config-general.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'cuerpo' => ['required', 'string', 'max:20000'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo'),
            'descripcion' => is_string($this->descripcion) && trim($this->descripcion) === ''
                ? null
                : $this->descripcion,
        ]);
    }
}
