<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockInventarioAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->tenant_id !== null
            && $user->can('stock.adjust');
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        if ($tenantId === null) {
            return [
                'producto_id' => ['prohibited'],
                'sede_id' => ['prohibited'],
                'cantidad' => ['prohibited'],
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
            'cantidad' => ['required', 'numeric', 'min:0', 'max:99999999.999'],
        ];
    }
}
