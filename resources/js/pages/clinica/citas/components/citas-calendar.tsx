import { TZDate } from '@date-fns/tz';
import { addDays, format, isSameDay } from 'date-fns';
import { enUS, es } from 'date-fns/locale';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    MapPin,
    Stethoscope,
    User,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { CitaRow } from '../types';

const HOUR_START = 7;
const HOUR_END = 21;
const SLOT_HEIGHT = 52;

type Props = {
    citas: readonly CitaRow[];
    semanaDesde: string;
    timeZone: string;
    localeCode: string;
    isLoading?: boolean;
    onSelectCita: (cita: CitaRow) => void;
    onPrevWeek: () => void;
    onNextWeek: () => void;
    onToday: () => void;
};

type PlacedCita = {
    cita: CitaRow;
    dayIndex: number;
    topPx: number;
    heightPx: number;
    lane: number;
    laneCount: number;
};

function parseWeekStart(isoDate: string): Date {
    const [y, m, d] = isoDate.split('-').map(Number);

    return new Date(y, m - 1, d, 12, 0, 0);
}

function getEstadoStyles(estado: string): string {
    switch (estado) {
        case 'confirmada':
            return 'border-primary/70 bg-primary text-primary-foreground shadow-sm hover:bg-primary/90';
        case 'programada':
            return 'border-primary/35 bg-primary/12 text-primary hover:bg-primary/20';
        case 'completada':
            return 'border-emerald-400/50 bg-emerald-50 text-emerald-900 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-100';
        case 'cancelada':
            return 'border-border bg-muted/80 text-muted-foreground line-through opacity-75';
        case 'no_asistio':
            return 'border-destructive/40 bg-destructive/10 text-destructive hover:bg-destructive/15';
        default:
            return 'border-primary/30 bg-primary/10 text-primary';
    }
}

function placeCitas(
    citas: readonly CitaRow[],
    weekStart: Date,
    timeZone: string,
): PlacedCita[] {
    const weekDays = Array.from({ length: 7 }, (_, i) => addDays(weekStart, i));
    const byDay: CitaRow[][] = Array.from({ length: 7 }, () => []);

    for (const cita of citas) {
        const start = new TZDate(cita.inicio_at, timeZone);
        const dayIndex = weekDays.findIndex((day) => isSameDay(start, day));

        if (dayIndex >= 0) {
            byDay[dayIndex].push(cita);
        }
    }

    const placed: PlacedCita[] = [];

    byDay.forEach((dayCitas, dayIndex) => {
        const sorted = [...dayCitas].sort(
            (a, b) => new TZDate(a.inicio_at, timeZone).getTime() - new TZDate(b.inicio_at, timeZone).getTime(),
        );

        const lanes: { endMin: number }[] = [];

        for (const cita of sorted) {
            const start = new TZDate(cita.inicio_at, timeZone);
            const startMin = start.getHours() * 60 + start.getMinutes();
            const endMin = startMin + cita.duracion_minutos;

            let lane = lanes.findIndex((l) => l.endMin <= startMin);

            if (lane === -1) {
                lane = lanes.length;
                lanes.push({ endMin });
            } else {
                lanes[lane].endMin = endMin;
            }

            const topMin = Math.max(0, startMin - HOUR_START * 60);
            const visibleEndMin = Math.min(endMin, HOUR_END * 60) - HOUR_START * 60;
            const heightMin = Math.max(24, visibleEndMin - topMin);

            placed.push({
                cita,
                dayIndex,
                topPx: (topMin / 60) * SLOT_HEIGHT,
                heightPx: Math.max(28, (heightMin / 60) * SLOT_HEIGHT),
                lane,
                laneCount: 1,
            });
        }

        const laneCount = Math.max(1, lanes.length);

        for (const item of placed.filter((p) => p.dayIndex === dayIndex)) {
            item.laneCount = laneCount;
        }
    });

    return placed;
}

