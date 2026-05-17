import { usePage } from '@inertiajs/react';
import type { TenancyShared } from '@/types/tenancy';

const DEFAULT_TENANCY: TenancyShared = {
    root_domain: 'vetsaas.test',
    scheme: 'https',
    login_path: '/login',
};

/** Props `tenancy` compartidas por {@see HandleInertiaRequests}. */
export function useTenancy(): TenancyShared {
    const fromPage = usePage().props.tenancy as TenancyShared | undefined;

    if (
        fromPage &&
        typeof fromPage.root_domain === 'string' &&
        fromPage.root_domain !== ''
    ) {
        return fromPage;
    }

    return DEFAULT_TENANCY;
}

/**
 * URL absoluta del subdominio de una clínica.
 */
export function tenantSubdomainUrl(
    slug: string,
    tenancy: TenancyShared,
    path = '/',
): string {
    const normalizedPath =
        path === '' || path === '/' ? '/' : path.startsWith('/') ? path : `/${path}`;

    return `${tenancy.scheme}://${slug}.${tenancy.root_domain}${normalizedPath}`;
}

export function tenantLoginUrl(slug: string, tenancy: TenancyShared): string {
    return tenantSubdomainUrl(slug, tenancy, tenancy.login_path || '/login');
}
