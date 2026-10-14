<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CotizacionLinea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sede_id' => ['nullable', 'uuid'],
            'propietario_id' => ['nullable', 'uuid', 'exists:propietarios,id'],
            'paciente_id' => ['nullable', 'uuid', 'exists:pacientes,id'],
            'cliente_nombre' => ['required', 'string', 'max:200'],
            'cliente_documento' => ['nullable', 'string', 'max:30'],
            'cliente_telefono' => ['nullable', 'string', 'max:30'],
            'cliente_email' => ['nullable', 'email', 'max:150'],
            'fecha' => ['required', 'date'],
            'vigencia_hasta' => ['required', 'date', 'after_or_equal:fecha'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'lineas' => ['required', 'array', 'min:1', 'max:100'],
            'lineas.*.tipo_linea' => ['required', Rule::in([CotizacionLinea::TIPO_PRODUCTO, CotizacionLinea::TIPO_SERVICIO])],
            'lineas.*.producto_id' => ['nullable', 'uuid', 'exists:productos,id'],
            'lineas.*.descripcion' => ['required', 'string', 'max:300'],
            'lineas.*.unidad' => ['nullable', 'string', 'max:30'],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lineas.*.descuento_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_nombre' => 'cliente',
            'cliente_email' => 'correo',
            'vigencia_hasta' => 'válida hasta',
            'lineas' => 'ítems',
            'lineas.*.descripcion' => 'descripción',
            'lineas.*.cantidad' => 'cantidad',
            'lineas.*.precio_unitario' => 'precio',
            'lineas.*.descuento_pct' => 'descuento',
        ];
    }

    public function messages(): array
    {
        return [
            'lineas.required' => 'Agrega al menos un producto o servicio.',
            'lineas.min' => 'Agrega al menos un producto o servicio.',
        ];
    }
}
