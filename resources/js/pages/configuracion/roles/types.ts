/**
 * Tipos compartidos del módulo Roles.
 *
 * Mantengo el patrón del módulo Sedes:
 *   - Entidad principal (`Role`).
 *   - Filtros del listado (`RoleFilters`).
 *   - Stats que se muestran como badges en el `PageHeader`.
 *   - Catálogo auxiliar que viaja en cada request del index.
 */

export type RolePermissionRef = {
    id: number;
    name: string;
};

export type Role = {
    /** Spatie usa BIGINT auto-incrementado, no UUID. */
    id: number;
    name: string;
    guard_name: string;
    description: string | null;
    /** Calculado en el backend (`role.is_system`). */
    is_system: boolean;
    /** `withCount('permissions')` desde el controller. */
    permissions_count: number;
    /** Permisos eager-loaded para el modal de edición. */
    permissions: readonly RolePermissionRef[];
    created_at: string;
    updated_at: string;
};

export type RoleStats = {
    total: number;
    sistema: number;
    personalizados: number;
    /** Cantidad de coincidencias con los filtros vigentes (todas las páginas). */
    coincidencias: number;
};

export type RoleTipoFilter = 'todos' | 'sistema' | 'personalizado';

export type RoleFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    tipo: RoleTipoFilter;
};

/**
 * Permiso individual dentro del catálogo agrupado.
 * `action` es la última parte (`view`, `create`, …) para mostrar en la UI
 * sin tener que splittear cada vez.
 */
export type CatalogPermission = {
    id: number;
    name: string;
    action: string;
};

/**
 * Catálogo completo de permisos agrupado por módulo. Lo envía el
 * `RoleController::index` en cada request para mantener el modal
 * sincronizado con la BD (idempotente con `PermissionsSeeder`).
 */
export type PermissionGroup = {
    module: string;
    permissions: readonly CatalogPermission[];
};

export type PermissionsCatalog = readonly PermissionGroup[];
