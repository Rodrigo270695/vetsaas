<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cambio obligatorio de contraseña al primer login (o tras un reset
 * administrativo del flag).
 *
 * Funciona junto con el middleware {@see \App\Http\Middleware\EnsurePasswordIsChanged}
 * que redirige aquí mientras `users.must_change_password = true`.
 *
 * No exige la contraseña actual a propósito: el usuario llega aquí
 * porque alguien (admin / job de provisión) le configuró una clave
 * temporal que no debería conservar. Sí exige que la nueva sea
 * distinta de la actual (para que el flag tenga sentido).
 */
class ChangePasswordController extends Controller
{
    use PasswordValidationRules;

    public function show(): Response
    {
        return Inertia::render('auth/change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user('web');

        abort_if($user === null, 401);

        $data = $request->validate([
            'password' => $this->passwordRules(),
        ]);

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('La nueva contraseña debe ser distinta a la actual.'),
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()
            ->intended(route('dashboard'))
            ->with('success', __('Tu contraseña fue actualizada.'));
    }
}
