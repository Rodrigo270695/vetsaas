export type SedeOptionCompra = {
    id: string;
    nombre: string;
    codigo: string;
};

export type ProveedorOptionCompra = {
    id: string;
    ruc: string;
    razon_social: string;
};

export type ProductoOptionCompra = {
    id: string;
    nombre: string;
    sku: string | null;
};

export type ProductoUnidadOptionCompra = {
    id: string;
    codigo: string;
    nombre: string;
    es_sistema: boolean;
};

export type CompraProveedorFila = {
    id: string;
    ruc: string;
    razon_social: string;
};

export type CompraLineaFila = {
    id: string;
    cantidad: string;
    costo_unitario: string | null;
    numero_lote: string | null;
    fecha_vencimiento: string | null;
    producto: {
        id: string;
        nombre: string;
        sku: string | null;
    };
};

export type CompraCreadoPor = {
    id: string;
    name: string;
} | null;

export type CompraFila = {
    id: string;
    proveedor_id: string | null;
    sede_id: string;
    fecha_documento: string;
    numero_documento: string | null;
    serie: string | null;
    moneda: string;
    total: string | null;
    notas: string | null;
    factura_path: string | null;
    factura_original_name: string | null;
    created_at: string;
    anulada_at?: string | null;
    proveedor?: CompraProveedorFila | null;
    creado_por?: CompraCreadoPor;
    lineas_count?: number;
    lineas?: CompraLineaFila[];
    sede_nombre?: string;
    sede_codigo?: string | null;
};

export type CompraStats = {
    total: number;
    coincidencias: number;
};

export type CompraFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    sede_id: string;
    proveedor_id: string | null;
    /** Rango aplicado a `fecha_documento` (inclusive, día calendario en zona de la app). */
    fecha_desde: string;
    fecha_hasta: string;
};

/** Metadatos solo para UI del filtro de fechas (no van en la query del modal de alta). */
export type CompraFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};
