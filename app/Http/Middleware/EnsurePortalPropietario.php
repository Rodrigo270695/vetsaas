<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Portal\PortalPropietarioAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePortalPropietario
{
    public function __construct(
        private readonly PortalPropietarioAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = (string) config('portal.cookie', 'vetsaas_portal');
        $sesion = $this->access->resolveSession($request->cookie($cookieName));

        if ($sesion === null) {
            if ($request->inertia() || $request->expectsJson()) {
                return redirect()->route('tenant.portal.sin-acceso');
            }

            return redirect()->route('tenant.portal.sin-acceso');
        }

        $request->attributes->set('portal_sesion', $sesion);
        $request->attributes->set('portal_propietario', $sesion->portal);
        $request->attributes->set('portal_titular', $sesion->portal?->propietario);

        return $next($request);
    }
}
