<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\Resolvers\PortalAccesoHostResolver;
use App\Tenancy\Resolvers\SubdomainResolver;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas públicas del portal solo en el subdominio del tenant o en su
 * host personalizado (`tenants.portal_acceso_hostname`).
 */
final class EnsurePortalTenantHost
{
    public function __construct(
        private readonly TenantManager $manager,
        private readonly SubdomainResolver $subdomains,
        private readonly PortalAccesoHostResolver $portalHosts,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->manager->check()) {
            abort(404);
        }

        $host = strtolower($request->getHost());
        if ($this->subdomains->resolveFromHost($host) !== null) {
            return $next($request);
        }

        $slug = $this->portalHosts->resolveSlugFromHost($host);
        if ($slug !== null && $slug === $this->manager->slug()) {
            return $next($request);
        }

        abort(404);
    }
}