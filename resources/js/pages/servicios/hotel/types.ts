import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '@/pages/clinica/propietarios/types';

export type HotelFilters = BaseFilters & {
    hotel_desde: string;
    hotel_hasta: string;
};

export type HotelFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};

export type HotelStats = {
    total: number;
    coincidencias: number;
};

export type PacienteHotelOpcion = {
    id: string;
    nombre: string;
    propietario?: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
    };
};

export type UsuarioHotelOpcion = {
    id: string;
    name: string;
};

export type SedeHotelOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

export type HotelTipoGrupo = {
    grupo: string;
    items: string[];
};

export type HotelEstanciaRow = {
    id: string;
    paciente_id: string;
    responsable_id: string | null;
    sede_id: string | null;
    ingreso_at: string;
    egreso_at: string | null;
    estado: string;
    tipo_estancia: string;
    tipo_detalle: string | null;
    notas: string | null;
    created_at: string;
    updated_at: string;
    paciente: {
        id: string;
        nombre: string;
        propietario?: {
            id: string;
            nombres: string;
            apellidos: string | null;
            razon_social: string | null;
        };
    };
    responsable: { id: string; name: string } | null;
    sede: { id: string; nombre: string; codigo: string } | null;
    venta_id: string | null;
    creado_por?: AuditUser | null;
    actualizado_por?: AuditUser | null;
};
