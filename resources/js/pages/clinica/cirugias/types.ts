import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '../propietarios/types';

export type CirugiaFilters = BaseFilters & {
    programada_desde: string;
    programada_hasta: string;
    estado: string;
};

export type CirugiaFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};

export type CirugiaStats = {
    total: number;
    coincidencias: number;
};

export type PacienteCirugiaOpcion = {
    id: string;
    nombre: string;
    propietario?: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
    };
};

export type UsuarioCirugiaOpcion = {
    id: string;
    name: string;
};

export type SedeCirugiaOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

export type ConsultaCirugiaOpcion = {
    id: string;
    atendido_at: string;
    historia_clinica_id: string;
    historia_clinica?: {
        id: string;
        paciente_id: string;
        paciente?: { id: string; nombre: string } | null;
    } | null;
};

export type CirugiaRow = {
    id: string;
    paciente_id: string;
    consulta_id: string | null;
    veterinario_id: string | null;
    sede_id: string | null;
    programada_at: string;
    estado: string;
    nombre_procedimiento: string;
    tipo_anestesia: string | null;
    observaciones: string | null;
    created_at: string;
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
    consulta: {
        id: string;
        atendido_at: string;
        historia_clinica_id?: string;
        historia_clinica?: {
            id: string;
            paciente_id: string;
        } | null;
    } | null;
    veterinario: { id: string; name: string } | null;
    sede: { id: string; nombre: string; codigo: string } | null;
    creado_por?: AuditUser | null;
    actualizado_por?: AuditUser | null;
};
