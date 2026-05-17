import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '../propietarios/types';

export type RecetaFilters = BaseFilters & {
    receta_desde: string;
    receta_hasta: string;
    estado: string;
};

export type RecetaFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};

export type RecetaStats = {
    total: number;
    coincidencias: number;
};

export type PacienteRecetaOpcion = {
    id: string;
    nombre: string;
    propietario?: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
    };
};

export type UsuarioRecetaOpcion = {
    id: string;
    name: string;
};

export type SedeRecetaOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

export type ConsultaRecetaOpcion = {
    id: string;
    atendido_at: string;
    historia_clinica_id: string;
    historia_clinica?: {
        id: string;
        paciente_id: string;
        paciente?: { id: string; nombre: string } | null;
    } | null;
};

export type RecetaLineaRow = {
    id: string;
    producto_id: string | null;
    nombre_medicamento: string;
    posologia: string | null;
    duracion_dias: number | null;
    instrucciones: string | null;
    orden: number;
    producto?: { id: string; nombre: string; sku: string | null; unidad: string | null } | null;
};

export type RecetaRow = {
    id: string;
    paciente_id: string;
    consulta_id: string | null;
    veterinario_id: string | null;
    sede_id: string | null;
    emitida_at: string;
    estado: string;
    observaciones: string | null;
    created_at: string;
    lineas_count: number;
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
    lineas?: RecetaLineaRow[];
    creado_por?: AuditUser | null;
    actualizado_por?: AuditUser | null;
};
