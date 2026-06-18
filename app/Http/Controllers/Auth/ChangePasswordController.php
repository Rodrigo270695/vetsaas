<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Cambio obligatorio de contraseña al primer login (o tras un reset
 * administrativo del flag).
 */
class ChangePasswordController extends Controller
{
    use PasswordValidationRules;

    public function show(): Response
    {
        return Inertia::render('auth/change-password');
    }

    public function update(Request $request): HttpResponse
    {
        $user = $request->user('web');

        abort_if($user === null, 401);
        abort_if(! $user instanceof User, 401);

        try {
            $data = $request->validate([
                'password' => $this->passwordRules(),
            ]);

            if (Hash::check($data['password'], $user->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'password' => __('La nueva contraseña debe ser distinta a la actual.'),
                ]);
            }

            $updates = [
                'password' => $data['password'],
                'must_change_password' => false,
            ];

            if ($user->tenant_id !== null && $user->email_verified_at === null) {
                $updates['email_verified_at'] = now();
            }

            $user->forceFill($updates)->save();

            Auth::guard('web')->setUser($user->fresh());

            $request->session()->regenerate();

            $request->session()->flash('success', __('Tu contraseña fue actualizada.'));

            // Evita que fetch siga el 302 al dashboard en el mismo POST (el fallo
            // del dashboard se veía como 500 en /cuenta/cambiar-password).
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('dashboard'));
            }

            return redirect()->route('dashboard');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            Log::error('Fallo al cambiar contraseña obligatoria.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            @file_put_contents(
                storage_path('logs/laravel.log'),
                sprintf(
                    "[%s] password.change.ERROR user=%s: %s\n",
                    now()->toDateTimeString(),
                    $user->id,
                    $e->getMessage(),
                ),
                FILE_APPEND | LOCK_EX,
            );

            throw ValidationException::withMessages([
                'password' => __('No se pudo guardar la contraseña. Intenta de nuevo o contacta a soporte.'),
            ]);
        }
    }
}
