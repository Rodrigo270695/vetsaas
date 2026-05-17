import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '@/pages/clinica/propietarios/types';

export type GroomingFilters = BaseFilters & {
    grooming_desde: string;
    grooming_hasta: string;
};

export type GroomingFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};

export type GroomingStats = {
    total: number;
    coincidencias: number;
};

export type PacienteGroomingOpcion = {
    id: string;
    nombre: string;
    propietario?: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
    };
};

export type UsuarioGroomingOpcion = {
    id: string;
    name: string;
};

export type SedeGroomingOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

export type GroomingServicioGrupo = {
    grupo: string;
    items: string[];
};

export type GroomingTurnoRow = {
    id: string;
    paciente_id: string;
    responsable_id: string | null;
    sede_id: string | null;
    inicio_at: string;
    duracion_minutos: number;
    estado: string;
    servicio: string;
    servicio_detalle: string | null;
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
