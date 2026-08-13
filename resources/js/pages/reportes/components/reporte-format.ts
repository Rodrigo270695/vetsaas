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
