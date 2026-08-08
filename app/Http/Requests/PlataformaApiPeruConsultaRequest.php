<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Integrations\ApiPeruEndpointCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlataformaApiPeruConsultaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('plataforma-operaciones.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $keys = array_keys(ApiPeruEndpointCatalog::endpointsByKey());

        return [
            'endpoint' => ['required', 'string', Rule::in($keys)],
            'payload' => ['nullable', 'array'],
        ];
    }
}
