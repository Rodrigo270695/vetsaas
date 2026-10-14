import type { BaseFilters } from '@/hooks/use-data-table-page';

export type CotizacionEstado = 'emitida' | 'aceptada' | 'rechazada';

export type CotizacionEstadoFilter = 'todas' | CotizacionEstado | 'vencida';

export type CotizacionRow = {
    id: string;
    numero: string;
    fecha: string;
    vigencia_hasta: string;
    estado: CotizacionEstado;
    vencida: boolean;
    cliente_nombre: string;
    cliente_documento: string | null;
    cliente_telefono: string | null;
    cliente_email: string | null;
    moneda: string;
    total: string;
    lineas_count: number;
    sede_nombre: string | null;
    creado_por: string | null;
    enviada_whatsapp_at: string | null;
    enviada_email_at: string | null;
};

export type CotizacionFilters = BaseFilters & {
    estado: CotizacionEstadoFilter;
};

export type CotizacionStats = {
    total: number;
    vigentes: number;
    aceptadas: number;
    coincidencias: number;
};

export type TipoLinea = 'producto' | 'servicio';

export type CotizacionLineaInput = {
    key: string;
    tipo_linea: TipoLinea;
    producto_id: string | null;
    descripcion: string;
    unidad: string | null;
    cantidad: number;
    precio_unitario: string;
    descuento_pct: number;
};

export type CotizacionFormData = {
    id: string;
    numero: string;
    sede_id: string | null;
    propietario_id: string | null;
    propietario: { id: string; label: string; doc: string | null } | null;
    paciente_id: string | null;
    cliente_nombre: string;
    cliente_documento: string | null;
    cliente_telefono: string | null;
    cliente_email: string | null;
    fecha: string;
    vigencia_hasta: string;
    notas: string | null;
    igv_porcentaje: string;
    precio_incluye_igv: boolean;
    lineas: Omit<CotizacionLineaInput, 'key'>[];
};

export type ClinicaCotizacionConfig = {
    moneda: string;
    igv_porcentaje: string;
    precio_incluye_igv: boolean;
};

export type ProductoHit = {
    id: string;
    nombre: string;
    sku: string | null;
    unidad: string | null;
    precio_venta: string | null;
};

export type ServicioHit = {
    nombre: string;
    precio_lista: string;
    origen?: string;
    categoria?: string | null;
};

export type PropietarioDetalle = {
    id: string;
    nombre: string;
    documento: string | null;
    telefono: string | null;
    email: string | null;
    pacientes: { id: string; nombre: string }[];
};

export function monedaSimbolo(moneda: string): string {
    if (moneda.toUpperCase() === 'USD') {
        return 'US$';
    }
    if (moneda.toUpperCase() === 'EUR') {
        return '€';
    }

    return 'S/';
}

export function formatMoney(value: number | string, moneda: string): string {
    const n = Number(value);

    return `${monedaSimbolo(moneda)} ${(Number.isFinite(n) ? n : 0).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    const [y, m, d] = iso.slice(0, 10).split('-');

    return `${d}/${m}/${y}`;
}
