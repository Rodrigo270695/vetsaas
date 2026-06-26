export type TicketAnchoMm = '56' | '58' | '80';

export const TICKET_ANCHO_OPTIONS: readonly TicketAnchoMm[] = [
    '56',
    '58',
    '80',
];

const STORAGE_KEY = 'vetsaas.ticket.ancho_mm';

export function normalizeTicketAncho(
    value: string | null | undefined,
    fallback: TicketAnchoMm = '58',
): TicketAnchoMm {
    if (value === '56' || value === '58' || value === '80') {
        return value;
    }

    return fallback;
}

export function readStoredTicketAncho(): TicketAnchoMm | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (raw === '56' || raw === '58' || raw === '80') {
            return raw;
        }
    } catch {
        // localStorage no disponible
    }

    return null;
}

export function writeStoredTicketAncho(ancho: TicketAnchoMm): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, ancho);
    } catch {
        // ignore
    }
}

export function resolveTicketAncho(configDefault: TicketAnchoMm): TicketAnchoMm {
    return readStoredTicketAncho() ?? normalizeTicketAncho(configDefault);
}

export function buildTicketPreviewUrl(
    baseUrl: string,
    ancho: TicketAnchoMm,
    bust: number,
    autoPrint = false,
): string {
    const params = new URLSearchParams({
        ancho,
        _pv: String(bust),
    });

    if (autoPrint) {
        params.set('print', '1');
    }

    const sep = baseUrl.includes('?') ? '&' : '?';

    return `${baseUrl}${sep}${params.toString()}`;
}
