<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use App\Models\Producto;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoInventarioRequest extends FormRequest
{
    use ValidatesPlanIntLimits;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $producto = $this->route('producto');
        $productoId = $producto instanceof Producto ? $producto->id : null;

        return [
            'categoria_id' => [
                'nullable',
                'uuid',
                Rule::exists('categorias_productos', 'id')->whereNull('deleted_at'),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:160',
                'alpha_dash',
                Rule::unique('productos', 'slug')->ignore($productoId),
            ],
            'descripcion' => ['nullable', 'string', 'max:20000'],
            'sku' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('productos', 'sku')->ignore($productoId),
            ],
            'codigo_barras' => ['nullable', 'string', 'max:64'],
            'unidad' => [
                'required',
                'string',
                'max:20',
                Rule::exists('unidades_medida', 'codigo')->where(
                    fn ($q) => $q->where('activo', true)->whereNull('deleted_at'),
                ),
            ],
            'precio_venta' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'precio_compra' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'medicamento' => ['required', 'boolean'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = trim(mb_strtolower((string) $this->input('slug', '')));
        $sku = trim((string) $this->input('sku', ''));
        $barras = trim((string) $this->input('codigo_barras', ''));

        $unidad = strtoupper(trim((string) $this->input('unidad', '')));

        $this->merge([
            'slug' => $slug === '' ? null : $slug,
            'sku' => $sku === '' ? null : $sku,
            'codigo_barras' => $barras === '' ? null : $barras,
            'unidad' => $unidad === '' ? 'UN' : substr($unidad, 0, 20),
            'medicamento' => $this->boolean('medicamento'),
            'activo' => $this->boolean('activo'),
        ]);

        foreach (['precio_venta', 'precio_compra'] as $campoPrecio) {
            $precio = $this->input($campoPrecio);
            if ($precio === null || $precio === '') {
                $this->merge([$campoPrecio => null]);
            }
        }

        $stockMin = $this->input('stock_minimo');
        if ($stockMin === null || $stockMin === '') {
            $this->merge(['stock_minimo' => null]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_productos']);
    }
}
