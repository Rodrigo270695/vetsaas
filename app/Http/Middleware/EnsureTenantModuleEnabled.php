<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea rutas de un módulo deshabilitado para el tenant activo.
 */
class EnsureTenantModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenantId = tenant_id();

        if ($tenantId === null) {
            return $next($request);
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! TenantModuleAccess::isEnabled($tenant, $module)) {
            abort(404);
        }

        return $next($request);
    }
}
