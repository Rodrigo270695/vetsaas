/**
 * Tipos compartidos del módulo Plataforma → Suscripciones.
 *
 * Replica el patrón de Sedes/Roles/Usuarios/Tenants/Planes:
 *   - Entidad principal (`Subscription`).
 *   - Filtros del listado (`SubscriptionFilters`).
 *   - Stats para los badges del PageHeader (incluyendo MRR estimado).
 *   - Catálogos auxiliares (plans, tenants) que viajan inline.
 */

/** Estados de ciclo de vida (debe coincidir con el CHECK del Postgres). */
export type SubscriptionEstado =
    | 'trial'
    | 'active'
    | 'grace'
    | 'suspended'
    | 'cancelled';

export type SubscriptionEstadoFilter = 'todos' | SubscriptionEstado;

export type SubscriptionCiclo = 'mensual' | 'anual';

/** Mini-tenant que viaja en cada suscripción. */
export type SubscriptionTenantRef = {
    id: string;
    slug: string;
    razon_social: string;
    nombre_comercial: string | null;
    email_admin: string;
};

/** Mini-plan que viaja en cada suscripción. */
export type SubscriptionPlanRef = {
    id: string;
    codigo: string;
    nombre: string;
    badge: string | null;
    color_hex: string | null;
};

export type Subscription = {
    /** UUID. */
    id: string;
    tenant_id: string;
    plan_id: string;
    estado: SubscriptionEstado;
    ciclo: SubscriptionCiclo;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    grace_ends_at: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    cancel_feedback: string | null;
    precio_pactado: string;
    descuento_pct: string;
    proximo_cobro_at: string | null;
    created_at: string;
    updated_at: string;
    tenant: SubscriptionTenantRef | null;
    plan: SubscriptionPlanRef | null;
};

export type SubscriptionMrrByPlan = {
    plan_id: string;
    codigo: string;
    nombre: string;
    mrr: number;
    cantidad: number;
};

export type SubscriptionStats = {
    total: number;
    trial: number;
    active: number;
    grace: number;
    suspended: number;
    cancelled: number;
    /** Suma de precio_pactado de suscripciones active+grace (MRR estimado). */
    mrr: number;
    /** Desglose de MRR por plan (active+grace). */
    mrr_by_plan: readonly SubscriptionMrrByPlan[];
    /** Coincidencias con los filtros vigentes (todas las páginas). */
    coincidencias: number;
};

export type SubscriptionFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: SubscriptionEstadoFilter;
    plan_id: string | null;
};

/** Catálogo de planes para el select del filtro y del modal. */
export type SubscriptionPlanOption = {
    id: string;
    codigo: string;
    nombre: string;
    trial_days: number;
    precio_mensual: string;
    precio_anual: string | null;
    badge: string | null;
    color_hex: string | null;
};

/** Catálogo de tenants para el select del modal de creación. */
export type SubscriptionTenantOption = {
    id: string;
    slug: string;
    razon_social: string;
};
