import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '../propietarios/types';

export type VacunaAplicadaFilters = BaseFilters & {
    aplicada_desde: string;
    aplicada_hasta: string;
};

export type AplicacionFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};

export type VacunaAplicadaStats = {
    total: number;
    coincidencias: number;
};

export type PacienteVacunaOpcion = {
    id: string;
    nombre: string;
    propietario?: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
    };
};

export type UsuarioVacunaOpcion = {
    id: string;
    name: string;
};

export type SedeVacunaOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

export type VacunaPrefillCreate = {
    paciente_id: string;
    consulta_id: string | null;
};

export type VacunaAplicadaRow = {
    id: string;
    paciente_id: string;
    consulta_id?: string | null;
    producto_id: string | null;
    nombre_vacuna: string;
    /** Presente tras migración tenant t063; por defecto se trata como vacuna. */
    categoria_registro?: string;
    esquema_antigenos?: string | null;
    fecha_proxima_sugerida?: string | null;
    aplicada_at: string;
    numero_dosis: number | null;
    lote: string | null;
    notas: string | null;
    veterinario_id: string | null;
    sede_id: string | null;
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
    producto: { id: string; nombre: string; sku: string | null } | null;
    veterinario: { id: string; name: string } | null;
    sede: { id: string; nombre: string; codigo: string } | null;
    creado_por?: AuditUser | null;
    actualizado_por?: AuditUser | null;
    consulta?: {
        id: string;
        atendido_at: string;
        cerrada_at: string | null;
    } | null;
};
