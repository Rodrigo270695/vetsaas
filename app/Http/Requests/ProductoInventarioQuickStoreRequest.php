<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use App\Support\Inventario\UnidadMedidaOpciones;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoInventarioQuickStoreRequest extends FormRequest
{
    use ValidatesPlanIntLimits;

    public function authorize(): bool
    {
        return $this->user()?->can('productos.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('productos', 'sku')],
            'unidad' => [
                'nullable',
                'string',
                'max:20',
                Rule::in(UnidadMedidaOpciones::allowedCodigos()),
            ],
            'precio_compra' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'precio_venta' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'medicamento' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sku = trim((string) $this->input('sku', ''));
        $unidad = strtoupper(trim((string) $this->input('unidad', '')));

        $this->merge([
            'nombre' => trim((string) $this->input('nombre', '')),
            'sku' => $sku === '' ? null : $sku,
            'unidad' => $unidad === '' ? 'UN' : substr($unidad, 0, 20),
            'medicamento' => $this->boolean('medicamento'),
        ]);

        foreach (['precio_compra', 'precio_venta'] as $campo) {
            $valor = $this->input($campo);
            if ($valor === null || $valor === '') {
                $this->merge([$campo => null]);
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_productos']);
    }
}
