import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
    AgendaMonthCalendar,
    monthRangeFromMes,
    shiftMes,
    type AgendaEvent,
} from '@/components/agenda/agenda-month-calendar';
import type { CitaRow } from '../types';
import { CITA_ESTADO_LEGEND, citaEstadoCalendarAccent } from '../cita-estado-styles';

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
    return citaEstadoCalendarAccent(estado);
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
                subtitle: [cita.paciente?.historia_clinica?.codigo, cita.veterinario?.name]
                    .filter(Boolean)
                    .join(' · ') || null,
                accentClass: getEstadoAccent(cita.estado),
                tone: 'cita',
                canDrag: canDragCita(cita, canUpdate),
            })),
        [citas, canUpdate],
    );

    const legend = useMemo(
        () =>
            CITA_ESTADO_LEGEND.map(({ estado, swatch }) => ({
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
