<?php

declare(strict_types=1);

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve el slug del tenant cuando el host es un dominio personalizado
 * del portal del propietario (ej. acceso.drguau.com).
 */
final class PortalAccesoHostResolver
{
    public function resolveSlugFromHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'portal_acceso_hostname')) {
            return null;
        }

        return Cache::remember(
            'tenant.portal_acceso_host.'.$host,
            now()->addMinutes(10),
            static function () use ($host): ?string {
                $slug = Tenant::query()
                    ->where('portal_acceso_hostname', $host)
                    ->whereNull('deleted_at')
                    ->value('slug');

                return is_string($slug) && $slug !== '' ? $slug : null;
            },
        );
    }

    public static function forgetCacheForHostname(?string $hostname): void
    {
        if ($hostname === null || trim($hostname) === '') {
            return;
        }

        Cache::forget('tenant.portal_acceso_host.'.strtolower(trim($hostname)));
    }
}