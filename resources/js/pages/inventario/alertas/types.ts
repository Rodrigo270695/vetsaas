import type { BaseFilters } from '@/hooks/use-data-table-page';

import type { Producto } from '../productos/types';

export type AlertaTipoFiltro = 'todos' | 'agotado' | 'bajo_minimo';

/** Fila del listado de alertas (misma base que stock + tipo derivado). */
export type AlertaProductoFila = Producto & {
    existencia_id: string | null;
    cantidad_stock: string | number;
    tipo_alerta: 'agotado' | 'bajo_minimo';
};

export type AlertaStockStats = {
    agotados: number;
    bajo_minimo: number;
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
