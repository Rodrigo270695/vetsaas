import type { BaseFilters } from '@/hooks/use-data-table-page';

import type { Producto } from '../productos/types';

export type StockProductoFila = Producto & {
    existencia_id: string | null;
    cantidad_stock: string | number;
};

export type StockStats = {
    total: number;
    coincidencias: number;
};

export type SedeOption = {
    id: string;
    nombre: string;
    codigo: string;
};

export type StockFilters = BaseFilters & {
    sede_id: string;
};
