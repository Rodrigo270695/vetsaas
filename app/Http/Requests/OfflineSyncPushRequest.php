<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfflineSyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ventas.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:25'],
            'items.*.uuid' => ['required', 'uuid'],
            'items.*.type' => ['required', 'string', Rule::in(['caja.venta.create'])],
            'items.*.payload' => ['required', 'array'],
        ];
    }
}
