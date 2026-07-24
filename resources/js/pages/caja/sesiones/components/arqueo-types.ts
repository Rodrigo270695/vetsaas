export type ArqueoMetodo = {
    codigo: string;
    count: number;
    total: string;
};

export type ArqueoEgreso = {
    id: string;
    monto: string;
    motivo: string;
    motivo_label: string;
    notas: string | null;
    created_at: string | null;
    created_by: string | null;
};

export type ArqueoPayload = {
    moneda: string;
    ventas_count: number;
    ventas_total: string;
    productos_total?: string;
    servicios_total?: string;
    no_efectivo_total?: string;
    anuladas_count: number;
    anuladas_total: string;
    egresos_count?: number;
    egresos_total?: string;
    egresos?: ArqueoEgreso[];
    comprobantes: {
        tickets: { count: number; total: string };
        boletas: { count: number; total: string };
        facturas: { count: number; total: string };
    };
    metodos: ArqueoMetodo[];
    saldo_apertura: string;
    efectivo_ventas: string;
    efectivo_esperado: string;
    efectivo_contado: string | null;
    diferencia: string | null;
};

export function formatArqueoMoney(
    value: string | null | undefined,
    moneda: string,
    locale: string,
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const n = Number(value);
    if (Number.isNaN(n)) {
        return value;
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: moneda === 'USD' ? 'USD' : 'PEN',
    }).format(n);
}

export function arqueoCsrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta instanceof HTMLMetaElement && meta.content) {
        return meta.content;
    }

    return '';
}
