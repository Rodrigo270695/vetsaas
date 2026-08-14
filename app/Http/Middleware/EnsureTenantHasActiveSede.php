<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Onboarding\ClinicOnboardingService;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige sede activa con distrito (departamento/provincia/distrito) en tenants.
 *
 * Aplica a todas las clínicas (free o pago), no solo al wizard de onboarding.
 */
class EnsureTenantHasActiveSede
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ClinicOnboardingService $onboarding,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantManager->check()) {
            return $next($request);
        }

        $tenant = $this->tenantManager->current()?->tenant;

        if ($tenant === null || $this->onboarding->isPreviewMode($request)) {
            return $next($request);
        }

        $tenantId = (string) $tenant->id;

        if ($this->onboarding->hasActiveSede($tenantId)) {
            return $next($request);
        }

        if ($this->isWhitelisted($request)) {
            return $next($request);
        }

        $needsCreate = ! $this->onboarding->hasAnyActiveSede($tenantId);
        $message = $needsCreate
            ? __('onboarding.requires_sede')
            : __('onboarding.requires_sede_geo');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect' => '/configuracion/sedes',
                'needs_sede' => $needsCreate,
                'needs_sede_geo' => ! $needsCreate,
            ], 423);
        }

        return redirect()
            ->route('configuracion.sedes.index')
            ->with('flash', [
                'type' => 'warning',
                'message' => $message,
            ]);
    }

    private function isWhitelisted(Request $request): bool
    {
        if ($request->routeIs(
            'dashboard',
            'logout',
            'password.change.form',
            'password.change.update',
            'impersonate.leave',
            'configuracion.*',
            'geo.*',
            'tenant.geo.store',
        )) {
            return true;
        }

        if ($request->is('dashboard', 'configuracion', 'configuracion/*', 'geo/*', 'tenant/geo')) {
            return true;
        }

        return false;
    }
}
