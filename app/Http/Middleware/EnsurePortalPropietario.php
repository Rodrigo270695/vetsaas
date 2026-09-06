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
            $identityName = (string) config('portal.identity_cookie', 'vetsaas_portal_id');
            $portal = $this->access->findByIdentity($request->cookie($identityName));

            if ($portal !== null) {
                return redirect()->route('tenant.portal.pin');
            }

            return redirect()->route('tenant.portal.sin-acceso');
        }

        $request->attributes->set('portal_sesion', $sesion);
        $request->attributes->set('portal_propietario', $sesion->portal);
        $request->attributes->set('portal_titular', $sesion->portal?->propietario);

        return $next($request);
    }
}