export function CitasCalendar({
    citas,
    semanaDesde,
    timeZone,
    localeCode,
    isLoading,
    onSelectCita,
    onPrevWeek,
    onNextWeek,
    onToday,
}: Props) {
    const { t } = useTranslation('citas');
    const dateFnsLocale = localeCode.toLowerCase().startsWith('es') ? es : enUS;

    const weekStart = useMemo(() => parseWeekStart(semanaDesde), [semanaDesde]);

    const weekDays = useMemo(
        () => Array.from({ length: 7 }, (_, i) => addDays(weekStart, i)),
        [weekStart],
    );

    const weekLabel = useMemo(() => {
        const end = addDays(weekStart, 6);

        return `${format(weekStart, 'd MMM', { locale: dateFnsLocale })} — ${format(end, 'd MMM yyyy', { locale: dateFnsLocale })}`;
    }, [weekStart, dateFnsLocale]);

    const placed = useMemo(() => placeCitas(citas, weekStart, timeZone), [citas, weekStart, timeZone]);

    const today = useMemo(() => {
        const now = new TZDate(new Date(), timeZone);

        return new Date(now.getFullYear(), now.getMonth(), now.getDate(), 12);
    }, [timeZone]);

    const hours = useMemo(
        () => Array.from({ length: HOUR_END - HOUR_START }, (_, i) => HOUR_START + i),
        [],
    );

    const gridHeight = (HOUR_END - HOUR_START) * SLOT_HEIGHT;

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm ring-1 ring-primary/5',
                isLoading && 'pointer-events-none opacity-60',
            )}
        >
            <div className="flex flex-col gap-3 border-b border-border/60 bg-gradient-to-r from-primary/[0.07] via-primary/[0.03] to-transparent px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex items-center rounded-lg border border-border/70 bg-background/80 p-0.5 shadow-xs">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 cursor-pointer"
                            onClick={onPrevWeek}
                            aria-label={t('calendar.prev_week')}
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 cursor-pointer px-3 text-xs font-medium"
                            onClick={onToday}
                        >
                            {t('calendar.today')}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 cursor-pointer"
                            onClick={onNextWeek}
                            aria-label={t('calendar.next_week')}
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                    <span className="text-sm font-semibold tracking-tight text-foreground">{weekLabel}</span>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {(['programada', 'confirmada', 'completada', 'cancelada'] as const).map((estado) => (
                        <span key={estado} className="flex items-center gap-1.5 text-[0.65rem] text-muted-foreground">
                            <span className={cn('size-2.5 rounded-full border', getEstadoStyles(estado))} />
                            {t(`estado.${estado}`)}
                        </span>
                    ))}
                </div>
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[760px]">
                    <div className="grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))] border-b border-border/60 bg-muted/30">
                        <div className="border-r border-border/40" />
                        {weekDays.map((day, index) => {
                            const isToday = isSameDay(day, today);

                            return (
                                <div
                                    key={index}
                                    className={cn(
                                        'border-r border-border/40 px-2 py-3 text-center last:border-r-0',
                                        isToday && 'bg-primary/[0.08]',
                                    )}
                                >
                                    <p className="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                                        {format(day, 'EEE', { locale: dateFnsLocale })}
                                    </p>
                                    <p
                                        className={cn(
                                            'mt-0.5 text-lg font-semibold tabular-nums',
                                            isToday ? 'text-primary' : 'text-foreground',
                                        )}
                                    >
                                        {format(day, 'd')}
                                    </p>
                                </div>
                            );
                        })}
                    </div>

                    <div className="grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))]">
                        <div className="relative border-r border-border/40 bg-muted/20">
                            {hours.map((hour) => (
                                <div
                                    key={hour}
                                    className="flex h-[52px] items-start justify-end border-b border-border/30 pr-2 pt-1"
                                >
                                    <span className="text-[0.65rem] tabular-nums text-muted-foreground">
                                        {String(hour).padStart(2, '0')}:00
                                    </span>
                                </div>
                            ))}
                        </div>

                        {weekDays.map((day, dayIndex) => {
                            const isToday = isSameDay(day, today);

                            return (
                                <div
                                    key={dayIndex}
                                    className={cn(
                                        'relative border-r border-border/40 last:border-r-0',
                                        isToday && 'bg-primary/[0.03]',
                                    )}
                                    style={{ height: gridHeight }}
                                >
                                    {hours.map((hour) => (
                                        <div
                                            key={hour}
                                            className="h-[52px] border-b border-border/20"
                                        />
                                    ))}

                                    {placed
                                        .filter((p) => p.dayIndex === dayIndex)
                                        .map(({ cita, topPx, heightPx, lane, laneCount }) => {
                                            const widthPct = 100 / laneCount;
                                            const leftPct = lane * widthPct;

                                            return (
                                                <button
                                                    key={cita.id}
                                                    type="button"
                                                    onClick={() => onSelectCita(cita)}
                                                    className={cn(
                                                        'absolute z-10 cursor-pointer overflow-hidden rounded-md border px-1.5 py-1 text-left transition-all',
                                                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1',
                                                        getEstadoStyles(cita.estado),
                                                    )}
                                                    style={{
                                                        top: topPx,
                                                        height: heightPx,
                                                        left: `calc(${leftPct}% + 2px)`,
                                                        width: `calc(${widthPct}% - 4px)`,
                                                    }}
                                                >
                                                    <p className="truncate text-[0.65rem] font-semibold leading-tight">
                                                        {format(new TZDate(cita.inicio_at, timeZone), 'HH:mm')}
                                                        {' · '}
                                                        {cita.paciente.nombre}
                                                    </p>
                                                    {heightPx > 36 && cita.veterinario ? (
                                                        <p className="mt-0.5 truncate text-[0.6rem] opacity-80">
                                                            {cita.veterinario.name}
                                                        </p>
                                                    ) : null}
                                                </button>
                                            );
                                        })}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {citas.length === 0 ? (
                <div className="border-t border-border/60 bg-muted/20 px-4 py-8 text-center">
                    <CalendarClock className="mx-auto size-8 text-primary/40" strokeWidth={1.5} />
                    <p className="mt-2 text-sm font-medium text-foreground">{t('calendar.empty_week_title')}</p>
                    <p className="mt-1 text-xs text-muted-foreground">{t('calendar.empty_week_description')}</p>
                </div>
            ) : null}
        </div>
    );
}

export function displayPropietarioCita(p: CitaRow['paciente']['propietario']): string {
    if (!p) {
        return '—';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
}
