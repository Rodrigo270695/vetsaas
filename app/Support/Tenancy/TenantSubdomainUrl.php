<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;

/**
 * URLs absolutas de subdominios de clínica ({slug}.{root_domain}).
 *
 * Usa `config('tenant.root_domain')` (resolución HTTP) y
 * `config('orvae.tenant.scheme')` para el esquema. Mantener
 * `TENANT_ROOT_DOMAIN` alineado con `VETSAAS_TENANT_DOMAIN` en producción.
 */
final class TenantSubdomainUrl
{
    public static function rootDomain(): string
    {
        return trim((string) config('tenant.root_domain', 'vetsaas.test'));
    }

    public static function scheme(): string
    {
        return trim((string) config('orvae.tenant.scheme', 'https'));
    }

    public static function loginPath(): string
    {
        $path = (string) config('orvae.tenant.login_path', '/login');

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    public static function host(Tenant|string $tenant): string
    {
        $slug = $tenant instanceof Tenant
            ? trim((string) $tenant->slug)
            : trim($tenant);

        return $slug.'.'.self::rootDomain();
    }

    public static function build(Tenant|string $tenant, string $path = '/'): string
    {
        $path = $path === '' ? '/' : (str_starts_with($path, '/') ? $path : '/'.$path);

        return sprintf('%s://%s%s', self::scheme(), self::host($tenant), $path);
    }

    public static function login(Tenant|string $tenant): string
    {
        return self::build($tenant, self::loginPath());
    }
}
