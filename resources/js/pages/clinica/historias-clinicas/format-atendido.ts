import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';
import { enUS, es } from 'date-fns/locale';

/**
 * Formatea un instante ISO en la zona y locale de la app (misma salida en SSR y cliente).
 */
export function formatAtendidoInAppTimezone(
    iso: string,
    localeCode: string,
    timeZone: string,
): string {
    try {
        const d = new TZDate(iso, timeZone);

        if (Number.isNaN(d.getTime())) {
            return '—';
        }

        const loc = localeCode.toLowerCase().startsWith('es') ? es : enUS;

        return format(d, 'd MMM yyyy, HH:mm', { locale: loc });
    } catch {
        return '—';
    }
}

/**
 * Formatea una fecha calendario (Y-m-d o ISO con hora) sin componente horario.
 */
export function formatDateOnlyLabel(value: string, localeCode: string): string {
    try {
        const datePart = value.trim().slice(0, 10);

        if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
            return '—';
        }

        const d = new Date(`${datePart}T12:00:00`);

        if (Number.isNaN(d.getTime())) {
            return '—';
        }

        const loc = localeCode.toLowerCase().startsWith('es') ? es : enUS;

        return format(d, 'd MMM yyyy', { locale: loc });
    } catch {
        return '—';
    }
}
