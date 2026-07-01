/**
 * Snapshot del tenant activo, compartido por Inertia en
 * `page.props.tenant`.
 *
 * Vale `null` cuando el request entra por el dominio central (panel
 * SaaS): componentes que dependan del tenant deben hacer `if (tenant)`
 * antes de leerlo. En subdominios de cliente (`*.vetsaas.test`)
 * siempre está presente porque el middleware `tenant.required`
 * aborta el request antes de llegar al renderer.
 */
export type TenantEstado = 'trial' | 'active' | 'grace' | 'suspended' | 'cancelled';

export type TenantShared = {
    id: string;
    slug: string;
    razon_social: string;
    nombre_comercial: string | null;
    estado: TenantEstado;
    logo_url: string | null;
};
