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
                'max:8192',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp,image/gif',
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
