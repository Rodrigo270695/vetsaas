<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrada one-shot post-provisión Orvae: token → sesión → cambiar contraseña.
 */
class BootstrapLoginController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $tenantId = tenant_id();
        abort_if($tenantId === null, 404);

        $token = trim($token);
        abort_if($token === '' || strlen($token) < 32, 404);

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->where('bootstrap_login_token', hash('sha256', $token))
            ->first();

        abort_if($user === null, 404);
        abort_unless($user->is_active === true, 403);

        if ($user->bootstrap_login_expires_at !== null
            && $user->bootstrap_login_expires_at->isPast()) {
            abort(403, 'El enlace de bienvenida expiró. Usa «Olvidé mi contraseña» en el login.');
        }

        // One-shot: invalidar al usar.
        $user->forceFill([
            'bootstrap_login_token' => null,
            'bootstrap_login_expires_at' => null,
        ])->save();

        Auth::guard('web')->login($user->fresh());
        $request->session()->regenerate();

        if ($user->must_change_password === true) {
            return redirect()
                ->route('password.change.form')
                ->with('success', __('Bienvenido. Elige tu contraseña para entrar a tu clínica.'));
        }

        return redirect()->route('dashboard');
    }
}
