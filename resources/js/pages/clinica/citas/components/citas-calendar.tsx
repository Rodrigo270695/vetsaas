import { TZDate } from '@date-fns/tz';
import {
    addDays,
    addMonths,
    endOfMonth,
    format,
    isSameMonth,
    startOfMonth,
    startOfWeek,
} from 'date-fns';
import { enUS, es } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, Clock, Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { CitaRow } from '../types';

const WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const;
const HOUR_START = 7;
const HOUR_END = 21;
const MAX_PILLS = 2;

type Props = {
    citas: readonly CitaRow[];
    mes: string;
    timeZone: string;
    isLoading?: boolean;
    canCreate: boolean;
    onSelectCita: (cita: CitaRow) => void;
    onScheduleDay: (fecha: string, hora?: string) => void;
    onPrevMonth: () => void;
    onNextMonth: () => void;
    onJumpToMonth: (mes: string) => void;
    onToday: () => void;
};

function parseMes(mes: string): Date {
    const [y, m] = mes.split('-').map(Number);

    return new Date(y, m - 1, 1, 12, 0, 0);
}

function toDateKey(d: Date, timeZone: string): string {
    const tz = new TZDate(d, timeZone);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${tz.getFullYear()}-${pad(tz.getMonth() + 1)}-${pad(tz.getDate())}`;
}

function getEstadoAccent(estado: string): string {
    switch (estado) {
        case 'confirmada':
            return 'border-l-primary bg-primary/15 text-primary hover:bg-primary/25';
        case 'programada':
            return 'border-l-primary/60 bg-primary/8 text-primary hover:bg-primary/15';
        case 'completada':
            return 'border-l-emerald-500 bg-emerald-50/90 text-emerald-900 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-100';
        case 'cancelada':
            return 'border-l-muted-foreground/40 bg-muted/70 text-muted-foreground line-through opacity-80';
        case 'no_asistio':
            return 'border-l-destructive bg-destructive/10 text-destructive hover:bg-destructive/15';
        default:
            return 'border-l-primary/40 bg-primary/10 text-primary';
    }
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

export function CitasCalendar({
    citas,
    mes,
    timeZone,
    isLoading,
    canCreate,
    onSelectCita,
    onScheduleDay,
    onPrevMonth,
    onNextMonth,
    onJumpToMonth,
    onToday,
}: Props) {
    const { t, i18n } = useTranslation('citas');
    const dateFnsLocale = i18n.language.startsWith('en') ? enUS : es;

    const monthStart = useMemo(() => parseMes(mes), [mes]);
    const [selectedDay, setSelectedDay] = useState<string | null>(null);

    useEffect(() => {
        setSelectedDay(null);
    }, [mes]);

    const todayKey = useMemo(() => toDateKey(new Date(), timeZone), [timeZone]);

    const defaultPanelDay = useMemo(() => {
        if (todayKey.startsWith(`${mes}-`)) {
            return todayKey;
        }

        return `${mes}-01`;
    }, [todayKey, mes]);

    const citasByDay = useMemo(() => {
        const map = new Map<string, CitaRow[]>();

        for (const cita of citas) {
            const key = toDateKey(new TZDate(cita.inicio_at, timeZone), timeZone);
            const list = map.get(key) ?? [];
            list.push(cita);
            map.set(key, list);
        }

        for (const [, list] of map) {
            list.sort(
                (a, b) =>
                    new TZDate(a.inicio_at, timeZone).getTime() -
                    new TZDate(b.inicio_at, timeZone).getTime(),
            );
        }

        return map;
    }, [citas, timeZone]);

    const gridDays = useMemo(() => {
        const start = startOfWeek(startOfMonth(monthStart), { weekStartsOn: 1 });

        return Array.from({ length: 42 }, (_, i) => addDays(start, i));
    }, [monthStart]);

    const [mesYear, mesMonth] = useMemo(() => {
        const [y, m] = mes.split('-').map(Number);

        return [y, m] as const;
    }, [mes]);

    const monthOptions = useMemo(
        () =>
            Array.from({ length: 12 }, (_, index) => {
                const d = new Date(mesYear, index, 1);

                return {
                    value: String(index + 1),
                    label: format(d, 'MMMM', { locale: dateFnsLocale }),
                };
            }),
        [dateFnsLocale, mesYear],
    );

    const yearOptions = useMemo(() => {
        const currentYear = new Date().getFullYear();
        const min = Math.min(mesYear - 8, currentYear - 15, 2020);
        const max = Math.max(mesYear + 3, currentYear + 2);
        const years: number[] = [];

        for (let y = min; y <= max; y += 1) {
            years.push(y);
        }

        return years;
    }, [mesYear]);

    const jumpToParts = useCallback(
        (year: number, month: number) => {
            const pad = (n: number) => String(n).padStart(2, '0');
            onJumpToMonth(`${year}-${pad(month)}`);
        },
        [onJumpToMonth],
    );

    const activeDay = selectedDay ?? defaultPanelDay;
    const activeDayCitas = citasByDay.get(activeDay) ?? [];

    const activeDayLabel = useMemo(() => {
        const [y, m, d] = activeDay.split('-').map(Number);
        const dt = new Date(y, m - 1, d, 12);

        return format(dt, "EEEE d 'de' MMMM", { locale: dateFnsLocale });
    }, [activeDay, dateFnsLocale]);

    const hourSlots = useMemo(
        () => Array.from({ length: HOUR_END - HOUR_START }, (_, i) => HOUR_START + i),
        [],
    );

    const handleDayClick = (dateKey: string, inMonth: boolean) => {
        if (!inMonth) {
            return;
        }

        setSelectedDay(dateKey);

        if (canCreate) {
            onScheduleDay(dateKey);
        }
    };

    return (
        <div
            className={cn(
                'overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm ring-1 ring-primary/5',
                isLoading && 'pointer-events-none opacity-60',
            )}
        >
            <div className="flex flex-col gap-3 border-b border-border/50 bg-gradient-to-r from-primary/[0.08] via-primary/[0.03] to-transparent px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex items-center rounded-xl border border-border/60 bg-background/90 p-0.5 shadow-xs">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 cursor-pointer rounded-lg"
                            onClick={onPrevMonth}
                            aria-label={t('calendar.prev_month')}
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 cursor-pointer rounded-lg px-3 text-xs font-semibold capitalize"
                            onClick={onToday}
                        >
                            {t('calendar.today')}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 cursor-pointer rounded-lg"
                            onClick={onNextMonth}
                            aria-label={t('calendar.next_month')}
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Select
                            value={String(mesMonth)}
                            onValueChange={(value) => jumpToParts(mesYear, Number.parseInt(value, 10))}
                        >
                            <SelectTrigger
                                className="h-8 w-[8.5rem] cursor-pointer capitalize"
                                aria-label={t('calendar.pick_month')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {monthOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                        className="cursor-pointer capitalize"
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={String(mesYear)}
                            onValueChange={(value) => jumpToParts(Number.parseInt(value, 10), mesMonth)}
                        >
                            <SelectTrigger
                                className="h-8 w-[5.5rem] cursor-pointer tabular-nums"
                                aria-label={t('calendar.pick_year')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="max-h-56">
                                {yearOptions.map((year) => (
                                    <SelectItem key={year} value={String(year)} className="cursor-pointer tabular-nums">
                                        {year}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3 text-[0.65rem] text-muted-foreground">
                    {(['programada', 'confirmada', 'completada', 'cancelada'] as const).map((estado) => (
                        <span key={estado} className="flex items-center gap-1.5">
                            <span className={cn('size-2 rounded-full border-l-2', getEstadoAccent(estado))} />
                            {t(`estado.${estado}`)}
                        </span>
                    ))}
                </div>
            </div>

            <div className="grid lg:grid-cols-[1fr_minmax(17rem,22rem)]">
                <div className="border-b border-border/50 p-3 sm:p-4 lg:border-b-0 lg:border-r">
                    <div className="mb-2 grid grid-cols-7 gap-1">
                        {WEEKDAY_KEYS.map((key) => (
                            <div
                                key={key}
                                className="py-1 text-center text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground"
                            >
                                {t(`calendar.weekdays.${key}`)}
                            </div>
                        ))}
                    </div>

                    <div className="grid grid-cols-7 gap-1.5">
                        {gridDays.map((day) => {
                            const dateKey = toDateKey(day, timeZone);
                            const inMonth = isSameMonth(day, monthStart);
                            const isToday = dateKey === todayKey;
                            const isSelected = dateKey === activeDay;
                            const dayCitas = citasByDay.get(dateKey) ?? [];
                            const overflow = Math.max(0, dayCitas.length - MAX_PILLS);

                            return (
                                <div
                                    key={dateKey}
                                    role="button"
                                    tabIndex={inMonth ? 0 : -1}
                                    onClick={() => handleDayClick(dateKey, inMonth)}
                                    onKeyDown={(e) => {
                                        if (inMonth && (e.key === 'Enter' || e.key === ' ')) {
                                            e.preventDefault();
                                            handleDayClick(dateKey, inMonth);
                                        }
                                    }}
                                    className={cn(
                                        'group relative flex min-h-[5.5rem] flex-col rounded-xl border p-1.5 text-left transition-all sm:min-h-[6.5rem]',
                                        inMonth
                                            ? 'cursor-pointer border-border/50 bg-background hover:border-primary/40 hover:bg-primary/[0.03] hover:shadow-sm'
                                            : 'cursor-default border-transparent bg-muted/20 opacity-40',
                                        isToday && inMonth && 'ring-1 ring-primary/50',
                                        isSelected &&
                                            inMonth &&
                                            'border-primary/60 bg-primary/[0.06] shadow-md ring-2 ring-primary/30',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'mb-1 flex size-7 items-center justify-center rounded-full text-sm font-semibold tabular-nums',
                                            isToday && 'bg-primary text-primary-foreground',
                                            isSelected && !isToday && 'bg-primary/15 text-primary',
                                            !isToday && !isSelected && 'text-foreground',
                                        )}
                                    >
                                        {format(day, 'd')}
                                    </span>

                                    <div className="flex min-h-0 flex-1 flex-col gap-0.5 overflow-hidden">
                                        {dayCitas.slice(0, MAX_PILLS).map((cita) => (
                                            <button
                                                key={cita.id}
                                                type="button"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setSelectedDay(dateKey);
                                                    onSelectCita(cita);
                                                }}
                                                className={cn(
                                                    'w-full cursor-pointer truncate rounded-md border-l-[3px] px-1 py-0.5 text-left text-[0.6rem] font-medium leading-tight transition-colors',
                                                    getEstadoAccent(cita.estado),
                                                )}
                                            >
                                                {format(new TZDate(cita.inicio_at, timeZone), 'HH:mm')}{' '}
                                                {cita.paciente.nombre}
                                            </button>
                                        ))}
                                        {overflow > 0 ? (
                                            <span className="px-1 text-[0.6rem] font-medium text-primary">
                                                +{overflow} {t('calendar.more')}
                                            </span>
                                        ) : null}
                                    </div>

                                    {inMonth && canCreate ? (
                                        <span className="pointer-events-none absolute bottom-1 right-1 opacity-0 transition-opacity group-hover:opacity-100">
                                            <Plus className="size-3.5 text-primary" />
                                        </span>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>

                    {canCreate ? (
                        <p className="mt-3 text-center text-xs text-muted-foreground">{t('calendar.click_day_hint')}</p>
                    ) : null}
                </div>

                <aside className="flex flex-col bg-gradient-to-b from-muted/30 to-background p-4">
                    <div className="mb-4">
                        <p className="text-[0.65rem] font-semibold uppercase tracking-wider text-primary">
                            {t('calendar.day_agenda')}
                        </p>
                        <h3 className="mt-1 text-sm font-semibold capitalize text-foreground">{activeDayLabel}</h3>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {activeDayCitas.length === 0
                                ? t('calendar.day_empty')
                                : t('calendar.day_count', { count: activeDayCitas.length })}
                        </p>
                    </div>

                    <div className="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto pr-1">
                        {hourSlots.map((hour) => {
                            const slotCitas = activeDayCitas.filter((c) => {
                                const h = new TZDate(c.inicio_at, timeZone).getHours();

                                return h === hour;
                            });

                            const isOccupied = slotCitas.length > 0;

                            return (
                                <div key={hour} className="grid grid-cols-[3rem_1fr] items-start gap-2">
                                    <span className="pt-1 text-[0.65rem] tabular-nums text-muted-foreground">
                                        {String(hour).padStart(2, '0')}:00
                                    </span>
                                    {isOccupied ? (
                                        <div className="space-y-1">
                                            {slotCitas.map((cita) => (
                                                <button
                                                    key={cita.id}
                                                    type="button"
                                                    onClick={() => onSelectCita(cita)}
                                                    className={cn(
                                                        'w-full cursor-pointer rounded-lg border-l-[3px] px-2.5 py-2 text-left transition-colors',
                                                        getEstadoAccent(cita.estado),
                                                    )}
                                                >
                                                    <p className="text-xs font-semibold">
                                                        {format(new TZDate(cita.inicio_at, timeZone), 'HH:mm')}
                                                        {' · '}
                                                        {cita.paciente.nombre}
                                                    </p>
                                                    {cita.veterinario ? (
                                                        <p className="mt-0.5 truncate text-[0.65rem] opacity-80">
                                                            {cita.veterinario.name}
                                                        </p>
                                                    ) : null}
                                                </button>
                                            ))}
                                        </div>
                                    ) : canCreate ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                onScheduleDay(activeDay, `${String(hour).padStart(2, '0')}:00`)
                                            }
                                            className="group/slot flex h-9 w-full cursor-pointer items-center gap-2 rounded-lg border border-dashed border-primary/25 px-2 text-left text-[0.65rem] text-muted-foreground transition-colors hover:border-primary/50 hover:bg-primary/5 hover:text-primary"
                                        >
                                            <Clock className="size-3 opacity-60 group-hover/slot:opacity-100" />
                                            {t('calendar.schedule_at', {
                                                hour: `${String(hour).padStart(2, '0')}:00`,
                                            })}
                                        </button>
                                    ) : (
                                        <div className="h-9 rounded-lg border border-dashed border-border/40" />
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </aside>
            </div>
        </div>
    );
}

export function shiftMes(mes: string, delta: number): string {
    const start = parseMes(mes);
    const next = addMonths(start, delta);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${next.getFullYear()}-${pad(next.getMonth() + 1)}`;
}

export function monthRangeFromMes(mes: string): { desde: string; hasta: string } {
    const start = parseMes(mes);
    const end = endOfMonth(start);
    const pad = (n: number) => String(n).padStart(2, '0');
    const fmt = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    return { desde: fmt(start), hasta: fmt(end) };
}
