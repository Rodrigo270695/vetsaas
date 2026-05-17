import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '../propietarios/types';

export type PacienteHistoriaOpcion = {
    id: string;
    nombre: string;
    propietario: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
    };
};

export type ConsultaHistoriaFilters = BaseFilters & {
    /** Rango aplicado a `atendido_at` (inclusive, día calendario en zona de la app). */
    atendido_desde: string;
    atendido_hasta: string;
};

/** Metadatos solo para UI (no van en la query string del listado). */
export type AtencionFiltroUi = {
    default_desde: string;
    default_hasta: string;
    fuera_del_mes_actual: boolean;
};

export type ConsultaHistoriaStats = {
    total: number;
    coincidencias: number;
};

export type ConsultaPlanTratamientoLinea = {
    id: string;
    producto_id: string | null;
    cantidad: string | null;
    medicamento: string;
    dosis: string | null;
    unidad: string | null;
    via: string | null;
    frecuencia: string | null;
    lote: string | null;
    notas: string | null;
    /** Fecha en que se registró la línea en el plan (`yyyy-MM-dd`). */
    anadido_en: string | null;
    sort_order: number;
    producto?: { id: string; nombre: string; unidad: string | null; sku: string | null } | null;
};

export type ConsultaPlanTratamientoResumen = {
    id: string;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    indicaciones: string | null;
    estado: string;
    lineas: ConsultaPlanTratamientoLinea[];
};

export type ConsultaPlanTratamientoSeguimiento = {
    id: string;
    registrado_at: string;
    nota: string;
    created_at: string;
    creado_por: { id: string; name: string } | null;
};

export type ConsultaPlanTratamientoDetalle = ConsultaPlanTratamientoResumen & {
    seguimientos: ConsultaPlanTratamientoSeguimiento[];
};

export type ConsultaHistoriaRow = {
    id: string;
    historia_clinica_id: string;
    atendido_at: string;
    motivo: string | null;
    subjetivo: string | null;
    objetivo: string | null;
    analisis: string | null;
    plan: string | null;
    peso_kg: string | null;
    temperatura_c: string | null;
    fc_lpm: number | null;
    fr_rpm: number | null;
    cerrada_at: string | null;
    created_at: string;
    updated_at: string;
    /** Plan de medicación vinculado (si existe); usado en el listado para acceso al plan y a «Plan y seguimiento». */
    plan_tratamiento: ConsultaPlanTratamientoResumen | null;
    /** Pre-cuenta / cargos de la consulta (si existe fila en `consulta_cargos`). */
    cargo?: { id: string; estado: string; total: string } | null;
    historia_clinica: {
        id: string;
        paciente_id: string;
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
    };
    veterinario: { id: string; name: string } | null;
    creado_por: AuditUser;
    actualizado_por: AuditUser;
};

/** Consulta tal como llega a la página de plan (incluye seguimientos en el plan). */
export type ConsultaHistoriaPlanPageRow = Omit<ConsultaHistoriaRow, 'plan_tratamiento'> & {
    plan_tratamiento: ConsultaPlanTratamientoDetalle | null;
};
