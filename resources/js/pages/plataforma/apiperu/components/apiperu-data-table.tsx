import { useMemo } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    rows: Array<Record<string, unknown>>;
    /** Clave del endpoint para etiquetas amigables (ej. ruc_representantes). */
    endpointKey?: string;
    className?: string;
};

const COLUMN_LABELS: Record<string, Record<string, string>> = {
    ruc_representantes: {
        tipo_de_documento: 'Documento',
        numero_de_documento: 'Número',
        nombre: 'Nombre',
        cargo: 'Cargo',
        fecha_desde: 'Desde',
        fecha_hasta: 'Hasta',
    },
    ruc_establecimientos_anexos: {
        codigo: 'Código',
        tipo_de_establecimiento: 'Tipo',
        actividad_economica: 'Actividad',
        direccion: 'Dirección',
        direccion_completa: 'Dirección completa',
        departamento: 'Departamento',
        provincia: 'Provincia',
        distrito: 'Distrito',
        ubigeo_sunat: 'Ubigeo',
    },
    ruc_deuda_coactiva: {
        monto: 'Monto',
        moneda: 'Moneda',
        periodo: 'Periodo',
        entidad: 'Entidad',
        estado: 'Estado',
        descripcion: 'Descripción',
    },
    ruc_trabajadores: {
        periodo: 'Periodo',
        total: 'Total',
        cantidad: 'Cantidad',
        trabajadores: 'Trabajadores',
    },
    comisiones_afp: {
        afp: 'AFP',
        comision: 'Comisión',
        prima: 'Prima',
        aporte: 'Aporte',
        flujo: 'Flujo',
        saldo: 'Saldo',
    },
};

const PREFERRED_ORDER: Record<string, string[]> = {
    ruc_representantes: [
        'tipo_de_documento',
        'numero_de_documento',
        'nombre',
        'cargo',
        'fecha_desde',
        'fecha_hasta',
    ],
    ruc_establecimientos_anexos: [
        'codigo',
        'tipo_de_establecimiento',
        'actividad_economica',
        'direccion',
        'departamento',
        'provincia',
        'distrito',
        'ubigeo_sunat',
    ],
};

function humanizeKey(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function cellText(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    if (Array.isArray(value)) {
        return value.map((v) => cellText(v)).join(', ');
    }

    try {
        return JSON.stringify(value);
    } catch {
        return String(value);
    }
}

function collectColumns(rows: Array<Record<string, unknown>>, endpointKey?: string): string[] {
    const seen = new Set<string>();
    for (const row of rows) {
        for (const key of Object.keys(row)) {
            // Evitar columnas demasiado pesadas en la tabla principal
            if (key === 'ubigeo' && Array.isArray(row[key])) {
                continue;
            }
            if (key === 'direccion_completa' && 'direccion' in row) {
                continue;
            }
            seen.add(key);
        }
    }

    const preferred = endpointKey ? PREFERRED_ORDER[endpointKey] : undefined;
    if (preferred) {
        const ordered = preferred.filter((k) => seen.has(k));
        for (const k of seen) {
            if (!ordered.includes(k)) {
                ordered.push(k);
            }
        }

        return ordered;
    }

    return Array.from(seen);
}

/**
 * Tabla estilo SUNAT para arrays de objetos (representantes, anexos, etc.).
 */
export function ApiPeruDataTable({ rows, endpointKey, className }: Props) {
    const columns = useMemo(() => collectColumns(rows, endpointKey), [rows, endpointKey]);
    const labels = endpointKey ? COLUMN_LABELS[endpointKey] : undefined;

    if (rows.length === 0 || columns.length === 0) {
        return (
            <p className="rounded-lg border border-border/60 bg-muted/30 px-3 py-4 text-sm text-muted-foreground">
                Sin registros.
            </p>
        );
    }

    return (
        <div className={cn('overflow-x-auto rounded-lg border border-border/60', className)}>
            <table className="w-full min-w-[28rem] border-collapse text-left text-sm">
                <thead>
                    <tr className="border-b border-border/60 bg-muted/50">
                        {columns.map((col) => (
                            <th
                                key={col}
                                className="whitespace-nowrap px-3 py-2.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {labels?.[col] ?? humanizeKey(col)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr
                            key={index}
                            className="border-b border-border/40 last:border-0 odd:bg-background even:bg-muted/20"
                        >
                            {columns.map((col) => (
                                <td
                                    key={col}
                                    className={cn(
                                        'px-3 py-2.5 align-top text-foreground',
                                        col === 'nombre' || col === 'cargo' || col.includes('direccion')
                                            ? 'min-w-[10rem] font-medium'
                                            : 'whitespace-nowrap',
                                    )}
                                >
                                    {cellText(row[col])}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
            <p className="border-t border-border/50 bg-muted/30 px-3 py-1.5 text-[11px] text-muted-foreground">
                {rows.length} {rows.length === 1 ? 'registro' : 'registros'}
            </p>
        </div>
    );
}

export function isObjectArray(value: unknown): value is Array<Record<string, unknown>> {
    return (
        Array.isArray(value) &&
        value.length > 0 &&
        value.every((item) => typeof item === 'object' && item !== null && !Array.isArray(item))
    );
}
