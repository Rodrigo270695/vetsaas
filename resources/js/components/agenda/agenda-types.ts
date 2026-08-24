export type AgendaEvent = {
    id: string;
    inicio_at: string;
    /** Fin estimado (grooming: inicio + duración; hotel: egreso). */
    fin_at?: string | null;
    /** Duración en minutos (grooming / citas). */
    duracion_minutos?: number | null;
    title: string;
    subtitle?: string | null;
    accentClass: string;
    /** Tono de barra en timeline del día (Google Calendar). */
    tone?: 'grooming' | 'hotel' | 'cita' | 'default';
    canDrag?: boolean;
};

export type AgendaLegendItem = {
    key: string;
    swatch: string;
    label: string;
};

export type AgendaCalendarLabels = {
    today: string;
    prevMonth: string;
    nextMonth: string;
    pickMonth: string;
    pickYear: string;
    more: string;
    dayAgenda: string;
    dayEmpty: string;
    dayCount: (count: number) => string;
    scheduleDay: string;
    scheduleAt: (hour: string) => string;
    clickDayHint: string;
    dragHint?: string;
    expandDay?: string;
    expandDayTitle?: string;
    durationMin?: (minutes: number) => string;
    until?: string;
    now?: string;
    weekdays: Record<
        'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun',
        string
    >;
};
