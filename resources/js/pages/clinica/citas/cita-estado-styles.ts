/**
 * Colores de estado de cita alineados con la Sala de espera
 * (citado/sky, en espera/amber, en atención/violet, atendido/emerald, cancelado/rose).
 */

const BADGE_CLASS: Record<string, string> = {
    programada:
        'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100',
    confirmada:
        'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-700 dark:bg-sky-950/50 dark:text-sky-200',
    en_atencion:
        'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-700 dark:bg-violet-950/50 dark:text-violet-100',
    completada:
        'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100',
    cancelada:
        'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100',
    no_asistio:
        'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100',
};

const CALENDAR_ACCENT: Record<string, string> = {
    programada:
        'border-l-amber-500 bg-amber-50/90 text-amber-950 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-100',
    confirmada:
        'border-l-sky-500 bg-sky-50/90 text-sky-950 hover:bg-sky-100 dark:bg-sky-950/40 dark:text-sky-100',
    en_atencion:
        'border-l-violet-500 bg-violet-50/90 text-violet-900 hover:bg-violet-100 dark:bg-violet-950/50 dark:text-violet-100',
    completada:
        'border-l-emerald-500 bg-emerald-50/90 text-emerald-900 hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-100',
    cancelada:
        'border-l-rose-500 bg-rose-50/80 text-rose-800 line-through opacity-90 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-100',
    no_asistio:
        'border-l-rose-500 bg-rose-50/80 text-rose-800 line-through opacity-90 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-100',
};

export const CITA_ESTADO_LEGEND: readonly { estado: string; swatch: string }[] = [
    { estado: 'programada', swatch: 'bg-amber-500' },
    { estado: 'confirmada', swatch: 'bg-sky-500' },
    { estado: 'en_atencion', swatch: 'bg-violet-500' },
    { estado: 'completada', swatch: 'bg-emerald-500' },
    { estado: 'cancelada', swatch: 'bg-rose-500' },
];

export function citaEstadoBadgeClass(estado: string): string {
    return BADGE_CLASS[estado] ?? BADGE_CLASS.programada;
}

export function citaEstadoCalendarAccent(estado: string): string {
    return CALENDAR_ACCENT[estado] ?? CALENDAR_ACCENT.programada;
}
