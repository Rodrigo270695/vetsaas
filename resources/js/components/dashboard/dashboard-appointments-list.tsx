import { Link } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import type { Locale } from 'date-fns';
import { CalendarDays, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ProximaCitaRow } from '@/pages/dashboard/types';

const estadoDot: Record<string, string> = {
    programada: 'bg-sky-500',
    confirmada: 'bg-brand-500',
    completada: 'bg-emerald-500',
    cancelada: 'bg-muted-foreground/40',
    no_asistio: 'bg-amber-500',
};

type Props = {
    citas: ProximaCitaRow[];
    estadoLabel: (estado: string) => string;
    dateLocale: Locale;
    verTodasHref?: string;
};

export function DashboardAppointmentsList({
    citas,
    estadoLabel,
    dateLocale,
    verTodasHref = '/clinica/citas',
}: Props) {
    const { t } = useTranslation('dashboard');

    return (
        <Card className="min-w-0 border-border/80 shadow-sm lg:col-span-2">
            <CardHeader className="flex flex-row items-center justify-between gap-2 border-b border-border/50 pb-4">
                <div className="flex items-center gap-2">
                    <div className="flex size-9 items-center justify-center rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200">
                        <CalendarDays className="size-4" aria-hidden />
                    </div>
                    <CardTitle className="text-base font-semibold">{t('proximas_citas.title')}</CardTitle>
                </div>
                <Button variant="ghost" size="sm" className="text-brand-700 hover:text-brand-800" asChild>
                    <Link href={verTodasHref}>
                        {t('proximas_citas.ver_todas')}
                        <ChevronRight className="ml-1 size-4" />
                    </Link>
                </Button>
            </CardHeader>
            <CardContent className="pt-4">
                {citas.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/80 bg-muted/20 py-10 text-center">
                        <CalendarDays className="size-8 text-muted-foreground/50" aria-hidden />
                        <p className="text-sm text-muted-foreground">{t('proximas_citas.empty')}</p>
                    </div>
                ) : (
                    <ul className="space-y-2">
                        {citas.map((cita) => (
                            <li
                                key={cita.id}
                                className="flex flex-col gap-2 rounded-xl border border-border/60 bg-card px-4 py-3 transition-colors hover:border-brand-200/60 hover:bg-brand-50/30 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-brand-950/20"
                            >
                                <div className="flex min-w-0 items-start gap-3">
                                    <span
                                        className={`mt-1.5 size-2.5 shrink-0 rounded-full ${estadoDot[cita.estado] ?? 'bg-muted-foreground'}`}
                                        aria-hidden
                                    />
                                    <div className="min-w-0">
                                        <p className="font-medium text-foreground">
                                            {cita.paciente_nombre ?? '—'}
                                        </p>
                                        <p className="mt-0.5 truncate text-sm text-muted-foreground">
                                            {[cita.veterinario_nombre, cita.sede_nombre, cita.motivo]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                </div>
                                <div className="shrink-0 sm:text-right">
                                    <p className="text-sm font-medium text-foreground">
                                        {cita.inicio_at
                                            ? format(parseISO(cita.inicio_at), 'PPp', {
                                                  locale: dateLocale,
                                              })
                                            : '—'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {estadoLabel(cita.estado)}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
