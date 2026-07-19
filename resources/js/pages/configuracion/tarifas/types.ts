import type { Paginated } from '@/types';

export type CatalogoGrupo = {
    grupo: string;
    items: string[];
};

export type CatalogoClinicaRow = {
    id: string;
    nombre: string;
    categoria: string | null;
    categoria_id?: string | null;
    codigo_legacy: string | null;
    precio_lista: string;
    moneda: string;
    duracion_minutos?: number | null;
    activo: boolean;
    orden: number;
    /** Cantidad de insumos asignados (solo grooming personalizado). */
    insumos_count?: number;
    /** Suma del precio de los insumos asignados. */
    insumos_total?: string | number | null;
};

export type CategoriaTarifaOption = {
    id: string;
    nombre: string;
};

/** @deprecated Use CategoriaTarifaOption */
export type CategoriaServicioClinicoOption = CategoriaTarifaOption;

export type GroomingInsumoCatalogo = {
    id: string;
    nombre: string;
};

export type GroomingInsumoAsignado = {
    grooming_insumo_id: string;
    nombre: string;
    precio: string;
};

export type GroomingInsumosResponse = {
    catalogo: GroomingInsumoCatalogo[];
    asignados: GroomingInsumoAsignado[];
    moneda: string;
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

export type TarifaTab = 'grooming' | 'hotel' | 'clinica';

export type TarifaFilters = {
    grooming_search: string;
    hotel_search: string;
    clinica_search: string;
};

export type TarifaIndexProps = {
    tab: TarifaTab;
    grooming_catalogo_personalizado: boolean;
    hotel_catalogo_personalizado: boolean;
    groomingServicios: CatalogoClinicaRow[];
    hotelTipos: CatalogoClinicaRow[];
    serviciosClinicos: CatalogoClinicaRow[];
    categoriaOptions: CategoriaTarifaOption[];
    groomingCategoriaOptions: CategoriaTarifaOption[];
    hotelCategoriaOptions: CategoriaTarifaOption[];
    catalogoGrooming: CatalogoGrupo[];
    catalogoHotel: CatalogoGrupo[];
    groomingTarifas: Paginated<GroomingTarifa> | null;
    hotelTarifas: Paginated<HotelTarifa> | null;
    filters: TarifaFilters;
};
