<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnidadMedidaInventarioUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('productos.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
        ];
    }
}
