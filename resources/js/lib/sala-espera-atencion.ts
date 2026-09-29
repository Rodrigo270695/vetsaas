export const TIPOS_ATENCION_SALA = [
    'no_urgente',
    'reanimacion',
    'emergencia',
    'urgencia',
    'prioritario',
] as const;

export type TipoAtencionSala = (typeof TIPOS_ATENCION_SALA)[number];

export const TIPO_ATENCION_SALA_CLASS: Record<TipoAtencionSala, string> = {
    no_urgente:
        'border-teal-300 bg-teal-50 text-teal-900 dark:border-teal-700 dark:bg-teal-950/50 dark:text-teal-100',
    reanimacion:
        'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100',
    emergencia:
        'border-orange-300 bg-orange-50 text-orange-900 dark:border-orange-700 dark:bg-orange-950/40 dark:text-orange-100',
    urgencia:
        'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100',
    prioritario:
        'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100',
};

export const TIPO_ATENCION_SALA_DOT: Record<TipoAtencionSala, string> = {
    no_urgente: 'bg-teal-500',
    reanimacion: 'bg-rose-500',
    emergencia: 'bg-orange-500',
    urgencia: 'bg-amber-400',
    prioritario: 'bg-emerald-500',
};

export function tipoAtencionSalaDe(value: string | null | undefined): TipoAtencionSala | null {
    return (TIPOS_ATENCION_SALA as readonly string[]).includes(value ?? '')
        ? (value as TipoAtencionSala)
        : null;
}
