<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación unificada para crear y editar roles.
 *
 * - `name` es único por (`guard_name`). Spatie ya lo enforce a nivel BD,
 *   pero validamos en form-request para que el error salga lindo al usuario.
 * - `permissions` es opcional: puedes crear un rol sin permisos y asignar
 *   después. Cada item debe ser un permiso existente del guard actual.
 * - `guard_name` SIEMPRE es `web` en este SaaS (no exponemos otros guards
 *   al usuario final). Se inyecta en `prepareForValidation` para que el
 *   frontend no pueda manipularlo.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \Spatie\Permission\Models\Role|null $role */
        $role = $this->route('role');
        $roleId = $role?->getKey();

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                // No permitimos espacios al inicio/final; lo normalizamos en prepareForValidation.
                Rule::unique(config('permission.table_names.roles'), 'name')
                    ->where('guard_name', 'web')
                    ->ignore($roleId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
        ]);
    }
}
