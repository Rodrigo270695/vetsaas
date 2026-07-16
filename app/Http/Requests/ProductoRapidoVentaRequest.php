<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use App\Support\Inventario\UnidadMedidaOpciones;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta rápida de producto vendible (con stock inicial) desde la pantalla de venta.
 *
 * Requiere permiso de venta y de creación de productos: quien cobra debe poder
 * dar de alta un artículo que aún no existe, sin salir del flujo de caja.
 */
class ProductoRapidoVentaRequest extends FormRequest
{
    use ValidatesPlanIntLimits;

    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('ventas.create') ?? false)
            && ($user?->can('productos.create') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
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
            'precio_venta' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'medicamento' => ['nullable', 'boolean'],
            'stock_inicial' => ['required', 'numeric', 'min:0.001', 'max:99999999.999'],
            'numero_lote' => ['nullable', 'string', 'max:128'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sku = trim((string) $this->input('sku', ''));
        $unidad = strtoupper(trim((string) $this->input('unidad', '')));
        $lote = trim((string) $this->input('numero_lote', ''));
        $venc = trim((string) $this->input('fecha_vencimiento', ''));

        $this->merge([
            'nombre' => trim((string) $this->input('nombre', '')),
            'sku' => $sku === '' ? null : $sku,
            'unidad' => $unidad === '' ? 'UN' : substr($unidad, 0, 20),
            'medicamento' => $this->boolean('medicamento'),
            'numero_lote' => $lote === '' ? null : $lote,
            'fecha_vencimiento' => $venc === '' ? null : $venc,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_productos']);
    }
}
