import type { BaseFilters } from '@/hooks/use-data-table-page';

import type { Producto } from '../productos/types';

export type AlertaTipoFiltro = 'todos' | 'agotado' | 'bajo_minimo' | 'por_vencer' | 'vencido';

export type AlertaModoListado = 'stock' | 'lotes';

/** Fila del listado de alertas de stock (producto + existencia sede). */
export type AlertaProductoFila = Producto & {
    existencia_id: string | null;
    cantidad_stock: string | number;
    tipo_alerta: 'agotado' | 'bajo_minimo';
};

/** Fila del listado de alertas por lote (vencimiento). */
export type AlertaLoteFila = {
    id: string;
    producto_id: string;
    sede_id: string;
    numero_lote: string;
    fecha_vencimiento: string;
    cantidad_lote: string | number;
    dias_restantes: number;
    tipo_alerta: 'por_vencer' | 'vencido';
    producto_nombre: string;
    producto_sku: string | null;
    producto_slug: string | null;
    categoria?: { id: string; nombre: string; slug: string } | null;
};

export type AlertaStockStats = {
    agotados: number;
    bajo_minimo: number;
    por_vencer: number;
    vencidos: number;
    coincidencias: number;
};

export type SedeOptionAlerta = {
    id: string;
    nombre: string;
    codigo: string;
};

export type AlertaStockFilters = BaseFilters & {
    sede_id: string;
    tipo_alerta: AlertaTipoFiltro;
};
