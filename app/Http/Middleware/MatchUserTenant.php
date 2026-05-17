<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantiza que el usuario autenticado pertenece al tenant del host.
 *
 * Arquitectura "single-login + datos aislados":
 *   El mismo modelo `App\Models\User` autentica a todos. La cookie de
 *   sesión es la misma para todos los hosts (porque `SESSION_DOMAIN=null`
 *   da una cookie por host). Pero NADA impide que un atacante intente
 *   reusar su sesión apuntando a un host distinto si Laravel no valida
 *   activamente que `user.tenant_id` ↔ `request.tenant` coincidan. Este
 *   middleware hace esa validación.
 *
 * Reglas:
 *   - Si NO hay usuario autenticado → seguir (otros middlewares se
 *     encargan de pedirle login).
 *   - Si el host es CENTRAL (sin tenant resuelto):
 *       · user.tenant_id = null → OK (es del panel central).
 *       · user.tenant_id ≠ null → cerrar sesión y redirigir al login de
 *         su clínica con un mensaje claro.
 *   - Si el host es de un TENANT X:
 *       · user.tenant_id = X → OK.
 *       · user.tenant_id = null (superadmin) → OK solo si la sesión lleva
 *         `tenant_impersonation.tenant_id = X` (modo soporte explícito).
 *       · user.tenant_id = null sin esa bandera → 403 (mensaje claro).
 *       · user.tenant_id ≠ X → 403.
 */
class MatchUserTenant
{
    public function __construct(protected TenantManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user === null) {
            return $next($request);
        }

        $hostTenantId = $this->manager->check() ? $this->manager->current()?->id() : null;
        $userTenantId = $user->tenant_id;

        if ($hostTenantId !== null
            && $userTenantId === null
            && $user->hasRole('superadmin')) {
            $imp = $request->session()->get('tenant_impersonation');
            if (is_array($imp)
                && isset($imp['tenant_id'])
                && (string) $imp['tenant_id'] === (string) $hostTenantId) {
                return $next($request);
            }
        }

        // Mismo lado: ambos central o ambos en el mismo tenant.
        if ($hostTenantId === $userTenantId) {
            return $next($request);
        }

        // El usuario está en el host equivocado. Lo cerramos para evitar
        // confusión y lo mandamos al host correcto (o le mostramos 403
        // si no podemos generar la URL del destino).
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        abort(403, $this->buildMessage($hostTenantId, $userTenantId));
    }

    private function buildMessage(?string $hostTenantId, ?string $userTenantId): string
    {
        if ($hostTenantId === null && $userTenantId !== null) {
            return 'Tu cuenta pertenece a una clínica. Inicia sesión desde el subdominio de tu clínica.';
        }
        if ($hostTenantId !== null && $userTenantId === null) {
            return 'Este es el panel de una clínica. Tu cuenta es del panel central de VetSaaS.';
        }

        return 'Tu cuenta no tiene acceso a esta clínica.';
    }
}
