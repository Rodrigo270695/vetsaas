import type { TenancyShared } from '@/types/tenancy';

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
