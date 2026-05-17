import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '../propietarios/types';

export type HospitalizacionFilters = BaseFilters & {
    ingreso_desde: string;
    ingreso_hasta: string;
    estado: string;
};

export type HospitalizacionFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};

export type HospitalizacionStats = {
    total: number;
    activos: number;
    coincidencias: number;
};

export type PacienteHospitalizacionOpcion = {
    id: string;
    nombre: string;
    propietario?: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
    };
};

export type UsuarioHospitalizacionOpcion = {
    id: string;
    name: string;
};

export type SedeHospitalizacionOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

export type ConsultaHospitalizacionOpcion = {
    id: string;
    atendido_at: string;
    historia_clinica_id: string;
    historia_clinica?: {
        id: string;
        paciente_id: string;
        paciente?: { id: string; nombre: string } | null;
    } | null;
};

export type InternamientoEvolucionRow = {
    id: string;
    internamiento_id: string;
    registrado_at: string;
    veterinario_id: string | null;
    peso_kg: string | null;
    temperatura_c: string | null;
    fc_lpm: number | null;
    fr_rpm: number | null;
    evolucion: string;
    tratamiento: string | null;
    veterinario: { id: string; name: string } | null;
    creado_por?: AuditUser | null;
};

export type InternamientoShow = InternamientoRow & {
    diagnostico_ingreso: string | null;
    notas: string | null;
    evoluciones: readonly InternamientoEvolucionRow[];
    paciente: InternamientoRow['paciente'] & {
        propietario?: {
            id: string;
            nombres: string;
            apellidos: string | null;
            razon_social: string | null;
            telefono?: string | null;
        };
    };
};

export type InternamientoCobroCargo = {
    id: string;
    estado: string;
    total: string;
    moneda: string;
    venta_id: string | null;
};

export type InternamientoCobroInfo = {
    consulta_id: string | null;
    cargo: InternamientoCobroCargo | null;
    cargo_internamiento: InternamientoCobroCargo | null;
    cargo_consulta: InternamientoCobroCargo | null;
    url_cargos_internamiento: string | null;
    url_cargos_consulta: string | null;
    puede_ver_cargos: boolean;
    puede_gestionar_cargos: boolean;
};

export type InternamientoRow = {
    id: string;
    paciente_id: string;
    consulta_id: string | null;
    veterinario_id: string | null;
    sede_id: string | null;
    ingreso_at: string;
    alta_at: string | null;
    estado: string;
    motivo_ingreso: string;
    ubicacion: string | null;
    diagnostico_ingreso: string | null;
    notas: string | null;
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
