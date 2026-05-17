<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso a rutas que SOLO tienen sentido dentro del contexto
 * de un tenant (la app de la clínica). Se aplica como alias
 * `tenant.required` desde `bootstrap/app.php`.
 *
 * Casos:
 *   - Si hay tenant resuelto → pasa.
 *   - Si NO hay tenant resuelto y el usuario es `superadmin` →
 *     renderiza una pantalla Inertia informativa indicando que la
 *     sección requiere una clínica seleccionada y ofreciendo navegar
 *     al listado de tenants. Mejor UX que un 404 seco, y prepara el
 *     terreno para la impersonation de Fase 5.
 *
 *     IMPORTANTE: la respuesta sale con status 200. Inertia interpreta
 *     cualquier 4xx/5xx que reciba como "error", desencadenando su
 *     modal interno y descartando page.props (lo que rompe el sidebar
 *     porque `auth.user` queda indefinido). Para Inertia, una respuesta
 *     con contenido válido siempre es 200; la "información" se
 *     transmite vía el componente renderizado, no vía el código HTTP.
 *
 *   - Si NO hay tenant resuelto y el usuario NO es superadmin →
 *     404, para no exponer la existencia del módulo.
 *
 * El middleware es DRY: se aplica a cualquier ruta del grupo
 * `tenant.required` (ej. `/configuracion/general`, futuros módulos
 * tenant-scoped) sin tener que repetir lógica en cada controller.
 */
class EnsureTenant
{
    public function __construct(protected TenantManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->manager->check()) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = Auth::user();

        if ($user !== null && $user->hasRole('superadmin')) {
            return Inertia::render('shared/tenant-required', [
                'attempted_path' => '/'.ltrim($request->path(), '/'),
            ])->toResponse($request);
        }

        abort(404);
    }
}
