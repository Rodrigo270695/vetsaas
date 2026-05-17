<?php

namespace App\Http\Requests;

use App\Models\MovimientoInventario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovimientoInventarioStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->tenant_id !== null
            && $user->can('movimientos-stock.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        if ($tenantId === null) {
            return [
                'producto_id' => ['prohibited'],
                'sede_id' => ['prohibited'],
                'tipo' => ['prohibited'],
                'cantidad' => ['prohibited'],
                'notas' => ['prohibited'],
            ];
        }

        return [
            'producto_id' => [
                'required',
                'uuid',
                Rule::exists('productos', 'id')->whereNull('deleted_at'),
            ],
            'sede_id' => [
                'required',
                'uuid',
                Rule::exists('sedes', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'tipo' => ['required', 'string', Rule::in(MovimientoInventario::TIPOS_OPERATIVOS)],
            'cantidad' => ['required', 'numeric', 'min:0.001', 'max:99999999.999'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notas') && is_string($this->input('notas')) && trim($this->input('notas')) === '') {
            $this->merge(['notas' => null]);
        }
    }
}
