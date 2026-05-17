<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ventas.delete') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'motivo' => __('caja.ventas.anulacion.campo_motivo'),
        ];
    }
}
