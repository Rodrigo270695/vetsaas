<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsultaPlanSeguimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('historias-clinicas-planes.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registrado_at' => ['required', 'date'],
            'nota' => ['required', 'string', 'max:20000'],
        ];
    }
}
