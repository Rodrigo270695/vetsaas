import type { OutboxItem, OutboxType } from './types';

const TYPE_KEYS: OutboxType[] = [
    'caja.venta.create',
    'clinica.cita.create',
    'clinica.consulta.create',
    'clinica.propietario.create',
    'clinica.paciente.create',
    'clinica.vacuna.create',
    'clinica.cirugia.create',
    'clinica.internamiento.create',
    'clinica.internamiento.evolucion.create',
    'servicios.grooming.create',
    'servicios.hotel.create',
    'inventario.movimiento.create',
    'inventario.compra.create',
    'inventario.producto.create',
    'inventario.categoria.create',
    'inventario.proveedor.create',
    'inventario.stock.adjust',
    'clinica.receta.create',
    'clinica.laboratorio.create',
    'configuracion.sede.create',
];

export function outboxTypeOptions(): OutboxType[] {
    return TYPE_KEYS;
}

export function outboxTypeI18nKey(type: OutboxType): string {
    return `types.${type.replace(/\./g, '_')}`;
}

function readString(payload: Record<string, unknown>, key: string): string | null {
    const value = payload[key];

    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    return null;
}

/**
 * Resumen legible del payload para la tabla del centro de sync.
 */
export function outboxPayloadSummary(item: OutboxItem): string {
    const p = item.payload;

    switch (item.type) {
        case 'caja.venta.create':
            return readString(p, 'notas') ?? readString(p, 'metodo_pago') ?? '—';
        case 'clinica.cita.create':
            return [readString(p, 'fecha'), readString(p, 'motivo')].filter(Boolean).join(' · ') || '—';
        case 'clinica.consulta.create':
            return readString(p, 'motivo_consulta') ?? readString(p, 'anamnesis') ?? '—';
        case 'clinica.propietario.create':
            return (
                readString(p, 'razon_social') ??
                [readString(p, 'nombres'), readString(p, 'apellidos')].filter(Boolean).join(' ') ??
                '—'
            );
        case 'clinica.paciente.create':
            return readString(p, 'nombre') ?? '—';
        case 'clinica.vacuna.create':
            return readString(p, 'vacuna_nombre') ?? readString(p, 'lote') ?? '—';
        case 'clinica.cirugia.create':
            return readString(p, 'procedimiento') ?? readString(p, 'motivo') ?? '—';
        case 'clinica.internamiento.create':
            return readString(p, 'motivo') ?? readString(p, 'diagnostico') ?? '—';
        case 'clinica.internamiento.evolucion.create':
            return readString(p, 'nota') ?? readString(p, 'observaciones') ?? '—';
        case 'servicios.grooming.create':
            return readString(p, 'servicio_nombre') ?? readString(p, 'fecha') ?? '—';
        case 'servicios.hotel.create':
            return readString(p, 'tipo_nombre') ?? readString(p, 'fecha_ingreso') ?? '—';
        case 'inventario.movimiento.create':
            return readString(p, 'motivo') ?? readString(p, 'tipo') ?? '—';
        case 'inventario.compra.create':
            return readString(p, 'numero_documento') ?? readString(p, 'proveedor_nombre') ?? '—';
        case 'inventario.producto.create':
            return readString(p, 'nombre') ?? readString(p, 'sku') ?? '—';
        case 'inventario.categoria.create':
            return readString(p, 'nombre') ?? '—';
        case 'inventario.proveedor.create':
            return readString(p, 'razon_social') ?? readString(p, 'ruc') ?? '—';
        case 'inventario.stock.adjust':
            return readString(p, 'motivo') ?? '—';
        case 'clinica.receta.create':
            return readString(p, 'indicaciones') ?? '—';
        case 'clinica.laboratorio.create':
            return readString(p, 'notas') ?? readString(p, 'prioridad') ?? '—';
        case 'configuracion.sede.create':
            return readString(p, 'nombre') ?? '—';
        default:
            return '—';
    }
}

export function formatOutboxPayload(item: OutboxItem): string {
    try {
        return JSON.stringify(item.payload, null, 2);
    } catch {
        return '{}';
    }
}
