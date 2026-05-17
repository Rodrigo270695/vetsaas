<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * URL absoluta al endpoint de consumo de token en el host del tenant.
 */
final class TenantImpersonationAcceptUrl
{
    public static function build(Tenant $tenant, string $token, Request $request): string
    {
        $slug = trim((string) $tenant->slug);
        $root = trim((string) config('tenant.root_domain'));

        $scheme = $request->getScheme();
        $host = $slug.'.'.$root;

        $port = $request->getPort();
        $authority = $host;
        if ($port !== null && ! in_array((int) $port, [80, 443], true)) {
            $authority .= ':'.$port;
        }

        return $scheme.'://'.$authority.'/impersonate/accept?token='.rawurlencode($token);
    }
}
