<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CajaEgreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCajaEgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('caja-sesiones.egreso') ?? false;
    }

    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'medio' => ['required', 'string', Rule::in(CajaEgreso::MEDIOS)],
            'motivo' => ['required', 'string', Rule::in(CajaEgreso::MOTIVOS)],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'monto' => __('caja.attributes.egreso_monto'),
            'medio' => __('caja.attributes.egreso_medio'),
            'motivo' => __('caja.attributes.egreso_motivo'),
            'notas' => __('caja.attributes.notas'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('medio')) {
            $this->merge(['medio' => CajaEgreso::MEDIO_EFECTIVO]);
        }
    }
}
