<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\ClinicAdminScope;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validación unificada para crear y editar usuarios.
 *
 * Reglas clave:
 *   - `email` único excepto cuando edito al mismo usuario.
 *   - `password` requerido al crear, OPCIONAL al editar (si viene vacío
 *     se ignora; si viene, se valida con la política de seguridad).
 *   - `role` es un único nombre de rol (un usuario = un rol). Si quieres
 *     soportar múltiples roles, cambia a `roles` (array).
 *   - `is_active` boolean — útil para suspender sin eliminar.
 *   - `created_by_id` lo establece el controller, NUNCA viene del cliente.
 */
class UserRequest extends FormRequest
{
    use ValidatesPlanIntLimits;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');
        $userId = $user?->getKey();
        $isCreate = $userId === null;
        $tenantId = tenant_id();

        $emailUnique = Rule::unique('users', 'email')
            ->whereNull('deleted_at')
            ->ignore($userId);

        if ($tenantId !== null) {
            $emailUnique = $emailUnique->where('tenant_id', $tenantId);
        } else {
            $emailUnique = $emailUnique->whereNull('tenant_id');
        }

        $roleRule = Rule::exists(config('permission.table_names.roles'), 'name')
            ->where('guard_name', 'web');

        if (ClinicAdminScope::isClinicContext()) {
            $roleRule = $roleRule
                ->where('tenant_id', tenant_id())
                ->whereNotIn('name', ClinicAdminScope::hiddenRoleNames());
        } else {
            $roleRule = $roleRule->whereNull('tenant_id');
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:150',
                $emailUnique,
            ],
            'phone' => ['nullable', 'string', 'max:32'],

            // Ficha profesional (opcional; pensada para rol veterinario).
            'documento_tipo' => ['nullable', 'string', 'max:10', Rule::in(['DNI', 'CE', 'PAS', 'OTRO'])],
            'documento_numero' => ['nullable', 'string', 'max:32'],
            'colegiatura' => ['nullable', 'string', 'max:40'],
            'cv' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
            'dni_file' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'firma' => ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
            'remove_cv' => ['sometimes', 'boolean'],
            'remove_dni_file' => ['sometimes', 'boolean'],
            'remove_firma' => ['sometimes', 'boolean'],

            // En CREATE el password es obligatorio. En UPDATE solo se valida
            // si llega un string no vacío (el usuario tecleó algo). El front
            // manda `null` cuando no quiere cambiar la contraseña.
            'password' => array_filter([
                $isCreate ? 'required' : 'nullable',
                'string',
                $isCreate || filled($this->input('password'))
                    ? Password::defaults()
                    : null,
                'confirmed',
            ]),

            'is_active' => ['required', 'boolean'],

            'role' => [
                'required',
                'string',
                $roleRule,
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre completo',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'password' => 'contraseña',
            'is_active' => 'estado',
            'role' => 'rol',
            'documento_tipo' => 'tipo de documento',
            'documento_numero' => 'número de documento',
            'colegiatura' => 'número de colegiatura',
            'cv' => 'CV',
            'dni_file' => 'documento de identidad',
            'firma' => 'firma digital',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tipo = strtoupper(trim((string) $this->input('documento_tipo', '')));
        $numero = preg_replace('/\s+/', '', (string) $this->input('documento_numero', '')) ?? '';

        if ($tipo === 'DNI') {
            $numero = preg_replace('/\D+/', '', $numero) ?? '';
        }

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'phone' => trim((string) $this->input('phone', '')) ?: null,
            'documento_tipo' => $tipo !== '' ? $tipo : null,
            'documento_numero' => $numero !== '' ? $numero : null,
            'colegiatura' => trim((string) $this->input('colegiatura', '')) ?: null,
            'is_active' => filter_var(
                $this->input('is_active', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? true,
            'remove_cv' => filter_var($this->input('remove_cv', false), FILTER_VALIDATE_BOOLEAN),
            'remove_dni_file' => filter_var($this->input('remove_dni_file', false), FILTER_VALIDATE_BOOLEAN),
            'remove_firma' => filter_var($this->input('remove_firma', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_usuarios']);

        $validator->after(function (Validator $v): void {
            if (! ClinicAdminScope::isClinicContext()) {
                return;
            }

            $role = (string) $this->input('role', '');
            if (in_array($role, Role::platformOnlyRoleNames(), true)) {
                $v->errors()->add('role', __('validation.in', ['attribute' => 'rol']));
            }

            $tipo = (string) ($this->input('documento_tipo') ?? '');
            $numero = (string) ($this->input('documento_numero') ?? '');

            if ($tipo === 'DNI' && $numero !== '' && ! preg_match('/^[0-9]{8}$/', $numero)) {
                $v->errors()->add('documento_numero', 'El DNI debe tener exactamente 8 dígitos.');
            }

            if ($numero !== '' && $tipo === '') {
                $v->errors()->add('documento_tipo', 'Indica el tipo de documento.');
            }
        });
    }
}
