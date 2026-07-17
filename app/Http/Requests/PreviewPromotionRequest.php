<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ventas.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'propietario_id' => ['required', 'uuid', 'exists:propietarios,id'],
            'paciente_id' => [
                'nullable',
                'uuid',
                Rule::exists('pacientes', 'id')->where(
                    fn ($q) => $q->where('propietario_id', $this->input('propietario_id')),
                ),
            ],
            'grooming_turno_id' => ['nullable', 'uuid', 'exists:grooming_turnos,id'],
            'hotel_estancia_id' => ['nullable', 'uuid', 'exists:hotel_estancias,id'],
            'promotion_code' => ['nullable', 'string', 'max:30'],
            'lineas' => ['required', 'array', 'min:1', 'max:80'],
            'lineas.*.producto_id' => ['nullable', 'uuid', 'exists:productos,id'],
            'lineas.*.concepto' => ['nullable', 'string', 'max:300'],
            'lineas.*.precio_lista' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.descuento_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lineas.*.descuento_monto' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'lineas.*.tipo_linea' => ['nullable', 'string', Rule::in(['servicio', 'producto', 'otro'])],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.001', 'max:999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = trim((string) $this->input('promotion_code', ''));
        $this->merge(['promotion_code' => $code === '' ? null : strtoupper($code)]);
    }
}
