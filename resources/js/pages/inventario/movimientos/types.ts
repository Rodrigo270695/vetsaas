import type { BaseFilters } from '@/hooks/use-data-table-page';

export type MovimientoTipoFiltro = 'todos' | 'entrada' | 'salida' | 'merma' | 'ajuste';

export type MovimientoUsuario = {
    id: string;
    name: string;
} | null;

export type MovimientoProducto = {
    id: string;
    nombre: string;
    sku: string | null;
};

export type MovimientoFila = {
    id: string;
    producto_id: string;
    sede_id: string;
    tipo: string;
    delta: string | number;
    stock_anterior: string | number;
    stock_despues: string | number;
    notas: string | null;
    /** Texto ya formateado para UI (compras, JSON legado, etc.). */
    notas_vista?: string | null;
    created_at: string;
    sede_nombre?: string;
    sede_codigo?: string | null;
    producto: MovimientoProducto;
    creado_por: MovimientoUsuario;
};

export type MovimientoStats = {
    total: number;
    coincidencias: number;
};

export type ProductoOptionMovimiento = {
    id: string;
    nombre: string;
    sku: string | null;
};

export type SedeOptionMovimiento = {
    id: string;
    nombre: string;
    codigo: string;
};

export type MovimientoFilters = BaseFilters & {
    sede_id: string;
    tipo: MovimientoTipoFiltro;
    /** Rango aplicado a `created_at` (inclusive, día calendario en zona de la app). */
    creado_desde: string;
    creado_hasta: string;
};

/** Metadatos solo para UI (no van en la query string del listado). */
export type MovimientoFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};
