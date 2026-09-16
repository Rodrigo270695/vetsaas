<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppCampanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'cuerpo' => ['required', 'string', 'min:10', 'max:1500'],
            'tope_diario' => ['required', 'integer', 'min:50', 'max:100'],
            'intervalo_minutos' => ['required', 'integer', 'min:8', 'max:30'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'imagen' => ['nullable', 'image', 'max:4096'],
            'clear_imagen' => ['sometimes', 'boolean'],
        ];
    }
}
