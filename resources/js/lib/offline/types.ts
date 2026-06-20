export type OutboxStatus = 'pending' | 'syncing' | 'synced' | 'failed';

export type OutboxType =
    | 'caja.venta.create'
    | 'clinica.cita.create'
    | 'clinica.consulta.create'
    | 'clinica.propietario.create'
    | 'clinica.paciente.create'
    | 'clinica.vacuna.create'
    | 'clinica.cirugia.create'
    | 'clinica.internamiento.create'
    | 'clinica.internamiento.evolucion.create'
    | 'servicios.grooming.create'
    | 'servicios.hotel.create'
    | 'inventario.movimiento.create'
    | 'inventario.compra.create'
    | 'inventario.producto.create'
    | 'inventario.categoria.create'
    | 'inventario.proveedor.create'
    | 'inventario.stock.adjust'
    | 'clinica.receta.create'
    | 'clinica.laboratorio.create'
    | 'configuracion.sede.create';

export type OutboxItem = {
    uuid: string;
    type: OutboxType;
    payload: Record<string, unknown>;
    status: OutboxStatus;
    error?: string;
    created_at: string;
    synced_at?: string;
    local_label?: string;
    venta_id?: string;
    numero?: string;
    resource_id?: string;
    resource_label?: string;
};

export type CajaBootstrapCache = {
    cached_at: string;
    puede_vender: boolean;
    mi_sesion: {
        id: string;
        sede_id: string;
        sede_nombre: string;
        moneda: string;
    } | null;
    clinica: {
        moneda: string;
        igv_porcentaje: string;
        precio_incluye_igv: boolean;
        emite_comprobantes_sunat: boolean;
        plan_permite_boletas: boolean;
        plan_permite_facturas: boolean;
    };
    propietarios_opciones: Array<{ id: string; label: string; doc: string | null }>;
    productos: Array<{
        id: string;
        nombre: string;
        sku: string | null;
        codigo_barras: string | null;
        precio_venta: string | null;
        unidad: string;
        stock_sede: string;
    }>;
    pacientes: Array<{ id: string; nombre: string; propietario_id: string }>;
};

export type ClinicaBootstrapCache = {
    cached_at: string;
    propietarios: Array<{
        id: string;
        nombres: string;
        apellidos: string | null;
        razon_social: string | null;
        numero_documento: string | null;
        telefono: string | null;
        email: string | null;
        label: string;
    }>;
    pacientes: Array<{
        id: string;
        nombre: string;
        propietario_id: string;
        especie: string | null;
        raza: string | null;
        propietario?: {
            id: string;
            nombres: string;
            apellidos: string | null;
            razon_social: string | null;
        } | null;
    }>;
    citas: Array<Record<string, unknown>>;
    sedes: Array<{ id: string; nombre: string; codigo: string }>;
    veterinarios: Array<{ id: string; name: string }>;
    catalogo_especie_raza: Record<string, unknown>;
    productos_vacuna: Array<{
        id: string;
        nombre: string;
        sku: string | null;
        unidad: string;
    }>;
    productos_medicamento: Array<{
        id: string;
        nombre: string;
        sku: string | null;
        unidad: string;
    }>;
    consultas_abiertas: Array<Record<string, unknown>>;
};

export type InventarioBootstrapCache = {
    cached_at: string;
    productos: Array<{
        id: string;
        nombre: string;
        sku: string | null;
        codigo_barras: string | null;
        unidad: string;
        precio_venta: string | null;
        precio_compra: string | null;
        medicamento: boolean;
        categoria_id: string | null;
        activo: boolean;
    }>;
    categorias: Array<{
        id: string;
        nombre: string;
        slug: string | null;
        parent_id: string | null;
        activo: boolean;
    }>;
    proveedores: Array<{
        id: string;
        ruc: string;
        razon_social: string;
        activo: boolean;
    }>;
    sedes: Array<{ id: string; nombre: string; codigo: string }>;
    unidades: Array<Record<string, unknown>>;
};

export type ServiciosBootstrapCache = {
    cached_at: string;
    pacientes: Array<{
        id: string;
        nombre: string;
        propietario_id: string;
        propietario?: {
            id: string;
            nombres: string;
            apellidos: string | null;
            razon_social: string | null;
        } | null;
    }>;
    usuarios: Array<{ id: string; name: string }>;
    sedes: Array<{ id: string; nombre: string; codigo: string }>;
    grooming_catalogo_personalizado: boolean;
    grooming_servicios: Array<Record<string, unknown>>;
    grooming_servicio_grupos: Array<Record<string, unknown>>;
    grooming_servicio_duraciones: Record<string, number>;
    hotel_catalogo_personalizado: boolean;
    hotel_tipos: Array<Record<string, unknown>>;
    hotel_tipo_grupos: Array<Record<string, unknown>>;
};

export type ConfiguracionBootstrapCache = {
    cached_at: string;
    sedes: Array<{
        id: string;
        nombre: string;
        codigo: string;
        activa: boolean;
        telefono: string | null;
        email: string | null;
        direccion: string | null;
        distrito_id: number | null;
        distrito: string | null;
        provincia: string | null;
        departamento: string | null;
    }>;
};

export type SyncPushResult = {
    uuid: string;
    status: 'synced' | 'failed';
    error?: string;
    type?: string;
    venta_id?: string;
    numero?: string;
    resource_id?: string;
    resource_label?: string;
};
