export type OutboxStatus = 'pending' | 'syncing' | 'synced' | 'failed';

export type OutboxItem = {
    uuid: string;
    type: 'caja.venta.create';
    payload: Record<string, unknown>;
    status: OutboxStatus;
    error?: string;
    created_at: string;
    synced_at?: string;
    local_label?: string;
    venta_id?: string;
    numero?: string;
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

export type SyncPushResult = {
    uuid: string;
    status: 'synced' | 'failed';
    error?: string;
    venta_id?: string;
    numero?: string;
};
