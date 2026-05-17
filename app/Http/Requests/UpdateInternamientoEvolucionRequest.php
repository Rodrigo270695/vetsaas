<?php

namespace App\Http\Requests;

class UpdateInternamientoEvolucionRequest extends StoreInternamientoEvolucionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hospitalizacion.update') ?? false;
    }
}
