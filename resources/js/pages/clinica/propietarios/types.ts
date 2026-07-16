export type AuditUser = {
    id: string;
    name: string;
    email: string;
} | null;

export type DistritoChain = {
    id: number;
    name: string;
    provincia_id: number;
    provincia: {
        id: number;
        name: string;
        departamento_id: number;
        departamento: {
            id: number;
            name: string;
        };
    };
} | null;

export type Propietario = {
    id: string;
    tipo_documento: string | null;
    numero_documento: string | null;
    nombres: string;
    apellidos: string | null;
    razon_social: string | null;
    email: string | null;
    telefono: string | null;
    telefono_alt: string | null;
    direccion: string | null;
    distrito_id: number | null;
    distrito: string | null;
    provincia: string | null;
    departamento: string | null;
    distrito_model: DistritoChain | null;
    notas: string | null;
    activo: boolean;
    pacientes_count?: number;
    created_at: string;
    updated_at: string;
    creado_por: AuditUser;
    actualizado_por: AuditUser;
};

export type GeoOption = {
    id: number;
    name: string;
};

export type PropietarioStats = {
    total: number;
    activos: number;
    inactivos: number;
    coincidencias: number;
};

export type PropietarioEstadoFilter = 'todos' | 'activo' | 'inactivo';

export type PropietarioFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: PropietarioEstadoFilter;
};

export type PropietarioOpcion = {
    id: string;
    nombres: string;
    apellidos: string | null;
    razon_social: string | null;
};

export type PacienteEstadoFilter = 'todos' | 'activo' | 'inactivo';

export type PacienteFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: PacienteEstadoFilter;
};

export type PacienteStats = {
    total: number;
    activos: number;
    inactivos: number;
    coincidencias: number;
};

export type Paciente = {
    id: string;
    propietario_id: string;
    nombre: string;
    /** URL pública (`/storage/...`); viene del accessor del modelo. */
    foto_url: string | null;
    especie: string | null;
    raza: string | null;
    sexo: string | null;
    fecha_nacimiento: string | null;
    peso_kg: string | null;
    microchip: string | null;
    color: string | null;
    esterilizado: boolean | null;
    notas: string | null;
    activo: boolean;
    created_at: string;
    updated_at: string;
    propietario?: {
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
        telefono?: string | null;
    };
    creado_por: AuditUser;
    actualizado_por: AuditUser;
};
