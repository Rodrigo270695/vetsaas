import { format, isToday, isYesterday, parseISO } from 'date-fns';
import type { Locale } from 'date-fns';

export type ChatThreadSep = { kind: 'sep'; key: string; label: string };
export type ChatThreadMsg<T> = { kind: 'msg'; key: string; message: T };
export type ChatThreadItem<T> = ChatThreadSep | ChatThreadMsg<T>;

function dayKey(iso: string | null | undefined): string {
    if (!iso) return '';
    try {
        return format(parseISO(iso), 'yyyy-MM-dd');
    } catch {
        return '';
    }
}

type Labels = {
    today: string;
    yesterday: string;
};

/**
 * Inserta separadores Hoy / Ayer / fecha entre mensajes ordenados cronológicamente.
 */
export function buildChatThreadItems<
    T extends { id: string; created_at: string | null },
>(
    messages: T[],
    labels: Labels,
    dateLocale?: Locale,
): ChatThreadItem<T>[] {
    const items: ChatThreadItem<T>[] = [];
    let prevDay = '';

    for (const m of messages) {
        const key = dayKey(m.created_at);
        if (key && key !== prevDay) {
            prevDay = key;
            let label = key;
            try {
                const d = parseISO(m.created_at!);
                if (isToday(d)) label = labels.today;
                else if (isYesterday(d)) label = labels.yesterday;
                else {
                    label = format(d, 'EEEE d MMM yyyy', {
                        locale: dateLocale,
                    });
                }
            } catch {
                // keep key
            }
            items.push({ kind: 'sep', key: `sep-${key}`, label });
        }
        items.push({ kind: 'msg', key: m.id, message: m });
    }

    return items;
}
