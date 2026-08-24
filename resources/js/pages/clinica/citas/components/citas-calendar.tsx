import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
    AgendaMonthCalendar,
    monthRangeFromMes,
    shiftMes,
    type AgendaEvent,
} from '@/components/agenda/agenda-month-calendar';
import type { CitaRow } from '../types';

type Props = {
    citas: readonly CitaRow[];
    mes: string;
    timeZone: string;
    horaInicio: string;
    horaFin: string;
    isLoading?: boolean;
    canCreate: boolean;
    canUpdate?: boolean;
    onSelectCita: (cita: CitaRow) => void;
    onScheduleDay: (fecha: string, hora?: string) => void;
    onReschedule?: (cita: CitaRow, fecha: string, hora?: string) => void;
    onPrevMonth: () => void;
    onNextMonth: () => void;
    onJumpToMonth: (mes: string) => void;
    onToday: () => void;
};

const RESCHEDULABLE = new Set(['programada', 'confirmada']);

function canDragCita(cita: CitaRow, canUpdate: boolean): boolean {
    return canUpdate && RESCHEDULABLE.has(cita.estado);
}

export function getEstadoAccent(estado: string): string {
    switch (estado) {
        case 'en_atencion':
            return 'border-l-sky-500 bg-sky-100/90 text-sky-900 hover:bg-sky-100 dark:bg-sky-950/50 dark:text-sky-100';
        case 'confirmada':
        case 'programada':
            return 'border-l-amber-500 bg-amber-50/90 text-amber-950 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-100';
        case 'completada':
            return 'border-l-emerald-500 bg-emerald-50/90 text-emerald-900 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-100';
        case 'cancelada':
        case 'no_asistio':
            return 'border-l-rose-500 bg-rose-50/80 text-rose-800 line-through opacity-90 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-100';
        default:
            return 'border-l-amber-400 bg-amber-50/80 text-amber-900';
    }
}

export function displayPacienteCita(paciente: CitaRow['paciente']): string {
    return paciente?.nombre ?? '—';
}

export function displayPropietarioCita(
    p: NonNullable<CitaRow['paciente']>['propietario'] | null | undefined,
): string {
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
    horaInicio,
    horaFin,
    isLoading,
    canCreate,
    canUpdate = false,
    onSelectCita,
    onScheduleDay,
    onReschedule,
    onPrevMonth,
    onNextMonth,
    onJumpToMonth,
    onToday,
}: Props) {
    const { t } = useTranslation('citas');

    const citasById = useMemo(
        () => new Map(citas.map((c) => [c.id, c])),
        [citas],
    );

    const events = useMemo(
        (): AgendaEvent[] =>
            citas.map((cita) => ({
                id: cita.id,
                inicio_at: cita.inicio_at,
                duracion_minutos: cita.duracion_minutos,
                title: displayPacienteCita(cita.paciente),
                subtitle: cita.veterinario?.name ?? null,
                accentClass: getEstadoAccent(cita.estado),
                tone: 'cita',
                canDrag: canDragCita(cita, canUpdate),
            })),
        [citas, canUpdate],
    );

    const legend = useMemo(
        () =>
            (
                [
                    { estado: 'programada', swatch: 'bg-amber-400' },
                    { estado: 'en_atencion', swatch: 'bg-sky-500' },
                    { estado: 'completada', swatch: 'bg-emerald-500' },
                    { estado: 'cancelada', swatch: 'bg-rose-500' },
                ] as const
            ).map(({ estado, swatch }) => ({
                key: estado,
                swatch,
                label: t(`estado.${estado}`),
            })),
        [t],
    );

    const labels = useMemo(
        () => ({
            today: t('calendar.today'),
            prevMonth: t('calendar.prev_month'),
            nextMonth: t('calendar.next_month'),
            pickMonth: t('calendar.pick_month'),
            pickYear: t('calendar.pick_year'),
            more: t('calendar.more'),
            dayAgenda: t('calendar.day_agenda'),
            dayEmpty: t('calendar.day_empty'),
            dayCount: (count: number) =>
                t('calendar.day_count', { count }),
            scheduleDay: t('calendar.schedule_day'),
            scheduleAt: (hour: string) =>
                t('calendar.schedule_at', { hour }),
            clickDayHint: t('calendar.click_day_hint'),
            expandDay: t('calendar.expand_day'),
            expandDayTitle: t('calendar.expand_day_title'),
            durationMin: (minutes: number) =>
                t('calendar.duration_min', { minutes }),
            until: t('calendar.until'),
            now: t('calendar.now'),
            dragHint: t('calendar.drag_hint'),
            weekdays: {
                mon: t('calendar.weekdays.mon'),
                tue: t('calendar.weekdays.tue'),
                wed: t('calendar.weekdays.wed'),
                thu: t('calendar.weekdays.thu'),
                fri: t('calendar.weekdays.fri'),
                sat: t('calendar.weekdays.sat'),
                sun: t('calendar.weekdays.sun'),
            },
        }),
        [t],
    );

    return (
        <AgendaMonthCalendar
            events={events}
            mes={mes}
            timeZone={timeZone}
            horaInicio={horaInicio}
            horaFin={horaFin}
            isLoading={isLoading}
            canCreate={canCreate}
            canUpdate={canUpdate}
            legend={legend}
            labels={labels}
            onSelectEvent={(event) => {
                const cita = citasById.get(event.id);
                if (cita) {
                    onSelectCita(cita);
                }
            }}
            onScheduleDay={onScheduleDay}
            onReschedule={
                onReschedule
                    ? (event, fecha, hora) => {
                          const cita = citasById.get(event.id);
                          if (cita) {
                              onReschedule(cita, fecha, hora);
                          }
                      }
                    : undefined
            }
            onPrevMonth={onPrevMonth}
            onNextMonth={onNextMonth}
            onJumpToMonth={onJumpToMonth}
            onToday={onToday}
        />
    );
}

export { monthRangeFromMes, shiftMes };
