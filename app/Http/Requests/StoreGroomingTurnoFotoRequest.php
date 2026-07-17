<?php

namespace App\Http\Requests;

use App\Models\GroomingTurnoFoto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroomingTurnoFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('grooming.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'tipo' => ['required', 'string', Rule::in(GroomingTurnoFoto::TIPOS)],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
