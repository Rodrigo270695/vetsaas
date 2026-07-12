import type { BaseFilters } from '@/hooks/use-data-table-page';

import type { Producto } from '../productos/types';

export type StockLoteFila = {
    id: string;
    numero_lote: string | null;
    fecha_vencimiento: string | null;
    cantidad: string;
};

export type StockProductoFila = Producto & {
    existencia_id: string | null;
    cantidad_stock: string | number;
    lotes?: StockLoteFila[];
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
