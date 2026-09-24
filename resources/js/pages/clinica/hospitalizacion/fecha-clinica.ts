import { TZDate } from '@date-fns/tz';

export const ZONA_PERU = 'America/Lima';

export function zonaPeru(timeZone: string | undefined): string {
    const zona = (timeZone ?? '').trim();

    if (zona === '' || zona.toUpperCase() === 'UTC' || zona === 'Etc/UTC') {
        return ZONA_PERU;
    }

    return zona;
}

export function toDatetimeLocalValue(instant: Date | number, timeZone: string): string {
    const d = new TZDate(instant, timeZone);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function parseIsoToDatetimeLocal(iso: string, timeZone: string): string {
    const d = new TZDate(iso, timeZone);

    if (Number.isNaN(d.getTime())) {
        return toDatetimeLocalValue(Date.now(), timeZone);
    }

    return toDatetimeLocalValue(d, timeZone);
}
