<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Hotel\HotelCatalogoTipoEstancia;
use App\Models\HotelEstanciaTarifa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotelEstanciaTarifaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $perm = $this->route('hotel_tarifa') ? 'tarifas.update' : 'tarifas.create';

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
        $tarifaId = $this->resolveTarifaId($this->route('hotel_tarifa'));

        return [
            'tipo_estancia' => [
                'required',
                'string',
                Rule::in(HotelCatalogoTipoEstancia::slugs()),
                Rule::unique('hotel_estancia_tarifas', 'tipo_estancia')->ignore($tarifaId),
            ],
            'precio_lista' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'moneda' => ['nullable', 'string', Rule::in(['PEN', 'USD'])],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    private function resolveTarifaId(mixed $routeParam): ?string
    {
        if ($routeParam instanceof HotelEstanciaTarifa) {
            return $routeParam->id;
        }

        if (is_string($routeParam) && $routeParam !== '') {
            return $routeParam;
        }

        return null;
    }
}
