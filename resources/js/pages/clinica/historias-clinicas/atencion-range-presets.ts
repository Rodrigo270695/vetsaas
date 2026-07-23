import { TZDate } from '@date-fns/tz';
import {
    endOfMonth,
    endOfQuarter,
    endOfWeek,
    endOfYear,
    startOfDay,
    startOfMonth,
    startOfQuarter,
    startOfWeek,
    startOfYear,
    subDays,
    subMonths,
    subWeeks,
} from 'date-fns';

function nowTz(timeZone: string): TZDate {
    return new TZDate(Date.now(), timeZone);
}

/** Solo el día de hoy (zona de la app). */
export function rangeToday(timeZone: string): { from: Date; to: Date } {
    const n = startOfDay(nowTz(timeZone));

    return { from: n, to: n };
}

/** Solo el día de ayer (zona de la app). */
export function rangeYesterday(timeZone: string): { from: Date; to: Date } {
    const n = startOfDay(subDays(nowTz(timeZone), 1));

    return { from: n, to: n };
}

/** Lunes a domingo de la semana calendario actual (zona de la app). */
export function rangeThisWeek(timeZone: string): { from: Date; to: Date } {
    const n = nowTz(timeZone);

    return {
        from: startOfWeek(n, { weekStartsOn: 1 }),
        to: endOfWeek(n, { weekStartsOn: 1 }),
    };
}

/** Semana calendario anterior (lunes–domingo). */
export function rangeLastWeek(timeZone: string): { from: Date; to: Date } {
    const n = nowTz(timeZone);
    const ref = subWeeks(n, 1);

    return {
        from: startOfWeek(ref, { weekStartsOn: 1 }),
        to: endOfWeek(ref, { weekStartsOn: 1 }),
    };
}

/** Mes calendario actual completo. */
export function rangeThisMonth(timeZone: string): { from: Date; to: Date } {
    const n = nowTz(timeZone);

    return {
        from: startOfMonth(n),
        to: endOfMonth(n),
    };
}

/** Mes calendario anterior completo. */
export function rangeLastMonth(timeZone: string): { from: Date; to: Date } {
    const n = nowTz(timeZone);
    const ref = subMonths(n, 1);

    return {
        from: startOfMonth(ref),
        to: endOfMonth(ref),
    };
}

/** Últimos 7 días inclusive (hoy incluido). */
export function rangeLast7Days(timeZone: string): { from: Date; to: Date } {
    const n = startOfDay(nowTz(timeZone));

    return {
        from: subDays(n, 6),
        to: n,
    };
}

/** Últimos 30 días inclusive (hoy incluido). */
export function rangeLast30Days(timeZone: string): { from: Date; to: Date } {
    const n = startOfDay(nowTz(timeZone));

    return {
        from: subDays(n, 29),
        to: n,
    };
}

/** Trimestre calendario en curso. */
export function rangeThisQuarter(timeZone: string): { from: Date; to: Date } {
    const n = nowTz(timeZone);

    return {
        from: startOfQuarter(n),
        to: endOfQuarter(n),
    };
}

/** Año calendario en curso. */
export function rangeThisYear(timeZone: string): { from: Date; to: Date } {
    const n = nowTz(timeZone);

    return {
        from: startOfYear(n),
        to: endOfYear(n),
    };
}
