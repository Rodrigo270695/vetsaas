<?php

namespace App\Http\Requests;

use App\Models\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidadMedidaInventarioStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->can('productos.update') || $user->can('productos.create');
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'codigo' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('unidades_medida', 'codigo'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $codigo = strtoupper(trim((string) $this->input('codigo', '')));

        $this->merge([
            'codigo' => $codigo === '' ? null : substr($codigo, 0, 20),
        ]);
    }

    public function codigoResuelto(): string
    {
        $manual = $this->input('codigo');

        if (is_string($manual) && $manual !== '') {
            return substr(strtoupper(trim($manual)), 0, 20);
        }

        /** @var string $nombre */
        $nombre = $this->input('nombre', '');

        return UnidadMedida::uniqueCodigoDesdeNombre($nombre);
    }
}
