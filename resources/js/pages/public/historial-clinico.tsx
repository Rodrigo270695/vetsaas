import { Head, usePage } from '@inertiajs/react';
import { CalendarDays, History, ShieldCheck } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { Paciente } from '@/pages/clinica/propietarios/types';
import { PacienteHistorialHero } from '@/pages/clinica/pacientes/components/paciente-historial-hero';
import { PacienteTimelineRow } from '@/pages/clinica/pacientes/components/paciente-timeline-row';
import type { TimelineItem } from '@/pages/clinica/pacientes/show';

type Props = {
    clinic: {
        nombre: string;
        logo_url: string | null;
    };
    paciente: Paciente;
    propietario_nombre: string;
    timeline: readonly TimelineItem[];
    links: {
        historial_pdf: string;
    };
    expires_at: string;
    permisos: {
        consultas_ver: boolean;
        vacunas_ver: boolean;
        consultas_crear: boolean;
        vacunas_crear: boolean;
        laboratorio_crear: boolean;
    };
};

export default function PublicHistorialClinico({
    clinic,
    paciente,
    propietario_nombre,
    timeline,
    links,
    expires_at,
    permisos,
}: Props) {
    const { t } = useTranslation(['pacientes']);
    const { locale: appLocale, timezone: appTz } = usePage().props;

    const title = useMemo(
        () => `${paciente.nombre} · ${t('historial.title_suffix')}`,
        [paciente.nombre, t],
    );

    const timelineStats = useMemo(
        () => ({
            consultas: timeline.filter((i) => i.kind === 'consulta').length,
            aplicaciones: timeline.filter((i) => i.kind === 'aplicacion').length,
            total: timeline.length,
        }),
        [timeline],
    );

    return (
        <>
            <Head title={title} />

            <div className="mb-4 flex items-start gap-3 rounded-2xl border border-sky-500/20 bg-sky-500/8 px-3.5 py-3 sm:px-4">
                <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 text-sky-800 dark:text-sky-200">
                    <ShieldCheck className="size-4.5" strokeWidth={2.25} />
                </span>
                <div className="min-w-0 space-y-1">
                    <p className="text-sm font-semibold text-foreground">{t('historial.public_banner_title')}</p>
                    <p className="text-xs leading-relaxed text-muted-foreground">
                        {t('historial.public_banner_body', { clinic: clinic.nombre })}
                    </p>
                </div>
            </div>

            <div className="flex flex-col gap-4">
                <PacienteHistorialHero
                    variant="public"
                    paciente={paciente}
                    propietarioNombre={propietario_nombre}
                    clinicName={clinic.nombre}
                    expiresAt={expires_at}
                    links={{
                        historial_pdf: links.historial_pdf,
                    }}
                    permisos={permisos}
                    timelineStats={timelineStats}
                    hasTimeline={timeline.length > 0}
                />

                <section className="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm ring-1 ring-black/[0.03] dark:ring-white/5">
                    <header className="flex flex-col gap-2 border-b border-border/50 bg-muted/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-primary/12 text-primary">
                                <CalendarDays className="size-4" strokeWidth={2.25} />
                            </span>
                            <div>
                                <h2 className="text-base font-semibold text-foreground">
                                    {t('historial.timeline_title')}
                                </h2>
                                <p className="text-xs text-muted-foreground">{t('historial.public_timeline_hint')}</p>
                            </div>
                        </div>
                    </header>

                    <div className="p-3 sm:p-5">
                        {timeline.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/80 bg-muted/15 px-6 py-12 text-center">
                                <span className="flex size-14 items-center justify-center rounded-2xl bg-muted/50 text-muted-foreground">
                                    <History className="size-7 opacity-60" strokeWidth={1.75} />
                                </span>
                                <p className="max-w-md text-sm text-muted-foreground">
                                    {t('historial.timeline_empty')}
                                </p>
                            </div>
                        ) : (
                            <ul className="relative m-0 list-none p-0">
                                {timeline.map((item, index) => (
                                    <PacienteTimelineRow
                                        key={`${item.kind}-${item.id}`}
                                        item={item}
                                        variant="public"
                                        appLocale={String(appLocale ?? 'es')}
                                        appTz={appTz}
                                        permisos={permisos}
                                        isLast={index === timeline.length - 1}
                                    />
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}
