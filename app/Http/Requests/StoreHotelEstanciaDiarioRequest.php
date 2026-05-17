<?php

namespace App\Http\Requests;

use App\Models\HotelEstancia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHotelEstanciaDiarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hotel.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $n = $this->input('notas');
        if (is_string($n) && trim($n) === '') {
            $this->merge(['notas' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $estancia = $this->route('hotel_estancia');
        $estanciaId = $estancia instanceof HotelEstancia ? $estancia->id : $estancia;

        return [
            'fecha' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('hotel_estancia_diarios', 'fecha')->where(
                    fn ($q) => $q->where('hotel_estancia_id', $estanciaId),
                ),
            ],
            'notas' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
