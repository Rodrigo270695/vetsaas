<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Integrations\ApiPeruEndpointCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlataformaApiPeruPerfilRequest extends FormRequest
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
        $ids = array_map(
            static fn (array $p): string => $p['id'],
            ApiPeruEndpointCatalog::profiles(),
        );

        return [
            'profile' => ['required', 'string', Rule::in($ids)],
            'payload' => ['nullable', 'array'],
        ];
    }
}
