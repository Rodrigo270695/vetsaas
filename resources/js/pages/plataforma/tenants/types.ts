/**
 * Tipos compartidos del módulo Plataforma → Tenants.
 *
 * Espejo de los tipos de Sedes/Roles/Usuarios:
 *   - Entidad principal (`Tenant`).
 *   - Filtros del listado (`TenantFilters`).
 *   - Stats para los badges del PageHeader.
 *   - Catálogos auxiliares (plans, departamentos) que viajan inline.
 */

/** Estados de ciclo de vida del tenant (debe coincidir con el CHECK del Postgres). */
export type TenantEstado = 'trial' | 'active' | 'suspended' | 'cancelled';

export type TenantEstadoFilter = 'todos' | TenantEstado;

/** Mini-plan que viaja con la suscripción activa de cada tenant. */
export type TenantPlanRef = {
    id: string;
    codigo: string;
    nombre: string;
    badge: string | null;
    color_hex: string | null;
};

/** Suscripción viva del tenant (trial, active o grace; la más reciente). */
export type TenantSubscriptionRef = {
    id: string;
    estado: string;
    ciclo: string;
    current_period_end: string | null;
    plan: TenantPlanRef | null;
};

/** Mini-distrito eager-loaded (igual que en Sedes). */
export type TenantDistritoModelRef = {
    id: number;
    name: string;
    provincia_id: number;
    provincia: {
        id: number;
        name: string;
        departamento_id: number;
        departamento: {
            id: number;
            name: string;
        };
    };
};

export type Tenant = {
    /** UUID. La tabla `tenants` usa UUID v4. */
    id: string;
    slug: string;
    schema_name: string;
    razon_social: string;
    nombre_comercial: string | null;
    ruc: string | null;
    email_admin: string;
    telefono: string | null;
    distrito_id: number | null;
    direccion: string | null;
    logo_url: string | null;
    sunat_configurado: boolean;
    estado: TenantEstado;
    trial_ends_at: string | null;
    suspended_at: string | null;
    suspension_reason: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    onboarding_completado: boolean;
    onboarding_paso: number;
    timezone: string;
    locale: string;
    canal_adquisicion: string | null;
    created_at: string;
    updated_at: string;
    /** Última suscripción (limitada a 1 en el eager load). */
    subscriptions: readonly TenantSubscriptionRef[];
    /** Distrito eager-loaded con su jerarquía completa. */
    distrito_model: TenantDistritoModelRef | null;
};

export type TenantStats = {
    total: number;
    trial: number;
    active: number;
    suspended: number;
    cancelled: number;
    /** Coincidencias con los filtros vigentes (todas las páginas). */
    coincidencias: number;
};

/** Valor especial: tenants sin suscripción viva (trial/active/grace). */
export type TenantPlanFilterNone = 'sin_plan';

export type TenantFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: TenantEstadoFilter;
    /** UUID del plan, `sin_plan`, o null (todos). */
    plan_id: string | TenantPlanFilterNone | null;
};

/**
 * Catálogo de planes disponibles para el form de creación.
 * Viene de `TenantController::index`.
 */
export type TenantPlanOption = {
    id: string;
    codigo: string;
    nombre: string;
    trial_days: number;
    precio_mensual: string;
    color_hex: string | null;
};

/** Catálogo geográfico (compatibilidad con Sedes). */
export type GeoOption = {
    id: number;
    name: string;
};
