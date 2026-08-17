<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Antes bloqueaba el acceso operativo sin sede/geo (redirect a /configuracion/sedes).
 *
 * Ahora es un no-op a propósito: el tenant entra a la plataforma sin sede por defecto
 * y se le guía con banner + onboarding (`clinic_location_gate` en Inertia).
 * No crear sede automática en el provisioner.
 *
 * Se mantiene registrado para no romper aliases de ruta / middleware stack.
 */
class EnsureTenantHasActiveSede
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
