<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use App\Models\Producto;
use App\Support\Inventario\UnidadMedidaOpciones;
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

        $rules = [
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
                Rule::in(UnidadMedidaOpciones::allowedCodigos()),
            ],
            'precio_venta' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'precio_compra' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'medicamento' => ['required', 'boolean'],
            'activo' => ['required', 'boolean'],
            'stock_inicial_sede_id' => [
                'nullable',
                'uuid',
                Rule::exists('sedes', 'id')->whereNull('deleted_at'),
                'required_with:stock_inicial_cantidad',
            ],
            'stock_inicial_cantidad' => ['nullable', 'numeric', 'min:0.001', 'max:999999999.999'],
            'numero_lote' => ['nullable', 'string', 'max:128'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ];

        if ($producto instanceof Producto) {
            unset(
                $rules['stock_inicial_sede_id'],
                $rules['stock_inicial_cantidad'],
                $rules['numero_lote'],
                $rules['fecha_vencimiento'],
            );
        }

        return $rules;
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

        $stockCantidad = $this->input('stock_inicial_cantidad');
        if ($stockCantidad === null || $stockCantidad === '') {
            $this->merge(['stock_inicial_cantidad' => null]);
        }

        $stockSede = trim((string) $this->input('stock_inicial_sede_id', ''));
        $this->merge([
            'stock_inicial_sede_id' => $stockSede === '' ? null : $stockSede,
        ]);

        $lote = trim((string) $this->input('numero_lote', ''));
        $this->merge([
            'numero_lote' => $lote === '' ? null : mb_substr($lote, 0, 128),
        ]);

        $venc = trim((string) $this->input('fecha_vencimiento', ''));
        $this->merge([
            'fecha_vencimiento' => $venc === '' ? null : $venc,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_productos']);
    }
}
