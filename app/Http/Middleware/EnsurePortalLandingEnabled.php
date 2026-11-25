<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ClinicSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bloquea `/portal/acceso*` si el tenant no activó el acceso desde landing. */
final class EnsurePortalLandingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $setting = ClinicSetting::current();
        if (! (bool) ($setting->portal_landing_activo ?? false)) {
            abort(404);
        }

        return $next($request);
    }
}