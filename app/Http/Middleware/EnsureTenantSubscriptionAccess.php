<?php

namespace App\Http\Middleware;

use App\Services\Subscriptions\TenantSubscriptionAccess;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra sesión si el tenant del host perdió acceso por suscripción vencida o suspendida.
 */
class EnsureTenantSubscriptionAccess
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TenantSubscriptionAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantManager->check()) {
            return $next($request);
        }

        $tenant = $this->tenantManager->current()?->tenant;

        if ($tenant === null || $this->access->allowsAccess($tenant)) {
            return $next($request);
        }

        if ($request->routeIs('logout')) {
            return $next($request);
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $denial = $this->access->resolveDenial($tenant);

        throw new \App\Tenancy\Exceptions\TenantSuspendedException($tenant, $denial ?? TenantSubscriptionAccess::DENIAL_SUSPENDED);
    }
}
