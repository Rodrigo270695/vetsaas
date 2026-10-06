export type TicketAnchoMm = '56' | '57' | '58' | '80';

export const TICKET_ANCHO_OPTIONS: readonly TicketAnchoMm[] = [
    '56',
    '57',
    '58',
    '80',
];

/**
 * Ancho confirmado en esta sesión (impresión o Configuración General).
 * Evita esperar un reload de Inertia para que la siguiente venta abra en el rollo nuevo.
 */
let sessionAncho: TicketAnchoMm | null = null;

export function normalizeTicketAncho(
    value: string | null | undefined,
    fallback: TicketAnchoMm = '58',
): TicketAnchoMm {
    if (value === '72') {
        return '80';
    }

    if (value === '56' || value === '57' || value === '58' || value === '80') {
        return value;
    }

    return fallback;
}

export function resolveTicketAncho(configDefault: TicketAnchoMm): TicketAnchoMm {
    return sessionAncho ?? normalizeTicketAncho(configDefault);
}

/** La guarda de Configuración General pisa lo recordado al imprimir. */
export function adoptTicketAncho(ancho: TicketAnchoMm): void {
    sessionAncho = ancho;
}

export async function persistPrintedTicketAncho(ancho: TicketAnchoMm): Promise<boolean> {
    const previous = sessionAncho;
    sessionAncho = ancho;

    const token =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    try {
        const response = await fetch('/configuracion/ticket-ancho', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ticket_ancho_mm: ancho }),
        });

        if (!response.ok) {
            sessionAncho = previous;

            return false;
        }

        return true;
    } catch {
        sessionAncho = previous;

        return false;
    }
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
