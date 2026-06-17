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

    protected function prepareForValidation(): void
    {
        if ($this->has('activo')) {
            $this->merge(['activo' => $this->boolean('activo')]);
        }
    }

    public function rules(): array
    {
        $tarifaId = $this->resolveTarifaId($this->route('grooming_tarifa'));

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

    private function resolveTarifaId(mixed $routeParam): ?string
    {
        if ($routeParam instanceof GroomingServicioTarifa) {
            return $routeParam->id;
        }

        if (is_string($routeParam) && $routeParam !== '') {
            return $routeParam;
        }

        return null;
    }
}
