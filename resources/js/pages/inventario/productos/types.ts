import type { BaseFilters } from '@/hooks/use-data-table-page';

export type ProductoAuditUser = {
    id: string;
    name: string;
    email: string;
} | null;

export type ProductoCategoria = {
    id: string;
    nombre: string;
    slug: string | null;
} | null;

export type Producto = {
    id: string;
    categoria_id: string | null;
    nombre: string;
    slug: string | null;
    descripcion: string | null;
    sku: string | null;
    codigo_barras: string | null;
    unidad: string;
    precio_venta: string | null;
    precio_compra: string | null;
    medicamento: boolean;
    activo: boolean;
    stock_minimo: string | null;
    created_at: string;
    updated_at: string;
    categoria: ProductoCategoria;
    creado_por: ProductoAuditUser;
    actualizado_por: ProductoAuditUser;
};

export type ProductoCategoriaOption = {
    id: string;
    nombre: string;
};

export type ProductoUnidadOption = {
    id: string;
    codigo: string;
    nombre: string;
    es_sistema: boolean;
    created_at?: string;
};

export type ProductoStats = {
    total: number;
    activos: number;
    inactivos: number;
    coincidencias: number;
};

export type ProductoEstadoFilter = 'todas' | 'activa' | 'inactiva';

export type ProductoFilters = BaseFilters & {
    estado: ProductoEstadoFilter;
    /** UUID de categoría activo en filtro, o cadena vacía si no aplica. */
    categoria_id: string;
};
