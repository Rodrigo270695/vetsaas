<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoAutorizacionPlantillaFromAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('config-general.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,jpg,jpeg,png,webp,gif',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'archivo' => 'archivo',
        ];
    }
}
