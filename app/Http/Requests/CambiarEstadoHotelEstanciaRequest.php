<?php

namespace App\Http\Requests;

use App\Models\HotelEstancia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CambiarEstadoHotelEstanciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hotel.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in([
                HotelEstancia::ESTADO_CONFIRMADA,
                HotelEstancia::ESTADO_EN_ESTANCIA,
                HotelEstancia::ESTADO_COMPLETADA,
                HotelEstancia::ESTADO_CANCELADA,
                HotelEstancia::ESTADO_NO_PRESENTO,
            ])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('estado')) {
                return;
            }

            $estancia = $this->route('hotel_estancia');
            if (! $estancia instanceof HotelEstancia) {
                return;
            }

            $destino = (string) $this->input('estado');
            $permitidos = match ($estancia->estado) {
                HotelEstancia::ESTADO_PROGRAMADA => [
                    HotelEstancia::ESTADO_CONFIRMADA,
                    HotelEstancia::ESTADO_CANCELADA,
                    HotelEstancia::ESTADO_NO_PRESENTO,
                ],
                HotelEstancia::ESTADO_CONFIRMADA => [
                    HotelEstancia::ESTADO_EN_ESTANCIA,
                    HotelEstancia::ESTADO_CANCELADA,
                    HotelEstancia::ESTADO_NO_PRESENTO,
                ],
                HotelEstancia::ESTADO_EN_ESTANCIA => [
                    HotelEstancia::ESTADO_COMPLETADA,
                    HotelEstancia::ESTADO_CANCELADA,
                ],
                default => [],
            };

            if (! in_array($destino, $permitidos, true)) {
                $validator->errors()->add(
                    'estado',
                    'La estancia ya no permite ese cambio de estado.',
                );
            }
        });
    }
}
