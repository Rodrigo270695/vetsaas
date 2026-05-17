import type { BaseFilters } from '@/hooks/use-data-table-page';

export type ProveedorAuditUser = {
    id: string;
    name: string;
    email: string;
} | null;

export type ProveedorFila = {
    id: string;
    ruc: string;
    razon_social: string;
    direccion: string | null;
    ubigeo_sunat: string | null;
    estado_sunat: string | null;
    condicion_sunat: string | null;
    telefono: string | null;
    email: string | null;
    notas: string | null;
    activo: boolean;
    created_at: string;
    updated_at: string;
    created_by_id: string | null;
    updated_by_id: string | null;
    creado_por: ProveedorAuditUser;
    actualizado_por: ProveedorAuditUser;
};

export type ProveedorStats = {
    total: number;
    activos: number;
    inactivos: number;
    coincidencias: number;
};

export type ProveedorEstadoFilter = 'todas' | 'activa' | 'inactiva';

export type ProveedorFilters = BaseFilters & {
    estado: ProveedorEstadoFilter;
};
