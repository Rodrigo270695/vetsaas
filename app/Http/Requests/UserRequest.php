<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use App\Models\User;
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

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:150',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:32'],

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
                Rule::exists(config('permission.table_names.roles'), 'name')
                    ->where('guard_name', 'web'),
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'phone' => trim((string) $this->input('phone', '')) ?: null,
            // El front envía 'true'/'false' como string en multipart o
            // boolean en JSON. Normalizamos a boolean real.
            'is_active' => filter_var(
                $this->input('is_active', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? true,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_usuarios']);
    }
}
