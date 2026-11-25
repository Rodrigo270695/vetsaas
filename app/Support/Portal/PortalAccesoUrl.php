<?php

declare(strict_types=1);

namespace App\Support\Portal;

use App\Models\Tenant;
use App\Support\Tenancy\TenantSubdomainUrl;
use App\Tenancy\TenantManager;

final class PortalAccesoUrl
{
    public static function acceso(?Tenant $tenant = null): string
    {
        $tenant ??= app(TenantManager::class)->current()?->tenant;
        if ($tenant === null) {
            return '';
        }

        $host = strtolower(trim((string) ($tenant->portal_acceso_hostname ?? '')));
        if ($host !== '') {
            return TenantSubdomainUrl::scheme().'://'.$host.'/portal/acceso';
        }

        return TenantSubdomainUrl::scheme().'://'.$tenant->slug.'.'.TenantSubdomainUrl::rootDomain().'/portal/acceso';
    }

    public static function widgetJs(?Tenant $tenant = null): string
    {
        return self::acceso($tenant).'/widget.js';
    }
}