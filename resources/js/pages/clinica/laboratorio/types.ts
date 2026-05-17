import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '../propietarios/types';

export type PedidoLaboratorioFilters = BaseFilters & {
    pedido_desde: string;
    pedido_hasta: string;
    estado: string;
};

export type PedidoLaboratorioFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};

export type PedidoLaboratorioStats = {
    total: number;
    coincidencias: number;
};

export type PacienteLaboratorioOpcion = {
    id: string;
    nombre: string;
    propietario?: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
    };
};

export type UsuarioLaboratorioOpcion = {
    id: string;
    name: string;
};

export type SedeLaboratorioOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

export type ConsultaLaboratorioOpcion = {
    id: string;
    atendido_at: string;
    historia_clinica_id: string;
    historia_clinica?: {
        id: string;
        paciente_id: string;
        paciente?: { id: string; nombre: string } | null;
    } | null;
};

export type PedidoLaboratorioLineaRow = {
    id: string;
    nombre_examen: string;
    indicaciones: string | null;
    resultado: string | null;
    resultado_at: string | null;
    orden: number;
};

export type PedidoLaboratorioRow = {
    id: string;
    paciente_id: string;
    consulta_id: string | null;
    veterinario_id: string | null;
    sede_id: string | null;
    solicitado_at: string;
    estado: string;
    laboratorio_destino: string | null;
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
    lineas?: PedidoLaboratorioLineaRow[];
    creado_por?: AuditUser | null;
    actualizado_por?: AuditUser | null;
};
