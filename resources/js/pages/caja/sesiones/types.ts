import type { Paginated } from '@/types';

export type CajaSesionEstadoFiltro = 'todas' | 'abierta' | 'cerrada';

export type CajaSesionFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: CajaSesionEstadoFiltro;
    sede_id: string;
};

export type CajaSesionUsuario = {
    id: string;
    name: string;
};

export type CajaSesionRow = {
    id: string;
    sede_id: string;
    sede_nombre?: string;
    estado: string;
    moneda: string;
    saldo_apertura: string;
    saldo_cierre_efectivo: string | null;
    opened_at: string;
    closed_at: string | null;
    notas: string | null;
    opened_by_id: string;
    closed_by_id: string | null;
    abierta_por?: CajaSesionUsuario | null;
    cerrada_por?: CajaSesionUsuario | null;
};

export type CajaSesionStats = {
    total: number;
    abiertas: number;
    cerradas: number;
    coincidencias: number;
};

export type SedeOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

export type CajaSesionesIndexProps = {
    sesiones: Paginated<CajaSesionRow>;
    sedes_opciones: SedeOpcion[];
    mi_sesion_abierta: (CajaSesionRow & { sede_nombre?: string }) | null;
    filters: CajaSesionFilters;
    stats: CajaSesionStats;
    sin_sedes: boolean;
};
