<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fuerza el cambio de contraseña cuando `users.must_change_password = true`.
 *
 * Casos típicos:
 *   - Admin recién provisionado vía `vetsaas:tenant-create-admin` o
 *     `ProvisionTenantJob`: nace con una contraseña temporal generada
 *     por soporte y debe definir la suya antes de operar.
 *   - Reset administrativo: soporte fuerza el flag tras un incidente
 *     de seguridad para obligar al usuario a re-elegir clave.
 *
 * Comportamiento:
 *   - Si el usuario está autenticado y tiene el flag → redirect a
 *     `password.change.form` salvo que YA esté en esa misma ruta o en
 *     `logout` (para que pueda salir si lo necesita).
 *   - Si el flag es `false` → deja pasar.
 *   - Si no hay usuario autenticado → no hacemos nada (otro middleware
 *     se encarga de pedir login).
 *
 * Va aplicado en el mismo grupo que `auth`, después de `tenant.match-user`.
 */
class EnsurePasswordIsChanged
{
    /**
     * Rutas que el usuario SIEMPRE puede visitar aunque tenga el flag
     * activo: la pantalla de cambio en sí, su endpoint POST, y logout.
     *
     * Mantenerlas en allowlist evita un bucle infinito de redirects.
     *
     * @var array<int, string>
     */
    protected array $allowedRoutes = [
        'password.change.form',
        'password.change.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user === null) {
            return $next($request);
        }

        if (! property_exists($user, 'must_change_password')
            && ! ($user instanceof \App\Models\User)) {
            return $next($request);
        }

        if ($user->must_change_password !== true) {
            return $next($request);
        }

        $currentRoute = $request->route()?->getName();
        if ($currentRoute !== null && in_array($currentRoute, $this->allowedRoutes, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(409, 'Debes cambiar tu contraseña antes de continuar.');
        }

        return redirect()->route('password.change.form');
    }
}
