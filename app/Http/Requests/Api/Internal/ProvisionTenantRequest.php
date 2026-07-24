<?php

namespace App\Http\Requests\Api\Internal;

use App\Support\Orvae\ProvisionPayloadNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class ProvisionTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(ProvisionPayloadNormalizer::normalize($this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_order_id' => ['required', 'string', 'max:120'],
            'order_number' => ['nullable', 'string', 'max:60'],
            'plan_slug' => ['required', 'string', 'max:30'],
            'ciclo' => ['nullable', 'in:mensual,trimestral,semestral,anual'],
            'tenant_slug' => ['required', 'string', 'regex:/^[a-z0-9\-]{3,60}$/'],
            'razon_social' => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:150'],
            'ruc' => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:10'],
            'canal_adquisicion' => ['nullable', 'string', 'max:50'],
            'descuento_pct' => ['nullable', 'numeric', 'between:0,100'],

            'admin_nombres' => ['required', 'string', 'max:100'],
            'admin_apellidos' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:150'],
            'admin_password' => ['required', 'string', 'min:8', 'max:200'],

            'payment' => ['nullable', 'array'],
            'payment.monto' => ['required_with:payment', 'numeric', 'min:0'],
            'payment.moneda' => ['nullable', 'in:PEN,USD'],
            'payment.total' => ['nullable', 'numeric', 'min:0'],
            'payment.igv_monto' => ['nullable', 'numeric', 'min:0'],
            'payment.descuento_monto' => ['nullable', 'numeric', 'min:0'],
            'payment.estado' => ['nullable', 'in:pendiente,procesado,fallido,reembolsado'],
            'payment.pasarela' => ['nullable', 'string', 'max:30'],
            'payment.transaction_id' => ['nullable', 'string', 'max:200'],
            'payment.pagado_at' => ['nullable', 'date'],
            'payment.raw_response' => ['nullable', 'array'],
        ];
    }
}
