export type ReporteVentasFiltros = {
    fecha_desde: string;
    fecha_hasta: string;
    periodo: string;
    tipo?: string;
};

export type ReporteVentasTotales = {
    unidades: number;
    ventas: number;
    ingresos: number;
    costo: number;
    utilidad: number | null;
    margen_pct: number | null;
    items_sin_costo: number;
};

export type ReporteVentasItem = {
    id: string;
    nombre: string;
    categoria: string | null;
    tipo: string;
    cantidad: number;
    ventas: number;
    precio_unit: number | null;
    costo_unit: number | null;
    ingreso: number;
    costo: number | null;
    utilidad: number | null;
    margen_pct: number | null;
    tiene_costo: boolean;
};

export type ReporteServicioResumenSlice = {
    unidades: number;
    ventas: number;
    ingresos: number;
    costo: number;
    utilidad: number | null;
    margen_pct: number | null;
    items: number;
    items_sin_costo: number;
};

export type ReporteServicioResumen = {
    tratamiento: ReporteServicioResumenSlice;
    vacuna: ReporteServicioResumenSlice;
    grooming: ReporteServicioResumenSlice;
};

export type SortKey =
    | 'nombre'
    | 'categoria'
    | 'tipo'
    | 'cantidad'
    | 'ventas'
    | 'precio_unit'
    | 'costo_unit'
    | 'ingreso'
    | 'costo'
    | 'utilidad'
    | 'margen_pct';

export type SortDir = 'asc' | 'desc';
