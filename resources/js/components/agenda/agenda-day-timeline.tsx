import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';
import { Clock } from 'lucide-react';
import { useMemo } from 'react';
import type { DragEvent } from 'react';
import { cn } from '@/lib/utils';
import type { AgendaEvent } from './agenda-types';

const DEFAULT_DURATION_MIN = 30;
const LABEL_COL_PX = 48;

function toDateKey(d: Date, timeZone: string): string {
    const tz = new TZDate(d, timeZone);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${tz.getFullYear()}-${pad(tz.getMonth() + 1)}-${pad(tz.getDate())}`;
}

export type AgendaDayTimelineLabels = {
    scheduleAt: (hour: string) => string;
    durationMin: (minutes: number) => string;
    until: string;
    now?: string;
};

type Props = {
    dateKey: string;
    events: readonly AgendaEvent[];
    hourSlots: readonly number[];
    timeZone: string;
    labels: AgendaDayTimelineLabels;
    canCreate: boolean;
    canUpdate: boolean;
    /** px por 60 minutos. 1 h de servicio = exactamente esta altura. */
    pxPerHour?: number;
    compact?: boolean;
    showNowLine?: boolean;
    dragOverKey: string | null;
    onSelectEvent: (event: AgendaEvent) => void;
    onScheduleAt: (dateKey: string, hourLabel: string) => void;
    onDragStart: (e: DragEvent, event: AgendaEvent) => void;
    onDragEnd: () => void;
    onAllowDrop: (e: DragEvent, key: string) => void;
    onClearDragOver: (key: string) => void;
    onDropOnHour: (e: DragEvent, dateKey: string, hour: number) => void;
    isSlotPast: (dateKey: string, hourLabel: string) => boolean;
};

type LaidOutEvent = {
    event: AgendaEvent;
    topPx: number;
    heightPx: number;
    column: number;
    columnCount: number;
    startLabel: string;
    endLabel: string;
    durationMin: number;
};

function barToneClass(event: AgendaEvent): string {
    if (event.tone === 'grooming' || event.accentClass.includes('emerald')) {
        return 'border-emerald-700/30 bg-emerald-500 text-white hover:bg-emerald-600 dark:bg-emerald-600 dark:hover:bg-emerald-500';
    }

    if (event.tone === 'hotel' || event.accentClass.includes('sky')) {
        return 'border-sky-700/30 bg-sky-500 text-white hover:bg-sky-600 dark:bg-sky-600 dark:hover:bg-sky-500';
    }

    if (event.tone === 'cita' || event.accentClass.includes('amber')) {
        return 'border-amber-700/30 bg-amber-500 text-white hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-500';
    }

    if (event.accentClass.includes('rose')) {
        return 'border-rose-700/30 bg-rose-500 text-white hover:bg-rose-600 dark:bg-rose-600 dark:hover:bg-rose-500';
    }

    return 'border-primary/30 bg-primary text-primary-foreground hover:bg-primary/90';
}

function resolveDurationMinutes(event: AgendaEvent, timeZone: string): number {
    if (
        typeof event.duracion_minutos === 'number' &&
        Number.isFinite(event.duracion_minutos) &&
        event.duracion_minutos > 0
    ) {
        return Math.round(event.duracion_minutos);
    }

    if (event.fin_at) {
        const start = new TZDate(event.inicio_at, timeZone).getTime();
        const end = new TZDate(event.fin_at, timeZone).getTime();
        const mins = Math.round((end - start) / 60_000);

        if (mins > 0) {
            if (mins > 12 * 60) {
                return 60;
            }

            return mins;
        }
    }

    return DEFAULT_DURATION_MIN;
}

function partsInZone(iso: string, timeZone: string): { h: number; m: number } {
    const tz = new TZDate(iso, timeZone);

    return { h: tz.getHours(), m: tz.getMinutes() };
}

type Enriched = {
    event: AgendaEvent;
    startMin: number;
    endMin: number;
    durationMin: number;
    startLabel: string;
    endLabel: string;
    topPx: number;
    heightPx: number;
    column: number;
    columnCount: number;
};

/**
 * Alineación exacta con la rejilla:
 * - top = minutos desde firstHour × (pxPerHour/60)
 * - height = duración × (pxPerHour/60)
 * Así 09:00–10:00 (60 min) va de la línea :00 a la siguiente :00.
 */
function layoutEvents(
    events: readonly AgendaEvent[],
    firstHour: number,
    lastHourInclusive: number,
    pxPerHour: number,
    timeZone: string,
): LaidOutEvent[] {
    const dayEndMinutes = (lastHourInclusive + 1) * 60 - firstHour * 60;
    const pxPerMinute = pxPerHour / 60;

    const enriched: Omit<Enriched, 'column' | 'columnCount'>[] = events
        .map((event) => {
            const durationMin = Math.max(
                5,
                resolveDurationMinutes(event, timeZone),
            );
            const { h, m } = partsInZone(event.inicio_at, timeZone);
            let startMin = h * 60 + m - firstHour * 60;
            let endMin = startMin + durationMin;

            startMin = Math.max(0, Math.min(startMin, dayEndMinutes - 5));
            endMin = Math.min(dayEndMinutes, Math.max(startMin + 5, endMin));

            const startTz = new TZDate(event.inicio_at, timeZone);
            const endTz = new Date(startTz.getTime() + durationMin * 60_000);

            // Redondeo al px para evitar subpíxeles que “bajan” la barra
            const topPx = Math.round(startMin * pxPerMinute);
            const heightPx = Math.max(
                16,
                Math.round((endMin - startMin) * pxPerMinute) - 1,
            );

            return {
                event,
                startMin,
                endMin,
                durationMin,
                startLabel: format(startTz, 'HH:mm'),
                endLabel: format(endTz, 'HH:mm'),
                topPx,
                heightPx,
            };
        })
        .sort((a, b) => a.startMin - b.startMin || b.endMin - a.endMin);

    type Active = { endMin: number; column: number };
    const active: Active[] = [];
    const withCols: Enriched[] = [];

    for (const item of enriched) {
        for (let i = active.length - 1; i >= 0; i -= 1) {
            if (active[i].endMin <= item.startMin) {
                active.splice(i, 1);
            }
        }

        const used = new Set(active.map((a) => a.column));
        let column = 0;
        while (used.has(column)) {
            column += 1;
        }

        active.push({ endMin: item.endMin, column });
        withCols.push({ ...item, column, columnCount: 1 });
    }

    const n = withCols.length;
    const parent = Array.from({ length: n }, (_, i) => i);
    const find = (i: number): number => {
        if (parent[i] !== i) {
            parent[i] = find(parent[i]);
        }

        return parent[i];
    };
    const union = (a: number, b: number) => {
        const ra = find(a);
        const rb = find(b);
        if (ra !== rb) {
            parent[rb] = ra;
        }
    };

    for (let i = 0; i < n; i += 1) {
        for (let j = i + 1; j < n; j += 1) {
            if (
                withCols[i].startMin < withCols[j].endMin &&
                withCols[j].startMin < withCols[i].endMin
            ) {
                union(i, j);
            }
        }
    }

    const clusterMaxCol = new Map<number, number>();
    for (let i = 0; i < n; i += 1) {
        const root = find(i);
        clusterMaxCol.set(
            root,
            Math.max(clusterMaxCol.get(root) ?? 0, withCols[i].column + 1),
        );
    }

    return withCols.map((item, i) => ({
        event: item.event,
        topPx: item.topPx,
        heightPx: item.heightPx,
        column: item.column,
        columnCount: clusterMaxCol.get(find(i)) ?? 1,
        startLabel: item.startLabel,
        endLabel: item.endLabel,
        durationMin: item.durationMin,
    }));
}

function hourIsCovered(
    hour: number,
    firstHour: number,
    laidOut: readonly LaidOutEvent[],
    pxPerHour: number,
): boolean {
    const hourStartPx = (hour - firstHour) * pxPerHour;
    const hourEndPx = hourStartPx + pxPerHour;

    return laidOut.some(
        (e) =>
            e.topPx < hourEndPx - 2 && e.topPx + e.heightPx > hourStartPx + 2,
    );
}

export function AgendaDayTimeline({
    dateKey,
    events,
    hourSlots,
    timeZone,
    labels,
    canCreate,
    canUpdate,
    pxPerHour = 64,
    compact = true,
    showNowLine = true,
    dragOverKey,
    onSelectEvent,
    onScheduleAt,
    onDragStart,
    onDragEnd,
    onAllowDrop,
    onClearDragOver,
    onDropOnHour,
    isSlotPast,
}: Props) {
    const firstHour = hourSlots[0] ?? 7;
    const lastHour = hourSlots[hourSlots.length - 1] ?? 20;

    const laidOut = useMemo(
        () => layoutEvents(events, firstHour, lastHour, pxPerHour, timeZone),
        [events, firstHour, lastHour, pxPerHour, timeZone],
    );

    const totalHeight = hourSlots.length * pxPerHour;

    const nowLineTop = useMemo(() => {
        if (!showNowLine || dateKey !== toDateKey(new Date(), timeZone)) {
            return null;
        }

        const now = new TZDate(new Date(), timeZone);
        const mins = now.getHours() * 60 + now.getMinutes() - firstHour * 60;

        if (mins < 0 || mins > hourSlots.length * 60) {
            return null;
        }

        return Math.round((mins / 60) * pxPerHour);
    }, [
        dateKey,
        firstHour,
        hourSlots.length,
        pxPerHour,
        showNowLine,
        timeZone,
    ]);

    return (
        <div
            className="relative select-none"
            style={{ height: totalHeight, minHeight: totalHeight }}
        >
            {/* Columna de eventos (fondo) */}
            <div
                className="absolute inset-y-0 right-0 bg-muted/15"
                style={{ left: LABEL_COL_PX }}
                aria-hidden
            />

            {/* Líneas de hora exactas (misma Y que top de eventos) */}
            {hourSlots.map((hour, index) => {
                const y = index * pxPerHour;
                const hourLabel = `${String(hour).padStart(2, '0')}:00`;
                const slotKey = `hour:${dateKey}:${hour}`;
                const slotPast = isSlotPast(dateKey, hourLabel);
                const covered = hourIsCovered(
                    hour,
                    firstHour,
                    laidOut,
                    pxPerHour,
                );
                const isLast = index === hourSlots.length - 1;

                return (
                    <div key={hour}>
                        {/* Etiqueta centrada en la línea */}
                        <span
                            className={cn(
                                'pointer-events-none absolute z-[1] -translate-y-1/2 text-right text-[0.7rem] font-medium text-muted-foreground tabular-nums',
                                slotPast && 'opacity-40',
                            )}
                            style={{
                                top: y,
                                left: 0,
                                width: LABEL_COL_PX - 8,
                            }}
                        >
                            {hourLabel}
                        </span>

                        {/* Línea de hora a Y exacta */}
                        <div
                            className="pointer-events-none absolute right-0 z-0 border-t border-border/70"
                            style={{ top: y, left: LABEL_COL_PX }}
                            aria-hidden
                        />

                        {/* Media hora */}
                        <div
                            className="pointer-events-none absolute right-0 z-0 border-t border-dashed border-border/35"
                            style={{
                                top: y + pxPerHour / 2,
                                left: LABEL_COL_PX,
                            }}
                            aria-hidden
                        />

                        {isLast ? (
                            <div
                                className="pointer-events-none absolute right-0 z-0 border-t border-border/70"
                                style={{
                                    top: y + pxPerHour,
                                    left: LABEL_COL_PX,
                                }}
                                aria-hidden
                            />
                        ) : null}

                        {/* Zona drop / agendar (altura = 1 h exacta) */}
                        <div
                            onDragOver={(e) => {
                                if (!slotPast) {
                                    onAllowDrop(e, slotKey);
                                }
                            }}
                            onDragLeave={() => onClearDragOver(slotKey)}
                            onDrop={(e) => onDropOnHour(e, dateKey, hour)}
                            className={cn(
                                'absolute right-0 z-0',
                                dragOverKey === slotKey &&
                                    'bg-primary/10 ring-2 ring-primary/30 ring-inset',
                            )}
                            style={{
                                top: y,
                                left: LABEL_COL_PX,
                                height: pxPerHour,
                            }}
                        >
                            {!covered && canCreate && !slotPast ? (
                                <button
                                    type="button"
                                    onClick={() =>
                                        onScheduleAt(dateKey, hourLabel)
                                    }
                                    className={cn(
                                        'group/slot absolute inset-x-1 flex cursor-pointer items-center gap-1.5 rounded-md px-2 text-left text-[0.65rem] text-muted-foreground/45 transition-all',
                                        'top-1/2 -translate-y-1/2 hover:bg-background/70 hover:text-foreground',
                                        compact ? 'h-7' : 'h-8',
                                    )}
                                >
                                    <Clock className="size-3 opacity-50 group-hover/slot:opacity-100" />
                                    <span className="truncate">
                                        {labels.scheduleAt(hourLabel)}
                                    </span>
                                </button>
                            ) : null}
                        </div>
                    </div>
                );
            })}

            {/* Barras: top/height en la misma escala que las líneas */}
            <div
                className="pointer-events-none absolute top-0 right-0 bottom-0 z-10"
                style={{ left: LABEL_COL_PX, height: totalHeight }}
            >
                {laidOut.map(
                    ({
                        event,
                        topPx,
                        heightPx,
                        column,
                        columnCount,
                        startLabel,
                        endLabel,
                        durationMin,
                    }) => {
                        const draggable =
                            Boolean(event.canDrag) && canUpdate;
                        const colGap = columnCount > 1 ? 4 : 3;
                        const widthPct = 100 / columnCount;
                        const leftPct = column * widthPct;
                        const showMeta = heightPx >= pxPerHour * 0.35;
                        const showSub = heightPx >= pxPerHour * 0.55;

                        return (
                            <button
                                key={event.id}
                                type="button"
                                title={`${startLabel} – ${endLabel} (${durationMin} min) · ${event.title}${event.subtitle ? ` · ${event.subtitle}` : ''}`}
                                draggable={draggable}
                                onDragStart={(e) => onDragStart(e, event)}
                                onDragEnd={onDragEnd}
                                onClick={() => onSelectEvent(event)}
                                style={{
                                    top: topPx,
                                    height: heightPx,
                                    left: `calc(${leftPct}% + ${colGap}px)`,
                                    width: `calc(${widthPct}% - ${colGap * 2}px)`,
                                }}
                                className={cn(
                                    'pointer-events-auto absolute box-border flex flex-col overflow-hidden rounded-[4px] border text-left shadow-sm',
                                    'transition-[filter,box-shadow] hover:z-20 hover:brightness-105 hover:shadow-md',
                                    barToneClass(event),
                                    draggable
                                        ? 'cursor-grab active:cursor-grabbing'
                                        : 'cursor-pointer',
                                    compact ? 'px-1.5 py-0.5' : 'px-2 py-1',
                                )}
                            >
                                <p
                                    className={cn(
                                        'leading-tight font-semibold tracking-tight',
                                        compact
                                            ? 'text-[0.7rem]'
                                            : 'text-[0.8rem]',
                                    )}
                                >
                                    {event.title}
                                </p>
                                {showMeta ? (
                                    <p
                                        className={cn(
                                            'mt-0.5 tabular-nums opacity-95',
                                            compact
                                                ? 'text-[0.65rem]'
                                                : 'text-[0.7rem]',
                                        )}
                                    >
                                        {startLabel} – {endLabel}
                                    </p>
                                ) : null}
                                {showSub && event.subtitle ? (
                                    <p
                                        className={cn(
                                            'mt-0.5 truncate opacity-90',
                                            compact
                                                ? 'text-[0.6rem]'
                                                : 'text-[0.65rem]',
                                        )}
                                    >
                                        {labels.durationMin(durationMin)}
                                        {' · '}
                                        {event.subtitle}
                                    </p>
                                ) : null}
                            </button>
                        );
                    },
                )}
            </div>

            {nowLineTop != null ? (
                <div
                    className="pointer-events-none absolute right-0 z-30"
                    style={{ top: nowLineTop, left: LABEL_COL_PX }}
                    aria-hidden
                >
                    <div className="relative flex items-center">
                        <span className="absolute -left-1 size-2.5 -translate-x-1/2 rounded-full bg-rose-500 ring-2 ring-background" />
                        <div className="h-0.5 w-full bg-rose-500/80" />
                        {labels.now ? (
                            <span className="absolute right-0 -top-4 rounded bg-rose-500 px-1.5 py-0.5 text-[0.55rem] font-semibold tracking-wide text-white uppercase">
                                {labels.now}
                            </span>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </div>
    );
}
