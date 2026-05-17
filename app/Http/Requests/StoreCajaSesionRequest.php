<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCajaSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('caja-sesiones.open') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'sede_id' => [
                'required',
                'uuid',
                Rule::exists('sedes', 'id')->where(function ($q) use ($tenantId): void {
                    if ($tenantId !== null) {
                        $q->where('tenant_id', $tenantId)->where('activa', true)->whereNull('deleted_at');
                    }
                }),
            ],
            'moneda' => ['required', Rule::in(['PEN', 'USD'])],
            'saldo_apertura' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sede_id' => __('caja.attributes.sede_id'),
            'moneda' => __('caja.attributes.moneda'),
            'saldo_apertura' => __('caja.attributes.saldo_apertura'),
            'notas' => __('caja.attributes.notas'),
        ];
    }
}
