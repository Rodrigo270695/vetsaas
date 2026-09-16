<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'variantes' => ['required', 'array', 'min:3', 'max:5'],
            'variantes.*' => ['required', 'string', 'min:20', 'max:1500'],
            'tope_diario' => ['required', 'integer', 'min:50', 'max:100'],
            'intervalo_minutos' => ['required', 'integer', 'min:8', 'max:30'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'imagen' => ['nullable', 'image', 'max:4096'],
            'clear_imagen' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $variantes = $this->input('variantes', []);
            if (! is_array($variantes)) {
                return;
            }
            $clean = [];
            foreach ($variantes as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $clean[] = trim($item);
                }
            }
            if (count(array_unique($clean)) < 3) {
                $validator->errors()->add(
                    'variantes',
                    'Necesitás al menos 3 textos distintos para rotar y no repetir el mismo mensaje.',
                );
            }
        });
    }
}
