<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Onboarding\ClinicOnboardingService;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirige a sedes si el tenant está en onboarding y aún no tiene sede activa.
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

        if ($tenant === null || $this->onboarding->isPreviewMode($request) || ! $this->onboarding->shouldShow($tenant)) {
            return $next($request);
        }

        if ($this->onboarding->hasActiveSede((string) $tenant->id)) {
            return $next($request);
        }

        if ($this->isWhitelisted($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('onboarding.requires_sede'),
                'redirect' => '/configuracion/sedes',
            ], 423);
        }

        return redirect()
            ->route('configuracion.sedes.index')
            ->with('flash', [
                'type' => 'warning',
                'message' => __('onboarding.requires_sede'),
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
        )) {
            return true;
        }

        if ($request->is('dashboard', 'configuracion', 'configuracion/*', 'geo/*')) {
            return true;
        }

        return false;
    }
}
