/**
 * Tipos compartidos del módulo Plataforma → Planes.
 *
 * Replica el patrón de Sedes/Roles/Usuarios/Tenants:
 *   - Entidad principal (`Plan`).
 *   - Filtros del listado (`PlanFilters`).
 *   - Stats para los badges del PageHeader.
 *   - Catálogo de features que viaja inline para hidratar el modal.
 */

export type PlanEstadoFilter =
    | 'todos'
    | 'activos'
    | 'inactivos'
    | 'publicos'
    | 'privados';

/** Tipo de valor que admite una feature en `plan_features`. */
export type PlanFeatureType = 'int' | 'bool' | 'str';

/**
 * Entrada del catálogo de features que el backend envía con cada
 * index() para que el modal sepa qué inputs pintar.
 */
export type PlanFeatureCatalogEntry = {
    feature: string;
    type: PlanFeatureType;
    group: string;
    default: number | boolean | string | null;
};

/** Fila de la tabla `plan_features` (valor concreto para un plan). */
export type PlanFeatureRow = {
    feature: string;
    valor_int: number | null;
    valor_bool: boolean | null;
    valor_str: string | null;
};

export type Plan = {
    /** UUID. La tabla `plans` usa UUID v4. */
    id: string;
    codigo: string;
    nombre: string;
    descripcion: string | null;
    badge: string | null;
    color_hex: string | null;
    precio_mensual: string;
    precio_anual: string | null;
    trial_days: number;
    orden: number;
    es_publico: boolean;
    activo: boolean;
    created_at: string;
    updated_at: string;
    /** Conteos cargados con `withCount` en el controller. */
    features_count: number;
    subscriptions_count: number;
    /** Features asociadas (eager-loaded en cada index). */
    features: readonly PlanFeatureRow[];
};

export type PlanStats = {
    total: number;
    activos: number;
    inactivos: number;
    publicos: number;
    /** Coincidencias con los filtros vigentes (todas las páginas). */
    coincidencias: number;
};

export type PlanFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: PlanEstadoFilter;
};
