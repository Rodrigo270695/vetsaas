<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Grooming\GroomingCatalogoServicio;
use App\Models\GroomingServicioTarifa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroomingServicioTarifaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $perm = $this->route('grooming_tarifa') ? 'tarifas.update' : 'tarifas.create';

        return $this->user()?->can($perm) ?? false;
    }

    public function rules(): array
    {
        $tarifa = $this->route('grooming_tarifa');
        $tarifaId = $tarifa instanceof GroomingServicioTarifa ? $tarifa->id : null;

        return [
            'servicio' => [
                'required',
                'string',
                Rule::in(GroomingCatalogoServicio::slugs()),
                Rule::unique('grooming_servicio_tarifas', 'servicio')->ignore($tarifaId),
            ],
            'precio_lista' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'moneda' => ['nullable', 'string', Rule::in(['PEN', 'USD'])],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
