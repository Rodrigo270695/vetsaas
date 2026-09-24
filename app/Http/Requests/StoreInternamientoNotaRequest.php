<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInternamientoNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hospitalizacion.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registrado_at' => ['required', 'date'],
            'cuerpo' => ['required', 'string', 'max:20000'],
        ];
    }
}
