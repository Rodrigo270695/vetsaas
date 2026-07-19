<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el team de Spatie Permission al tenant del request (o null en central).
 *
 * Debe correr DESPUÉS de ResolveTenant para que tenant_id() ya esté disponible.
 */
final class SetPermissionsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        setPermissionsTeamId(tenant_id());

        try {
            return $next($request);
        } finally {
            // Evita filtrar el team a otro request en la misma conexión/worker.
            setPermissionsTeamId(null);
        }
    }
}
