<?php

namespace App\Http\Requests\Api\Internal;

use Illuminate\Foundation\Http\FormRequest;

class RenewTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_order_id' => ['required', 'string', 'max:120'],
            'order_number' => ['nullable', 'string', 'max:60'],
            'tenant_slug' => ['required', 'string', 'regex:/^[a-z0-9\-]{3,60}$/'],
            'plan_slug' => ['required', 'string', 'max:30'],
            'ciclo' => ['nullable', 'in:mensual,trimestral,semestral,anual'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after:period_start'],
            'precio_pactado' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],

            'payment' => ['required', 'array'],
            'payment.monto' => ['required', 'numeric', 'min:0'],
            'payment.moneda' => ['nullable', 'in:PEN,USD'],
            'payment.total' => ['nullable', 'numeric', 'min:0'],
            'payment.igv_monto' => ['nullable', 'numeric', 'min:0'],
            'payment.descuento_monto' => ['nullable', 'numeric', 'min:0'],
            'payment.estado' => ['nullable', 'in:pendiente,procesado,fallido,reembolsado'],
            'payment.pasarela' => ['nullable', 'string', 'max:30'],
            'payment.transaction_id' => ['nullable', 'string', 'max:200'],
            'payment.pagado_at' => ['nullable', 'date'],
            'payment.raw_response' => ['nullable', 'array'],

            'comprobantes_overage' => ['nullable', 'array'],
            'comprobantes_overage.blocks' => ['nullable', 'integer', 'min:0'],
            'comprobantes_overage.units' => ['nullable', 'integer', 'min:0'],
            'comprobantes_overage.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
