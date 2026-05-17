<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validación para crear/editar tenants desde el panel del superadmin.
 *
 * Notas:
 *   - En operación normal los tenants nacen vía provisioner HTTP desde
 *     Orvae PE. Este endpoint manual existe para soporte, migración y
 *     pruebas internas, por eso es estricto en validaciones de slug y
 *     en la unicidad de RUC / email.
 *   - `slug` solo acepta minúsculas, dígitos y guiones (regex). Es lo
 *     que va al subdominio (`{slug}.vetsaas.com`) y al schema físico
 *     (`vet_{slug-normalizado}`).
 *   - `schema_name` se ignora si llega del cliente: lo genera el
 *     controller a partir del slug (`vet_<slug_seguro>`).
 *   - `estado` no es editable desde acá; se controla mediante endpoints
 *     dedicados (`suspend` / `resume`).
 */
class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');
        $tenantId = $tenant?->getKey();

        return [
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:60',
                // Solo minúsculas, dígitos y guiones; debe empezar/terminar con
                // alfanumérico para que sirva como subdominio válido.
                'regex:/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/',
                Rule::unique('tenants', 'slug')
                    ->whereNull('deleted_at')
                    ->ignore($tenantId),
            ],
            'razon_social' => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:150'],
            'ruc' => [
                'nullable',
                'string',
                'regex:/^\d{11}$/',
                Rule::unique('tenants', 'ruc')
                    ->whereNull('deleted_at')
                    ->ignore($tenantId),
            ],
            'email_admin' => [
                'required',
                'string',
                'email:rfc',
                'max:150',
                Rule::unique('tenants', 'email_admin')
                    ->whereNull('deleted_at')
                    ->ignore($tenantId),
            ],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'distrito_id' => [
                'nullable',
                'integer',
                Rule::exists('distritos', 'id'),
            ],
            'timezone' => ['required', 'string', 'max:50'],
            'locale' => ['required', 'string', 'max:10'],
            'canal_adquisicion' => ['nullable', 'string', 'max:50'],

            // Solo se usan en CREATE para definir el trial inicial.
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function attributes(): array
    {
        return [
            'slug' => 'subdominio',
            'razon_social' => 'razón social',
            'nombre_comercial' => 'nombre comercial',
            'ruc' => 'RUC',
            'email_admin' => 'email del administrador',
            'telefono' => 'teléfono',
            'direccion' => 'dirección',
            'distrito_id' => 'distrito',
            'timezone' => 'zona horaria',
            'locale' => 'idioma',
            'canal_adquisicion' => 'canal de adquisición',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'El subdominio solo admite minúsculas, dígitos y guiones, y no puede empezar o terminar en guion.',
            'ruc.regex' => 'El RUC debe ser exactamente 11 dígitos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalización: el slug se fuerza a minúsculas + sin espacios.
        // El email se normaliza también. El RUC null cuando viene vacío.
        $rawSlug = trim((string) $this->input('slug', ''));
        $normalizedSlug = Str::lower(preg_replace('/\s+/', '-', $rawSlug));

        $this->merge([
            'slug' => $normalizedSlug,
            'email_admin' => Str::lower(trim((string) $this->input('email_admin', ''))),
            'ruc' => filled($this->input('ruc')) ? trim((string) $this->input('ruc')) : null,
            'telefono' => filled($this->input('telefono')) ? trim((string) $this->input('telefono')) : null,
            'razon_social' => trim((string) $this->input('razon_social', '')),
            'nombre_comercial' => filled($this->input('nombre_comercial')) ? trim((string) $this->input('nombre_comercial')) : null,
            'direccion' => filled($this->input('direccion')) ? trim((string) $this->input('direccion')) : null,
            'timezone' => trim((string) $this->input('timezone', 'America/Lima')) ?: 'America/Lima',
            'locale' => trim((string) $this->input('locale', 'es_PE')) ?: 'es_PE',
        ]);
    }
}
