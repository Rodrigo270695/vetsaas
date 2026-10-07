<?php

namespace App\Http\Requests;

use App\Support\Caja\CajaBilleteras;
use Illuminate\Foundation\Http\FormRequest;

class CloseCajaSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('caja-sesiones.close') ?? false;
    }

    public function rules(): array
    {
        return [
            'saldo_cierre_efectivo' => ['required', 'numeric', 'min:0'],
            // El esperado de Yape/Plin/transferencia puede quedar bajo cero
            // si los egresos de ese canal superan lo cobrado. El modal
            // precarga ese número; min:0 rechazaba el cierre en silencio.
            ...CajaBilleteras::validationRules('saldos_cierre', true, allowNegative: true),
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'saldo_cierre_efectivo' => __('caja.attributes.saldo_cierre_efectivo'),
            ...CajaBilleteras::validationAttributes('saldos_cierre'),
            'notas' => __('caja.attributes.notas'),
        ];
    }
}
