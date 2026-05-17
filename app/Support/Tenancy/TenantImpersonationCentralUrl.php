<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Http\Request;

/**
 * URL del panel central (login) al salir del modo soporte en un subdominio tenant.
 */
final class TenantImpersonationCentralUrl
{
    public static function originFromRequest(Request $request): string
    {
        return $request->getScheme().'://'.$request->getHttpHost();
    }

    public static function loginUrl(string $centralOrigin): string
    {
        return rtrim($centralOrigin, '/').'/login';
    }

    /**
     * Respaldo si la sesión no guardó el origen central (p. ej. impersonación anterior al fix).
     */
    public static function fallbackLoginUrl(Request $request): string
    {
        $scheme = $request->getScheme();
        $appUrl = rtrim((string) config('app.url'), '/');
        $parsed = parse_url($appUrl) ?: [];
        $host = (string) ($parsed['host'] ?? '127.0.0.1');

        if (isset($parsed['port'])) {
            $authority = $host.':'.$parsed['port'];
        } else {
            $port = (int) $request->getPort();
            $authority = $port > 0 && ! in_array($port, [80, 443], true)
                ? $host.':'.$port
                : $host;
        }

        return $scheme.'://'.$authority.'/login';
    }
}
