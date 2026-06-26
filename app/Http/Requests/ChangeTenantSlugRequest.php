<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validación para cambiar el subdominio (slug) de un tenant activo en producción.
 */
class ChangeTenantSlugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return [
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/',
                Rule::unique('tenants', 'slug')
                    ->whereNull('deleted_at')
                    ->ignore($tenant->getKey()),
                Rule::notIn([$tenant->slug]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slug' => 'subdominio',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El subdominio solo admite minúsculas, dígitos y guiones, y no puede empezar o terminar en guion.',
            'slug.not_in' => 'El subdominio nuevo debe ser distinto al actual.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawSlug = trim((string) $this->input('slug', ''));

        $this->merge([
            'slug' => Str::of($rawSlug)->lower()->trim()->toString(),
        ]);
    }
}
