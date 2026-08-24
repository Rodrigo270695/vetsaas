import type { BaseFilters } from '@/hooks/use-data-table-page';
import type { AuditUser } from '@/pages/clinica/propietarios/types';

export type GroomingFilters = BaseFilters & {
    grooming_desde: string;
    grooming_hasta: string;
    cobro?: 'todos' | 'por_cobrar' | 'cobrado' | 'sin_precuenta';
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

export type GroomingServicioRow = {
    id: string;
    nombre: string;
    categoria: string | null;
    precio_lista: string;
    moneda: string;
    duracion_minutos: number;
    activo: boolean;
    orden: number;
};

export type GroomingTurnoFoto = {
    id: string;
    grooming_turno_id: string;
    tipo: 'proceso' | 'final' | string;
    path: string;
    caption: string | null;
    url: string | null;
    enviado_whatsapp_at: string | null;
    created_at: string;
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
    grooming_servicio_id?: string | null;
    servicio_label?: string;
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
            telefono?: string | null;
        } | null;
    } | null;
    responsable: { id: string; name: string } | null;
    sede: { id: string; nombre: string; codigo: string } | null;
    venta_id: string | null;
    adelanto_venta_id?: string | null;
    adelanto_monto?: string | null;
    adelanto_at?: string | null;
    /** URL POS precargada (solo si pre-cuenta confirmada y permisos). */
    url_cobrar?: string | null;
    puede_adelanto?: boolean;
    /** sin_precuenta | precuenta_borrador | precuenta_lista | cobrado */
    estado_cobro?: string;
    fotos?: GroomingTurnoFoto[];
    cargo?: {
        id: string;
        estado: string;
        venta_id: string | null;
    } | null;
    creado_por?: AuditUser | null;
    actualizado_por?: AuditUser | null;
};
