import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';
import { Clock } from 'lucide-react';
import { useMemo } from 'react';
import type { DragEvent } from 'react';
import { cn } from '@/lib/utils';
import type { AgendaEvent } from './agenda-types';

const DEFAULT_DURATION_MIN = 30;
/** Mínimo visual: ~20 min para que la barra se lea bien. */
const MIN_VISUAL_DURATION_MIN = 20;

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
    /** Altura en px de cada hora (Google Calendar ~48–96). */
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
            // Hotel multi-día: en vista diaria, bloque de check-in.
            if (mins > 12 * 60) {
                return 60;
            }

            return mins;
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
 * Layout tipo Google Calendar: columnas para solapes + mismo columnCount por cluster.
 */
function layoutEvents(
    events: readonly AgendaEvent[],
    firstHour: number,
    lastHourInclusive: number,
    pxPerHour: number,
    timeZone: string,
): LaidOutEvent[] {
    const dayEndMinutes = (lastHourInclusive + 1) * 60 - firstHour * 60;

    const enriched: Omit<Enriched, 'column' | 'columnCount'>[] = events
        .map((event) => {
            const durationMin = resolveDurationMinutes(event, timeZone);
            let startMin = minutesFromGridStart(
                event.inicio_at,
                firstHour,
                timeZone,
            );
            const visualDuration = Math.max(durationMin, MIN_VISUAL_DURATION_MIN);
            let endMin = startMin + visualDuration;

            startMin = Math.max(0, Math.min(startMin, dayEndMinutes - 5));
            endMin = Math.min(
                dayEndMinutes,
                Math.max(startMin + MIN_VISUAL_DURATION_MIN, endMin),
            );

            const startTz = new TZDate(event.inicio_at, timeZone);
            const endTz = new Date(startTz.getTime() + durationMin * 60_000);

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
                    28,
                ),
            };
        })
        .sort((a, b) => a.startMin - b.startMin || b.endMin - a.endMin);

    // Asignar columnas greedy
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

    // Clusters conectados → mismo columnCount
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
        (e) => e.topPx < hourEndPx - 4 && e.topPx + e.heightPx > hourStartPx + 4,
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
    pxPerHour = 72,
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

        return (mins / 60) * pxPerHour;
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
            {/* Rejilla horaria + medias horas (estilo Google) */}
            {hourSlots.map((hour, index) => {
                const hourLabel = `${String(hour).padStart(2, '0')}:00`;
                const slotKey = `hour:${dateKey}:${hour}`;
                const slotPast = isSlotPast(dateKey, hourLabel);
                const covered = hourIsCovered(
                    hour,
                    firstHour,
                    laidOut,
                    pxPerHour,
                );

                return (
                    <div
                        key={hour}
                        className="absolute right-0 left-0 grid grid-cols-[3rem_1fr] gap-1.5"
                        style={{
                            top: index * pxPerHour,
                            height: pxPerHour,
                        }}
                    >
                        <span
                            className={cn(
                                '-translate-y-1/2 text-right text-[0.7rem] font-medium text-muted-foreground tabular-nums',
                                slotPast && 'opacity-40',
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
                                'relative h-full border-t border-border/50 bg-transparent transition-colors',
                                index === hourSlots.length - 1 &&
                                    'border-b border-border/50',
                                dragOverKey === slotKey &&
                                    'bg-primary/8 ring-2 ring-primary/30 ring-inset',
                            )}
                        >
                            {/* Línea de media hora */}
                            <div
                                className="pointer-events-none absolute inset-x-0 border-t border-dashed border-border/35"
                                style={{ top: pxPerHour / 2 }}
                                aria-hidden
                            />

                            {!covered && canCreate && !slotPast ? (
                                <button
                                    type="button"
                                    onClick={() =>
                                        onScheduleAt(dateKey, hourLabel)
                                    }
                                    className={cn(
                                        'group/slot absolute inset-x-1 top-1 z-0 flex cursor-pointer items-center gap-1.5 rounded-md px-2 text-left text-[0.65rem] text-muted-foreground/55 transition-all',
                                        compact ? 'h-7' : 'h-8',
                                        'hover:bg-muted/60 hover:text-foreground',
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

            {/* Barras de eventos (capa superior) */}
            <div
                className="pointer-events-none absolute top-0 right-0 bottom-0 left-12"
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
                        const gapPx = columnCount > 1 ? 3 : 2;
                        const widthPct = 100 / columnCount;
                        const leftPct = column * widthPct;
                        const tall = heightPx >= pxPerHour * 0.55;
                        const veryTall = heightPx >= pxPerHour * 0.85;

                        return (
                            <button
                                key={event.id}
                                type="button"
                                title={`${startLabel} – ${endLabel} · ${event.title}${event.subtitle ? ` · ${event.subtitle}` : ''}`}
                                draggable={draggable}
                                onDragStart={(e) => onDragStart(e, event)}
                                onDragEnd={onDragEnd}
                                onClick={() => onSelectEvent(event)}
                                style={{
                                    top: topPx,
                                    height: heightPx,
                                    left: `calc(${leftPct}% + ${gapPx}px)`,
                                    width: `calc(${widthPct}% - ${gapPx * 2}px)`,
                                }}
                                className={cn(
                                    'pointer-events-auto absolute z-10 flex flex-col overflow-hidden rounded-md border border-black/5 text-left shadow-sm',
                                    'border-l-4 transition-[box-shadow,transform] hover:z-20 hover:shadow-md',
                                    'dark:border-white/10',
                                    event.accentClass,
                                    draggable
                                        ? 'cursor-grab active:cursor-grabbing'
                                        : 'cursor-pointer',
                                    // Más “barra” y menos tarjeta
                                    'bg-opacity-95 backdrop-blur-[1px]',
                                    compact ? 'px-1.5 py-0.5' : 'px-2 py-1',
                                )}
                            >
                                {/* Relleno de duración (refuerzo visual) */}
                                <span
                                    className="pointer-events-none absolute inset-y-0 left-0 w-1 opacity-80"
                                    aria-hidden
                                />

                                <p
                                    className={cn(
                                        'relative leading-tight font-semibold',
                                        compact
                                            ? 'text-[0.65rem]'
                                            : 'text-[0.75rem]',
                                    )}
                                >
                                    <span className="tabular-nums">
                                        {startLabel}
                                    </span>
                                    {tall ? (
                                        <span className="font-normal opacity-70">
                                            {' '}
                                            – {endLabel}
                                        </span>
                                    ) : null}
                                    <span className="font-semibold">
                                        {' '}
                                        {event.title}
                                    </span>
                                </p>

                                {tall ? (
                                    <p
                                        className={cn(
                                            'relative mt-0.5 truncate font-medium opacity-80',
                                            compact
                                                ? 'text-[0.6rem]'
                                                : 'text-[0.65rem]',
                                        )}
                                    >
                                        {labels.durationMin(durationMin)}
                                        {event.subtitle
                                            ? ` · ${event.subtitle}`
                                            : ''}
                                    </p>
                                ) : null}

                                {veryTall && !compact ? (
                                    <p className="relative mt-auto pt-1 text-[0.6rem] font-medium opacity-60">
                                        {labels.until} {endLabel}
                                    </p>
                                ) : null}
                            </button>
                        );
                    },
                )}
            </div>

            {/* Línea “ahora” — etiqueta clara para no confundir con duración */}
            {nowLineTop != null ? (
                <div
                    className="pointer-events-none absolute right-0 left-12 z-30"
                    style={{ top: nowLineTop }}
                    aria-hidden
                >
                    <div className="relative flex items-center">
                        <span className="absolute -left-1 size-2.5 -translate-x-1/2 rounded-full bg-rose-500 ring-2 ring-background" />
                        <div className="h-0.5 w-full bg-rose-500/70" />
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
