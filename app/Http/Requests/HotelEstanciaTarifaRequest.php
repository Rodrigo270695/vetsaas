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

    public function rules(): array
    {
        $tarifa = $this->route('hotel_tarifa');
        $tarifaId = $tarifa instanceof HotelEstanciaTarifa ? $tarifa->id : null;

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
}
