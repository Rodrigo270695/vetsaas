/**
 * Tipos compartidos del módulo Usuarios.
 * Mismo patrón que Sedes/Roles:
 *   - Entidad principal (`User`).
 *   - Filtros del listado (`UserFilters`).
 *   - Stats para los badges del PageHeader.
 *   - Catálogo auxiliar (`UserRoleRef[]`) que viaja en cada request del index
 *     y se usa tanto en el filtro segmentado como en el `<select>` del modal.
 */

/** Mini-rol que viaja con cada usuario. */
export type UserRoleRef = {
    id: number;
    name: string;
};

/** Resumen del creador (audit trail) para mostrar en la tabla. */
export type UserCreatedByRef = {
    id: string;
    name: string;
};

export type User = {
    /** UUID. La tabla `users` usa UUID v4 desde la primera migración. */
    id: string;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    email_verified_at: string | null;
    last_login_at: string | null;
    created_at: string;
    updated_at: string;
    /** Roles asignados (a través de Spatie). */
    roles: readonly UserRoleRef[];
    /** Usuario que dio de alta a este (puede ser null para el primer superadmin). */
    created_by: UserCreatedByRef | null;
};

export type UserStats = {
    total: number;
    activos: number;
    inactivos: number;
    /** Coincidencias con los filtros vigentes (todas las páginas). */
    coincidencias: number;
};

export type UserEstadoFilter = 'todos' | 'activos' | 'inactivos';

export type UserFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: UserEstadoFilter;
    /** Nombre del rol filtrado (o null para "todos"). */
    rol: string | null;
};

/**
 * Catálogo de roles disponibles para asignar a un usuario.
 * Viene de `RoleController` filtrado por `guard_name = web`.
 */
export type UserRoleOption = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
};
