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
 * Fecha calendario de hoy (Y-m-d) en la zona horaria de la app (p. ej. America/Lima).
 */
export function todayCalendarDateInAppTimezone(timeZone: string): string {
    const d = new TZDate(Date.now(), timeZone);

    return format(d, 'yyyy-MM-dd');
}

/**
 * Clave de día (Y-m-d) de un instante ISO en la zona de la app. Útil para agrupar
 * eventos por día calendario sin desalinearse por UTC vs. hora local.
 */
export function dateKeyInAppTimezone(iso: string, timeZone: string): string {
    try {
        const d = new TZDate(iso, timeZone);

        if (Number.isNaN(d.getTime())) {
            return '';
        }

        return format(d, 'yyyy-MM-dd');
    } catch {
        return '';
    }
}

/**
 * Solo la hora (HH:mm) de un instante ISO en la zona de la app.
 */
export function formatTimeOnlyInAppTimezone(iso: string, timeZone: string): string {
    try {
        const d = new TZDate(iso, timeZone);

        if (Number.isNaN(d.getTime())) {
            return '—';
        }

        return format(d, 'HH:mm');
    } catch {
        return '—';
    }
}

/**
 * Etiqueta de fecha completa ("viernes 14 de agosto de 2026" / "Friday, August 14, 2026")
 * para usar como encabezado de grupo en un timeline. No resuelve "Hoy"/"Ayer": eso se
 * decide en el componente para poder traducirlo con las claves i18n correctas.
 */
export function formatFullDateLabelInAppTimezone(
    iso: string,
    localeCode: string,
    timeZone: string,
): string {
    try {
        const d = new TZDate(iso, timeZone);

        if (Number.isNaN(d.getTime())) {
            return '—';
        }

        const isEs = localeCode.toLowerCase().startsWith('es');
        const loc = isEs ? es : enUS;
        const pattern = isEs ? "EEEE d 'de' MMMM 'de' yyyy" : 'EEEE, MMMM d, yyyy';

        return format(d, pattern, { locale: loc });
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
