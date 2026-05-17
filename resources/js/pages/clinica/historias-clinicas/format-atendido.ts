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
