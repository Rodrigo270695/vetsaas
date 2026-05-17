import type { Paginated } from '@/types';

export type CatalogoGrupo = {
    grupo: string;
    items: string[];
};

export type GroomingTarifa = {
    id: string;
    servicio: string;
    precio_lista: string;
    moneda: string;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type HotelTarifa = {
    id: string;
    tipo_estancia: string;
    precio_lista: string;
    moneda: string;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type TarifaTab = 'grooming' | 'hotel';

export type TarifaFilters = {
    grooming_search: string;
    hotel_search: string;
};

export type TarifaIndexProps = {
    tab: TarifaTab;
    catalogoGrooming: CatalogoGrupo[];
    catalogoHotel: CatalogoGrupo[];
    groomingTarifas: Paginated<GroomingTarifa>;
    hotelTarifas: Paginated<HotelTarifa>;
    filters: TarifaFilters;
};
