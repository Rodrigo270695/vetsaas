<?php

namespace App\Support\Hotel;

use App\Hotel\HotelCatalogoMode;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Models\HotelTipoEstancia;
use Illuminate\Validation\Rule;

final class HotelEstanciaTipoRules
{
    /**
     * @return array<string, mixed>
     */
    public static function tipoFields(): array
    {
        if (HotelCatalogoMode::usaCatalogoPersonalizado()) {
            return [
                'hotel_tipo_id' => [
                    'required',
                    'uuid',
                    Rule::exists('hotel_tipos_estancia', 'id')->where(fn ($q) => $q->where('activo', true)),
                ],
                'tipo_estancia' => ['prohibited'],
                'tipo_detalle' => ['prohibited'],
            ];
        }

        return [
            'hotel_tipo_id' => ['prohibited'],
            'tipo_estancia' => ['required', 'string', Rule::in(HotelCatalogoTipoEstancia::slugs())],
            'tipo_detalle' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn () => request()->input('tipo_estancia') === HotelCatalogoTipoEstancia::OTRO_PERSONALIZADO),
                Rule::when(
                    request()->input('tipo_estancia') === HotelCatalogoTipoEstancia::OTRO_PERSONALIZADO,
                    ['min:3'],
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarParaPersistencia(array $data): array
    {
        if (! HotelCatalogoMode::usaCatalogoPersonalizado()) {
            unset($data['hotel_tipo_id']);

            return $data;
        }

        $tipo = HotelTipoEstancia::query()->findOrFail($data['hotel_tipo_id']);
        $data['tipo_estancia'] = $tipo->id;
        $data['hotel_tipo_id'] = $tipo->id;
        $data['tipo_detalle'] = null;

        return $data;
    }
}
