import { TZDate } from '@date-fns/tz';
import {
    endOfMonth,
    endOfWeek,
    startOfMonth,
    startOfWeek,
    subMonths,
    subWeeks,
} from 'date-fns';

function nowTz(timeZone: string): TZDate {
    return new TZDate(Date.now(), timeZone);
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

/** Mes calendario anterior completo. */
export function rangeLastMonth(timeZone: string): { from: Date; to: Date } {
    const n = nowTz(timeZone);
    const ref = subMonths(n, 1);

    return {
        from: startOfMonth(ref),
        to: endOfMonth(ref),
    };
}
