<?php

namespace App\Http\Requests;

class UpdateInternamientoSignoRequest extends StoreInternamientoSignoRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hospitalizacion.update') ?? false;
    }
}
