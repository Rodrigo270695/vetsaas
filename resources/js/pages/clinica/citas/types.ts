import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '../propietarios/types';

export type VistaCita = 'calendario' | 'lista';

export type CitaFilters = BaseFilters & {
    cita_desde: string;
    cita_hasta: string;
    vista: VistaCita;
    semana_desde: string | null;
};

export type CitaFiltroUi = {
    default_desde: string;
    default_hasta: string;
    default_semana_desde: string;
    fuera_del_mes_actual: boolean;
};

export type CitaStats = {
    total: number;
    coincidencias: number;
};

export type PacienteCitaOpcion = {
    id: string;
    nombre: string;
    propietario?: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
    };
};

export type UsuarioCitaOpcion = {
    id: string;
    name: string;
};

export type SedeCitaOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

export type CitaRow = {
    id: string;
    paciente_id: string;
    veterinario_id: string | null;
    sede_id: string | null;
    inicio_at: string;
    duracion_minutos: number;
    estado: string;
    motivo: string | null;
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
    veterinario: { id: string; name: string } | null;
    sede: { id: string; nombre: string; codigo: string } | null;
    creado_por?: AuditUser | null;
    actualizado_por?: AuditUser | null;
};
