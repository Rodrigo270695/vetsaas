export function formatMoney(value: number | null | undefined, moneda: string, locale: string): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: moneda,
            minimumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${moneda} ${value.toFixed(2)}`;
    }
}

export function formatNumber(value: number | null | undefined, locale: string, digits = 2): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    return value.toLocaleString(locale, {
        minimumFractionDigits: 0,
        maximumFractionDigits: digits,
    });
}

export function formatPct(value: number | null | undefined, locale: string): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    return `${value.toLocaleString(locale, { maximumFractionDigits: 1 })}%`;
}

/** Rango o fecha única (Y-m-d) para columnas de reportes de ventas. */
export function formatFechaRango(
    primera: string | null | undefined,
    ultima: string | null | undefined,
    locale: string,
): string {
    const fmt = (iso: string): string => {
        const d = new Date(`${iso}T12:00:00`);
        if (Number.isNaN(d.getTime())) {
            return iso;
        }

        try {
            return new Intl.DateTimeFormat(locale, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(d);
        } catch {
            return iso;
        }
    };

    const a = (primera ?? '').trim();
    const b = (ultima ?? '').trim();

    if (a === '' && b === '') {
        return '—';
    }
    if (a === '' || b === '' || a === b) {
        return fmt(a || b);
    }

    return `${fmt(a)} – ${fmt(b)}`;
}
