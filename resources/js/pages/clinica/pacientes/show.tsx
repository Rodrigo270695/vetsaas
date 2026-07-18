import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays, History } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import type { Paciente } from '../propietarios/types';
import { ClinicalHistoryWhatsAppDialog } from './components/clinical-history-whatsapp-dialog';
import type { ClinicalHistoryShareTarget } from './components/clinical-history-whatsapp-dialog';
import { PacienteHistorialHero } from './components/paciente-historial-hero';
import { PacienteTimelineRow } from './components/paciente-timeline-row';

export type TimelineLabLinea = {
    id: string;
    nombre_examen: string;
    resultado: string | null;
    resultado_at: string | null;
    resultado_archivo_url: string | null;
    resultado_archivo_original_name: string | null;
};

export type TimelineConsultaVinculos = {
    recetas: readonly { id: string; estado: string; lineas_count: number; url: string }[];
    laboratorio: readonly {
        id: string;
        estado: string;
        lineas_count: number;
        url: string;
        lineas: readonly TimelineLabLinea[];
    }[];
    cirugias: readonly { id: string; estado: string; titulo: string; url: string }[];
    internamientos: readonly { id: string; estado: string; titulo: string; url: string }[];
};

export type TimelineConsultaDetalle = {
    peso_kg: string | null;
    temperatura_c: string | null;
    fc_lpm: number | null;
    fr_rpm: number | null;
    subjetivo: string | null;
    objetivo: string | null;
    analisis: string | null;
    plan: string | null;
    vinculos: TimelineConsultaVinculos;
};

export type TimelineAplicacionDetalle = {
    producto_nombre: string | null | undefined;
    producto_sku: string | null | undefined;
    lote: string | null;
    numero_dosis: number | null;
    fecha_proxima_sugerida: string | null;
    esquema_antigenos: string | null;
    notas: string | null;
};

export type TimelineItem =
    | {
          kind: 'consulta';
          id: string;
          ocurrido_at: string;
          titulo: string;
          cerrada: boolean;
          veterinario: string | null | undefined;
          historia_url: string;
          pdf_url: string;
          whatsapp_url: string;
          detalle: TimelineConsultaDetalle;
      }
    | {
          kind: 'aplicacion';
          id: string;
          ocurrido_at: string;
          titulo: string;
          categoria: string;
          consulta_id: string | null;
          veterinario: string | null | undefined;
          vacunaciones_url: string;
          pdf_url: string;
          detalle: TimelineAplicacionDetalle;
      };

type Props = {
    paciente: Paciente;
    timeline: readonly TimelineItem[];
    consultas_para_lab?: readonly { id: string; label: string; abierta: boolean }[];
    links: {
        nueva_consulta: string;
        nueva_aplicacion: string;
        historial_pdf: string | null;
        historial_whatsapp: string | null;
        laboratorio_rapido: string | null;
    };
    permisos: {
        consultas_ver: boolean;
        consultas_crear: boolean;
        vacunas_ver: boolean;
        vacunas_crear: boolean;
        laboratorio_crear: boolean;
    };
};

export default function PacienteShow({
    paciente,
    timeline,
    consultas_para_lab = [],
    links,
    permisos,
}: Props) {
    const { t } = useTranslation(['pacientes', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const [shareTarget, setShareTarget] = useState<ClinicalHistoryShareTarget>(null);

    const title = useMemo(() => `${paciente.nombre} · ${t('historial.title_suffix')}`, [paciente.nombre, t]);

    const propietarioNombre = useMemo(() => {
        const p = paciente.propietario;

        if (!p) {
            return '—';
        }

        if (p.razon_social) {
            return p.razon_social;
        }

        return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
    }, [paciente.propietario]);

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
            <div className="flex flex-1 flex-col gap-4 p-4 sm:gap-5 sm:p-6">
                <PacienteHistorialHero
                    paciente={paciente}
                    propietarioNombre={propietarioNombre}
                    links={links}
                    permisos={permisos}
                    consultasParaLab={consultas_para_lab}
                    timelineStats={timelineStats}
                    hasTimeline={timeline.length > 0}
                    onShareHistory={() => {
                        if (links.historial_whatsapp) {
                            setShareTarget({
                                url: links.historial_whatsapp,
                                label: t('historial.document_general'),
                            });
                        }
                    }}
                />

                <section className="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm ring-1 ring-black/[0.03] dark:ring-white/5">
                    <header className="flex flex-col gap-2 border-b border-border/50 bg-muted/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-primary/12 text-primary">
                                <CalendarDays className="size-4" strokeWidth={2.25} />
                            </span>
                            <div>
                                <h2 className="text-base font-semibold text-foreground">{t('historial.timeline_title')}</h2>
                                <p className="text-xs text-muted-foreground">{t('historial.timeline_hint')}</p>
                            </div>
                        </div>
                    </header>

                    <div className="p-4 sm:p-5">
                        {!permisos.consultas_ver && !permisos.vacunas_ver ? (
                            <p className="text-sm text-muted-foreground">{t('historial.sin_permisos')}</p>
                        ) : timeline.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/80 bg-muted/15 px-6 py-12 text-center">
                                <span className="flex size-14 items-center justify-center rounded-2xl bg-muted/50 text-muted-foreground">
                                    <History className="size-7 opacity-60" strokeWidth={1.75} />
                                </span>
                                <p className="max-w-md text-sm text-muted-foreground">{t('historial.timeline_empty')}</p>
                            </div>
                        ) : (
                            <ul className="relative m-0 list-none p-0">
                                {timeline.map((item, index) => (
                                    <PacienteTimelineRow
                                        key={`${item.kind}-${item.id}`}
                                        item={item}
                                        appLocale={String(appLocale ?? 'es')}
                                        appTz={appTz}
                                        permisos={permisos}
                                        isLast={index === timeline.length - 1}
                                        onShareConsulta={(consulta) =>
                                            setShareTarget({
                                                url: consulta.whatsapp_url,
                                                label: t('historial.document_consulta'),
                                            })
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </div>
                </section>

                <Can permission="propietarios.view">
                    <p className="text-center text-xs text-muted-foreground sm:text-left">
                        <Link
                            href={clinica.propietarios.show.url({ propietario: paciente.propietario_id })}
                            className="font-medium text-primary underline-offset-4 hover:underline"
                        >
                            {t('historial.ver_titular')}
                        </Link>
                    </p>
                </Can>
            </div>

            <ClinicalHistoryWhatsAppDialog
                target={shareTarget}
                defaultPhone={paciente.propietario?.telefono ?? ''}
                onOpenChange={(open) => {
                    if (!open) {
                        setShareTarget(null);
                    }
                }}
            />
        </>
    );
}

PacienteShow.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Pacientes', href: clinica.pacientes.index().url },
        { title: 'Historial', href: '#' },
    ],
};
