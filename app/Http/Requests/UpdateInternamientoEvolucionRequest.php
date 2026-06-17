<?php

namespace App\Http\Requests;

class UpdateInternamientoEvolucionRequest extends StoreInternamientoEvolucionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hospitalizacion.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->stripVeterinarioFromUpdate();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['veterinario_id']);

        return $rules;
    }
}
