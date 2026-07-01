<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Tenancy\TenantModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;

class TenantModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('plataforma-tenants.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'modulos_deshabilitados' => ['present', 'array'],
            'modulos_deshabilitados.*' => ['string'],
        ];
    }

    /**
     * @return list<string>
     */
    public function validatedDisabledModules(): array
    {
        /** @var list<string> $raw */
        $raw = $this->input('modulos_deshabilitados', []);

        return TenantModuleRegistry::validateKeys($raw);
    }
}
