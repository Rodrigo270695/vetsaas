<?php

namespace App\Http\Requests;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validación para crear/editar planes desde el panel del superadmin.
 *
 * Notas:
 *   - `codigo` es el identificador interno (corto, en minúsculas) que
 *     se usa en código para resolver features (`Plan::find('starter')`).
 *     Una vez creado **no se cambia**, porque podría tener clientes vivos.
 *   - `color_hex` admite tanto formato `#RRGGBB` como `#RGB`.
 *   - Las features se gestionan vía endpoint dedicado (`updateFeatures`),
 *     no en el mismo formulario base.
 */
class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Plan|null $plan */
        $plan = $this->route('plan');
        $planId = $plan?->getKey();

        return [
            'codigo' => [
                'required',
                'string',
                'min:2',
                'max:30',
                // Solo minúsculas, dígitos y guiones bajos. Es lo que el
                // código consulta para resolver features.
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('plans', 'codigo')->ignore($planId),
            ],
            'nombre' => ['required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'badge' => ['nullable', 'string', 'max:50'],
            'color_hex' => [
                'nullable',
                'string',
                'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/',
                'max:7',
            ],
            'precio_mensual' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'precio_anual' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'orden' => ['required', 'integer', 'min:0', 'max:1000'],
            'es_publico' => ['required', 'boolean'],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'badge' => 'badge',
            'color_hex' => 'color',
            'precio_mensual' => 'precio mensual',
            'precio_anual' => 'precio anual',
            'trial_days' => 'días de prueba',
            'orden' => 'orden de despliegue',
            'es_publico' => 'visible en landing',
            'activo' => 'plan activo',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código solo admite minúsculas, dígitos y guion bajo, y debe empezar con letra.',
            'color_hex.regex' => 'El color debe estar en formato hexadecimal (ej. #1F6E4A o #1F6).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => Str::lower(trim((string) $this->input('codigo', ''))),
            'nombre' => trim((string) $this->input('nombre', '')),
            'descripcion' => filled($this->input('descripcion'))
                ? trim((string) $this->input('descripcion'))
                : null,
            'badge' => filled($this->input('badge')) ? trim((string) $this->input('badge')) : null,
            'color_hex' => filled($this->input('color_hex')) ? trim((string) $this->input('color_hex')) : null,
            'precio_anual' => filled($this->input('precio_anual')) ? $this->input('precio_anual') : null,
            'es_publico' => $this->boolean('es_publico'),
            'activo' => $this->boolean('activo'),
        ]);
    }
}
