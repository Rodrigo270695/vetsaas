export type SedeAuditUser = {
    id: string;
    name: string;
    email: string;
} | null;

/**
 * Cadena geográfica eager-loaded para edición y display.
 * Llega desde el backend via `distritoModel.provincia.departamento`.
 */
export type SedeDistritoChain = {
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
} | null;

export type Sede = {
    id: string;
    nombre: string;
    codigo: string;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    /** FK al catálogo oficial (nullable hasta que se elija). */
    distrito_id: number | null;
    /** Cache denormalizado del nombre del distrito (auto-hidratado). */
    distrito: string | null;
    /** Cache denormalizado del nombre de la provincia. */
    provincia: string | null;
    /** Cache denormalizado del nombre del departamento. */
    departamento: string | null;
    /** Cadena completa eager-loaded (solo si se carga `with`). */
    distrito_model: SedeDistritoChain;
    serie_factura: string | null;
    serie_boleta: string | null;
    activa: boolean;
    created_at: string;
    updated_at: string;
    created_by_id: string | null;
    updated_by_id: string | null;
    creado_por: SedeAuditUser;
    actualizado_por: SedeAuditUser;
};

export type GeoOption = {
    id: number;
    name: string;
};

export type SedeStats = {
    total: number;
    activas: number;
    inactivas: number;
    /** Cantidad de coincidencias con los filtros vigentes (todas las páginas). */
    coincidencias: number;
};

export type SedeEstadoFilter = 'todas' | 'activa' | 'inactiva';

export type SedeFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: SedeEstadoFilter;
};
