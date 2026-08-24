import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';
import { Clock } from 'lucide-react';
import { useMemo } from 'react';
import type { DragEvent } from 'react';
import { cn } from '@/lib/utils';
import type { AgendaEvent } from './agenda-types';

const DEFAULT_DURATION_MIN = 30;
const MIN_DURATION_MIN = 15;

function toDateKey(d: Date, timeZone: string): string {
    const tz = new TZDate(d, timeZone);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${tz.getFullYear()}-${pad(tz.getMonth() + 1)}-${pad(tz.getDate())}`;
}

export type AgendaDayTimelineLabels = {
    scheduleAt: (hour: string) => string;
    durationMin: (minutes: number) => string;
    until: string;
};

type Props = {
    dateKey: string;
    events: readonly AgendaEvent[];
    hourSlots: readonly number[];
    timeZone: string;
    labels: AgendaDayTimelineLabels;
    canCreate: boolean;
    canUpdate: boolean;
    /** Altura en px de cada hora. */
    pxPerHour?: number;
    compact?: boolean;
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

function resolveDurationMinutes(event: AgendaEvent, timeZone: string): number {
    if (
        typeof event.duracion_minutos === 'number' &&
        Number.isFinite(event.duracion_minutos) &&
        event.duracion_minutos > 0
    ) {
        return Math.max(MIN_DURATION_MIN, Math.round(event.duracion_minutos));
    }

    if (event.fin_at) {
        const start = new TZDate(event.inicio_at, timeZone).getTime();
        const end = new TZDate(event.fin_at, timeZone).getTime();
        const mins = Math.round((end - start) / 60_000);

        if (mins > 0) {
            // Hotel multi-día: en vista diaria mostramos un bloque corto de check-in.
            if (mins > 12 * 60) {
                return 60;
            }

            return Math.max(MIN_DURATION_MIN, mins);
        }
    }

    return DEFAULT_DURATION_MIN;
}

function minutesFromGridStart(
    iso: string,
    firstHour: number,
    timeZone: string,
): number {
    const tz = new TZDate(iso, timeZone);

    return tz.getHours() * 60 + tz.getMinutes() - firstHour * 60;
}

/**
 * Asigna columnas a eventos solapados (estilo calendario Google/Apple).
 */
function layoutEvents(
    events: readonly AgendaEvent[],
    firstHour: number,
    lastHourInclusive: number,
    pxPerHour: number,
    timeZone: string,
): LaidOutEvent[] {
    const dayEndMinutes = (lastHourInclusive + 1) * 60 - firstHour * 60;

    const enriched = events
        .map((event) => {
            const durationMin = resolveDurationMinutes(event, timeZone);
            let startMin = minutesFromGridStart(
                event.inicio_at,
                firstHour,
                timeZone,
            );
            let endMin = startMin + durationMin;

            startMin = Math.max(0, startMin);
            endMin = Math.min(dayEndMinutes, Math.max(startMin + MIN_DURATION_MIN, endMin));

            const startTz = new TZDate(event.inicio_at, timeZone);
            const endTz = new TZDate(
                startTz.getTime() + durationMin * 60_000,
                timeZone,
            );

            return {
                event,
                startMin,
                endMin,
                durationMin,
                startLabel: format(startTz, 'HH:mm'),
                endLabel: format(endTz, 'HH:mm'),
                topPx: (startMin / 60) * pxPerHour,
                heightPx: Math.max(
                    ((endMin - startMin) / 60) * pxPerHour,
                    pxPerHour * 0.35,
                ),
            };
        })
        .sort((a, b) => a.startMin - b.startMin || b.endMin - a.endMin);

    type Active = { endMin: number; column: number };
    const active: Active[] = [];
    const withCols: Array<(typeof enriched)[number] & { column: number }> = [];

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
        withCols.push({ ...item, column });
    }

    // columnCount por cluster de solapes
    const result: LaidOutEvent[] = withCols.map((item) => {
        const overlapping = withCols.filter(
            (other) =>
                other.startMin < item.endMin && other.endMin > item.startMin,
        );
        const columnCount = Math.max(
            1,
            ...overlapping.map((o) => o.column + 1),
        );

        return {
            event: item.event,
            topPx: item.topPx,
            heightPx: item.heightPx,
            column: item.column,
            columnCount,
            startLabel: item.startLabel,
            endLabel: item.endLabel,
            durationMin: item.durationMin,
        };
    });

    return result;
}

export function AgendaDayTimeline({
    dateKey,
    events,
    hourSlots,
    timeZone,
    labels,
    canCreate,
    canUpdate,
    pxPerHour = 48,
    compact = true,
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
        if (dateKey !== toDateKey(new Date(), timeZone)) {
            return null;
        }

        const now = new TZDate(new Date(), timeZone);
        const mins = now.getHours() * 60 + now.getMinutes() - firstHour * 60;

        if (mins < 0 || mins > hourSlots.length * 60) {
            return null;
        }

        return (mins / 60) * pxPerHour;
    }, [dateKey, firstHour, hourSlots.length, pxPerHour, timeZone]);

    return (
        <div className="relative" style={{ height: totalHeight }}>
            {nowLineTop != null ? (
                <div
                    className="pointer-events-none absolute right-0 left-[2.75rem] z-30 pl-2"
                    style={{ top: nowLineTop }}
                    aria-hidden
                >
                    <div className="relative h-0">
                        <span className="absolute -top-1 -left-1 size-2.5 rounded-full bg-rose-500 shadow-sm ring-2 ring-background" />
                        <div className="h-px w-full bg-rose-500/80" />
                    </div>
                </div>
            ) : null}
            {hourSlots.map((hour, index) => {
                const hourLabel = `${String(hour).padStart(2, '0')}:00`;
                const slotKey = `hour:${dateKey}:${hour}`;
                const slotPast = isSlotPast(dateKey, hourLabel);
                const hasEventStartingHere = events.some((event) => {
                    const h = new TZDate(event.inicio_at, timeZone).getHours();

                    return h === hour;
                });

                return (
                    <div
                        key={hour}
                        className="absolute right-0 left-0 grid grid-cols-[2.75rem_1fr] gap-2"
                        style={{
                            top: index * pxPerHour,
                            height: pxPerHour,
                        }}
                    >
                        <span
                            className={cn(
                                '-mt-1.5 text-[0.65rem] text-muted-foreground tabular-nums',
                                compact ? 'pt-0' : 'pt-0.5 text-xs',
                                slotPast && 'opacity-45',
                            )}
                        >
                            {hourLabel}
                        </span>
                        <div
                            onDragOver={(e) => {
                                if (!slotPast) {
                                    onAllowDrop(e, slotKey);
                                }
                            }}
                            onDragLeave={() => onClearDragOver(slotKey)}
                            onDrop={(e) => onDropOnHour(e, dateKey, hour)}
                            className={cn(
                                'relative h-full border-t border-border/40 transition-colors',
                                index === hourSlots.length - 1 &&
                                    'border-b border-border/40',
                                dragOverKey === slotKey &&
                                    'bg-primary/10 ring-2 ring-primary/35 ring-inset',
                            )}
                        >
                            {!hasEventStartingHere &&
                            canCreate &&
                            !slotPast ? (
                                <button
                                    type="button"
                                    onClick={() => onScheduleAt(dateKey, hourLabel)}
                                    className={cn(
                                        'group/slot absolute inset-x-0 top-1 flex cursor-pointer items-center gap-1.5 rounded-md border border-dashed border-transparent px-2 text-left text-[0.65rem] text-muted-foreground/70 transition-all',
                                        compact ? 'h-7' : 'h-8',
                                        'hover:border-primary/40 hover:bg-primary/5 hover:text-primary',
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

            <div
                className="pointer-events-none absolute top-0 right-0 bottom-0 left-[2.75rem] pl-2"
                style={{ height: totalHeight }}
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
                        const widthPct = 100 / columnCount;
                        const leftPct = column * widthPct;

                        return (
                            <button
                                key={event.id}
                                type="button"
                                draggable={draggable}
                                onDragStart={(e) => onDragStart(e, event)}
                                onDragEnd={onDragEnd}
                                onClick={() => onSelectEvent(event)}
                                style={{
                                    top: topPx,
                                    height: heightPx,
                                    left: `calc(${leftPct}% + 2px)`,
                                    width: `calc(${widthPct}% - 4px)`,
                                }}
                                className={cn(
                                    'pointer-events-auto absolute z-10 overflow-hidden rounded-lg border-l-[3px] px-2 py-1 text-left shadow-sm ring-1 ring-black/5 transition-[transform,box-shadow] hover:z-20 hover:shadow-md hover:ring-black/10',
                                    'dark:ring-white/10 dark:hover:ring-white/20',
                                    event.accentClass,
                                    draggable
                                        ? 'cursor-grab active:cursor-grabbing'
                                        : 'cursor-pointer',
                                    compact ? 'text-[0.65rem]' : 'text-xs',
                                )}
                            >
                                <p
                                    className={cn(
                                        'leading-tight font-semibold',
                                        compact
                                            ? 'line-clamp-1'
                                            : 'line-clamp-2',
                                    )}
                                >
                                    {startLabel}
                                    <span className="font-normal opacity-70">
                                        {' '}
                                        – {endLabel}
                                    </span>
                                    {' · '}
                                    {event.title}
                                </p>
                                {heightPx >= pxPerHour * 0.55 ? (
                                    <p className="mt-0.5 truncate text-[0.6rem] opacity-80">
                                        {labels.durationMin(durationMin)}
                                        {event.subtitle
                                            ? ` · ${event.subtitle}`
                                            : ''}
                                    </p>
                                ) : null}
                                {!compact && heightPx >= pxPerHour * 0.9 ? (
                                    <p className="mt-0.5 text-[0.6rem] font-medium opacity-70">
                                        {labels.until} {endLabel}
                                    </p>
                                ) : null}
                            </button>
                        );
                    },
                )}
            </div>
        </div>
    );
}
