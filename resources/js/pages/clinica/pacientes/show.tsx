import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    ChevronDown,
    ClipboardList,
    ExternalLink,
    FileDown,
    Plus,
    Syringe,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { PageHeader } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import { formatAtendidoInAppTimezone } from '../historias-clinicas/format-atendido';
import type { Paciente } from '../propietarios/types';

export type TimelineConsultaVinculos = {
    recetas: readonly { id: string; estado: string; lineas_count: number; url: string }[];
    laboratorio: readonly { id: string; estado: string; lineas_count: number; url: string }[];
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
    links: {
        nueva_consulta: string;
        nueva_aplicacion: string;
        historial_pdf: string | null;
    };
    permisos: {
        consultas_ver: boolean;
        consultas_crear: boolean;
        vacunas_ver: boolean;
        vacunas_crear: boolean;
    };
};

function vinculosConsultaTieneContenido(v: TimelineConsultaVinculos): boolean {
    return (
        v.recetas.length > 0 ||
        v.laboratorio.length > 0 ||
        v.cirugias.length > 0 ||
        v.internamientos.length > 0
    );
}

function consultaDetalleTieneContenido(d: TimelineConsultaDetalle): boolean {
    return (
        Boolean(
            d.peso_kg ||
                d.temperatura_c ||
                d.fc_lpm != null ||
                d.fr_rpm != null ||
                d.subjetivo ||
                d.objetivo ||
                d.analisis ||
                d.plan,
        ) || vinculosConsultaTieneContenido(d.vinculos)
    );
}

function aplicacionDetalleTieneContenido(d: TimelineAplicacionDetalle): boolean {
    return Boolean(
        d.producto_nombre ||
            d.lote ||
            d.numero_dosis != null ||
            d.fecha_proxima_sugerida ||
            d.esquema_antigenos ||
            d.notas,
    );
}

function SoapBlock({
    label,
    text,
}: {
    label: string;
    text: string | null;
}) {
    if (!text) {
        return null;
    }

    return (
        <div className="space-y-0.5">
            <p className="text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="whitespace-pre-wrap text-sm leading-relaxed text-foreground">{text}</p>
        </div>
    );
}

type TimelineRowProps = {
    item: TimelineItem;
    appLocale: string;
    appTz: string | undefined;
    permisos: Props['permisos'];
};

function TimelineRow({ item, appLocale, appTz, permisos }: TimelineRowProps) {
    const { t } = useTranslation(['pacientes', 'recetas', 'laboratorio', 'cirugia', 'common']);
    const [resumenAbierto, setResumenAbierto] = useState(false);

    const fechaFmt = formatAtendidoInAppTimezone(
        item.ocurrido_at,
        String(appLocale ?? 'es'),
        appTz,
    );

    const categoriaEtiqueta = (c: string) => {
        const k = (c ?? 'vacuna').toLowerCase();

        if (k === 'desparasitacion') {
            return t('historial.cat_desparasitacion');
        }

        if (k === 'otro') {
            return t('historial.cat_otro');
        }

        return t('historial.cat_vacuna');
    };

    const hayResumen =
        item.kind === 'consulta'
            ? consultaDetalleTieneContenido(item.detalle)
            : aplicacionDetalleTieneContenido(item.detalle);

    return (
        <li className="relative pb-8 pl-0 last:pb-0 sm:pl-11">
            <div
                className="absolute left-[15px] top-3 hidden h-[calc(100%-0.5rem)] w-px bg-border sm:block"
                aria-hidden
            />
            <div className="absolute left-0 top-1.5 hidden size-8 items-center justify-center rounded-full border-2 border-background bg-primary/12 shadow-sm sm:flex">
                {item.kind === 'consulta' ? (
                    <ClipboardList className="size-4 text-primary" strokeWidth={2.25} />
                ) : (
                    <Syringe className="size-4 text-primary" strokeWidth={2.25} />
                )}
            </div>

            <div
                className={cn(
                    'relative rounded-xl border border-border/80 bg-card p-3 shadow-sm sm:ml-1 sm:p-4',
                    'ring-1 ring-black/5 dark:ring-white/10',
                )}
            >
                <div className="mb-2 flex items-start gap-3 sm:hidden">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/12">
                        {item.kind === 'consulta' ? (
                            <ClipboardList className="size-4 text-primary" strokeWidth={2.25} />
                        ) : (
                            <Syringe className="size-4 text-primary" strokeWidth={2.25} />
                        )}
                    </span>
                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge variant="outline" className="text-[0.65rem] font-normal">
                                {item.kind === 'consulta'
                                    ? t('historial.badge_consulta')
                                    : t('historial.badge_aplicacion')}
                            </Badge>
                            {item.kind === 'consulta' ? (
                                item.cerrada ? (
                                    <Badge
                                        variant="secondary"
                                        className="border-amber-500/30 bg-amber-500/15 text-[0.65rem] font-normal text-amber-950 dark:text-amber-100"
                                    >
                                        {t('historial.badge_cerrada')}
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="border-emerald-600/35 text-[0.65rem] font-normal text-emerald-900 dark:text-emerald-100"
                                    >
                                        {t('historial.badge_abierta')}
                                    </Badge>
                                )
                            ) : (
                                <Badge variant="secondary" className="text-[0.65rem] font-normal">
                                    {categoriaEtiqueta(item.categoria)}
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm font-semibold leading-snug text-foreground">{item.titulo}</p>
                        <p className="text-xs text-muted-foreground">
                            {item.veterinario ? `${item.veterinario} · ` : null}
                            {fechaFmt}
                        </p>
                    </div>
                </div>

                <div className="hidden sm:block">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className="text-[0.65rem] font-normal">
                            {item.kind === 'consulta'
                                ? t('historial.badge_consulta')
                                : t('historial.badge_aplicacion')}
                        </Badge>
                        {item.kind === 'consulta' ? (
                            item.cerrada ? (
                                <Badge
                                    variant="secondary"
                                    className="border-amber-500/30 bg-amber-500/15 text-[0.65rem] font-normal text-amber-950 dark:text-amber-100"
                                >
                                    {t('historial.badge_cerrada')}
                                </Badge>
                            ) : (
                                <Badge
                                    variant="outline"
                                    className="border-emerald-600/35 text-[0.65rem] font-normal text-emerald-900 dark:text-emerald-100"
                                >
                                    {t('historial.badge_abierta')}
                                </Badge>
                            )
                        ) : (
                            <Badge variant="secondary" className="text-[0.65rem] font-normal">
                                {categoriaEtiqueta(item.categoria)}
                            </Badge>
                        )}
                    </div>
                    <p className="mt-1.5 text-base font-semibold leading-snug text-foreground">{item.titulo}</p>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {item.veterinario ? `${item.veterinario} · ` : null}
                        {fechaFmt}
                    </p>
                </div>

                {item.kind === 'consulta' && hayResumen ? (
                    <Collapsible open={resumenAbierto} onOpenChange={setResumenAbierto} className="mt-3">
                        <CollapsibleTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 gap-1.5 px-2 text-muted-foreground hover:text-foreground"
                            >
                                <ChevronDown
                                    className={cn('size-4 transition-transform', resumenAbierto && 'rotate-180')}
                                    strokeWidth={2.25}
                                />
                                {resumenAbierto ? t('historial.ocultar_resumen') : t('historial.ver_resumen')}
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="overflow-hidden">
                            <div className="mt-2 space-y-3 rounded-lg border border-border/60 bg-muted/20 p-3 text-sm">
                                {(item.detalle.peso_kg ||
                                    item.detalle.temperatura_c ||
                                    item.detalle.fc_lpm != null ||
                                    item.detalle.fr_rpm != null) && (
                                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        {item.detalle.peso_kg ? (
                                            <span>
                                                <span className="font-medium text-foreground">
                                                    {t('historial.det_peso')}
                                                </span>{' '}
                                                {item.detalle.peso_kg} kg
                                            </span>
                                        ) : null}
                                        {item.detalle.temperatura_c ? (
                                            <span>
                                                <span className="font-medium text-foreground">
                                                    {t('historial.det_temp')}
                                                </span>{' '}
                                                {item.detalle.temperatura_c} °C
                                            </span>
                                        ) : null}
                                        {item.detalle.fc_lpm != null ? (
                                            <span>
                                                <span className="font-medium text-foreground">
                                                    {t('historial.det_fc')}
                                                </span>{' '}
                                                {item.detalle.fc_lpm} lpm
                                            </span>
                                        ) : null}
                                        {item.detalle.fr_rpm != null ? (
                                            <span>
                                                <span className="font-medium text-foreground">
                                                    {t('historial.det_fr')}
                                                </span>{' '}
                                                {item.detalle.fr_rpm} rpm
                                            </span>
                                        ) : null}
                                    </div>
                                )}
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <SoapBlock label={t('historial.det_subjetivo')} text={item.detalle.subjetivo} />
                                    <SoapBlock label={t('historial.det_objetivo')} text={item.detalle.objetivo} />
                                    <SoapBlock label={t('historial.det_analisis')} text={item.detalle.analisis} />
                                    <SoapBlock label={t('historial.det_plan_soap')} text={item.detalle.plan} />
                                </div>
                                {vinculosConsultaTieneContenido(item.detalle.vinculos) ? (
                                    <div className="space-y-4 border-t border-border/60 pt-3">
                                        <p className="text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">
                                            {t('historial.vinculos_lead')}
                                        </p>
                                        {item.detalle.vinculos.recetas.length > 0 ? (
                                            <div className="space-y-2">
                                                <p className="text-xs font-medium text-foreground">
                                                    {t('historial.vinculos_sec_recetas')}
                                                </p>
                                                <ul className="m-0 list-none space-y-2 p-0">
                                                    {item.detalle.vinculos.recetas.map((r) => (
                                                        <li
                                                            key={r.id}
                                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/50 bg-background/80 px-2 py-1.5"
                                                        >
                                                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-[0.65rem] font-normal"
                                                                >
                                                                    {t(`recetas:estado.${r.estado}`, {
                                                                        defaultValue: r.estado,
                                                                    })}
                                                                </Badge>
                                                                <span className="text-xs text-muted-foreground">
                                                                    {t('historial.vinculos_meds', {
                                                                        count: r.lineas_count,
                                                                    })}
                                                                </span>
                                                            </div>
                                                            <Button type="button" variant="link" size="sm" className="h-7 px-1" asChild>
                                                                <a href={r.url}>{t('historial.vinculos_abrir')}</a>
                                                            </Button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ) : null}
                                        {item.detalle.vinculos.laboratorio.length > 0 ? (
                                            <div className="space-y-2">
                                                <p className="text-xs font-medium text-foreground">
                                                    {t('historial.vinculos_sec_lab')}
                                                </p>
                                                <ul className="m-0 list-none space-y-2 p-0">
                                                    {item.detalle.vinculos.laboratorio.map((p) => (
                                                        <li
                                                            key={p.id}
                                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/50 bg-background/80 px-2 py-1.5"
                                                        >
                                                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-[0.65rem] font-normal"
                                                                >
                                                                    {t(`laboratorio:estado.${p.estado}`, {
                                                                        defaultValue: p.estado,
                                                                    })}
                                                                </Badge>
                                                                <span className="text-xs text-muted-foreground">
                                                                    {t('historial.vinculos_exam', {
                                                                        count: p.lineas_count,
                                                                    })}
                                                                </span>
                                                            </div>
                                                            <Button type="button" variant="link" size="sm" className="h-7 px-1" asChild>
                                                                <a href={p.url}>{t('historial.vinculos_abrir')}</a>
                                                            </Button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ) : null}
                                        {item.detalle.vinculos.cirugias.length > 0 ? (
                                            <div className="space-y-2">
                                                <p className="text-xs font-medium text-foreground">
                                                    {t('historial.vinculos_sec_ciru')}
                                                </p>
                                                <ul className="m-0 list-none space-y-2 p-0">
                                                    {item.detalle.vinculos.cirugias.map((c) => (
                                                        <li
                                                            key={c.id}
                                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/50 bg-background/80 px-2 py-1.5"
                                                        >
                                                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                                                <span className="truncate text-sm font-medium text-foreground">
                                                                    {c.titulo}
                                                                </span>
                                                                <Badge
                                                                    variant="outline"
                                                                    className="w-fit text-[0.65rem] font-normal"
                                                                >
                                                                    {t(`cirugia:estado.${c.estado}`, {
                                                                        defaultValue: c.estado,
                                                                    })}
                                                                </Badge>
                                                            </div>
                                                            <Button type="button" variant="link" size="sm" className="h-7 shrink-0 px-1" asChild>
                                                                <a href={c.url}>{t('historial.vinculos_abrir')}</a>
                                                            </Button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ) : null}
                                        {item.detalle.vinculos.internamientos.length > 0 ? (
                                            <div className="space-y-2">
                                                <p className="text-xs font-medium text-foreground">
                                                    {t('historial.vinculos_sec_hosp')}
                                                </p>
                                                <ul className="m-0 list-none space-y-2 p-0">
                                                    {item.detalle.vinculos.internamientos.map((h) => (
                                                        <li
                                                            key={h.id}
                                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/50 bg-background/80 px-2 py-1.5"
                                                        >
                                                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                                                <span className="truncate text-sm font-medium text-foreground">
                                                                    {h.titulo}
                                                                </span>
                                                                <Badge
                                                                    variant="outline"
                                                                    className="w-fit text-[0.65rem] font-normal"
                                                                >
                                                                    {t(`hospitalizacion:estado.${h.estado}`, {
                                                                        defaultValue: h.estado,
                                                                    })}
                                                                </Badge>
                                                            </div>
                                                            <Button type="button" variant="link" size="sm" className="h-7 shrink-0 px-1" asChild>
                                                                <a href={h.url}>{t('historial.vinculos_abrir')}</a>
                                                            </Button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ) : null}
                                    </div>
                                ) : null}
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                ) : null}

                {item.kind === 'aplicacion' && permisos.vacunas_ver && hayResumen ? (
                    <Collapsible open={resumenAbierto} onOpenChange={setResumenAbierto} className="mt-3">
                        <CollapsibleTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 gap-1.5 px-2 text-muted-foreground hover:text-foreground"
                            >
                                <ChevronDown
                                    className={cn('size-4 transition-transform', resumenAbierto && 'rotate-180')}
                                    strokeWidth={2.25}
                                />
                                {resumenAbierto ? t('historial.ocultar_resumen') : t('historial.ver_resumen')}
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div className="mt-2 space-y-2 rounded-lg border border-border/60 bg-muted/20 p-3 text-sm">
                                {item.detalle.producto_nombre ? (
                                    <p className="text-muted-foreground">
                                        <span className="font-medium text-foreground">{t('historial.det_producto')}</span>{' '}
                                        {item.detalle.producto_nombre}
                                        {item.detalle.producto_sku ? ` · SKU ${item.detalle.producto_sku}` : ''}
                                    </p>
                                ) : null}
                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    {item.detalle.lote ? (
                                        <span>
                                            <span className="font-medium text-foreground">{t('historial.det_lote')}</span>{' '}
                                            {item.detalle.lote}
                                        </span>
                                    ) : null}
                                    {item.detalle.numero_dosis != null ? (
                                        <span>
                                            <span className="font-medium text-foreground">{t('historial.det_dosis')}</span>{' '}
                                            {item.detalle.numero_dosis}
                                        </span>
                                    ) : null}
                                    {item.detalle.fecha_proxima_sugerida ? (
                                        <span>
                                            <span className="font-medium text-foreground">
                                                {t('historial.det_proxima')}
                                            </span>{' '}
                                            {item.detalle.fecha_proxima_sugerida}
                                        </span>
                                    ) : null}
                                </div>
                                {item.detalle.esquema_antigenos ? (
                                    <div className="space-y-0.5">
                                        <p className="text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">
                                            {t('historial.det_esquema')}
                                        </p>
                                        <p className="whitespace-pre-wrap leading-relaxed text-foreground">
                                            {item.detalle.esquema_antigenos}
                                        </p>
                                    </div>
                                ) : null}
                                {item.detalle.notas ? (
                                    <div className="space-y-0.5">
                                        <p className="text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">
                                            {t('historial.det_notas')}
                                        </p>
                                        <p className="whitespace-pre-wrap leading-relaxed text-foreground">
                                            {item.detalle.notas}
                                        </p>
                                    </div>
                                ) : null}
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                ) : null}

                <div className="mt-3 flex flex-wrap gap-2">
                    {item.kind === 'consulta' && permisos.consultas_ver ? (
                        <>
                            <Button type="button" size="sm" variant="default" className="gap-2" asChild>
                                <a href={item.historia_url}>
                                    <ExternalLink className="size-3.5" strokeWidth={2.25} />
                                    {t('historial.ver_consulta_completa')}
                                </a>
                            </Button>
                            <Button type="button" size="sm" variant="outline" className="gap-2" asChild>
                                <a
                                    href={item.pdf_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <FileDown className="size-3.5" strokeWidth={2.25} />
                                    {t('historial.pdf_registro')}
                                </a>
                            </Button>
                        </>
                    ) : null}
                    {item.kind === 'aplicacion' && permisos.vacunas_ver ? (
                        <>
                            <Button type="button" size="sm" variant="default" className="gap-2" asChild>
                                <Link href={item.vacunaciones_url} prefetch>
                                    <ExternalLink className="size-3.5" strokeWidth={2.25} />
                                    {t('historial.ver_aplicacion_completa')}
                                </Link>
                            </Button>
                            <Button type="button" size="sm" variant="outline" className="gap-2" asChild>
                                <a
                                    href={item.pdf_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <FileDown className="size-3.5" strokeWidth={2.25} />
                                    {t('historial.pdf_registro')}
                                </a>
                            </Button>
                        </>
                    ) : null}
                </div>
            </div>
        </li>
    );
}

export default function PacienteShow({ paciente, timeline, links, permisos }: Props) {
    const { t } = useTranslation(['pacientes', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;

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

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={paciente.nombre}
                    description={t('historial.description', { propietario: propietarioNombre })}
                    action={
                        <Button type="button" variant="outline" size="sm" className="gap-2" asChild>
                            <Link href={clinica.pacientes.index().url} prefetch>
                                <ArrowLeft className="size-4" strokeWidth={2.25} />
                                {t('historial.back_list')}
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {permisos.consultas_crear ? (
                        <Button type="button" size="sm" className="gap-2" asChild>
                            <a href={links.nueva_consulta}>
                                <Plus className="size-4" strokeWidth={2.25} />
                                {t('historial.action_nueva_consulta')}
                            </a>
                        </Button>
                    ) : null}
                    {permisos.vacunas_crear ? (
                        <Button type="button" size="sm" variant="secondary" className="gap-2" asChild>
                            <a href={links.nueva_aplicacion}>
                                <Syringe className="size-4" strokeWidth={2.25} />
                                {t('historial.action_nueva_aplicacion')}
                            </a>
                        </Button>
                    ) : null}
                    {links.historial_pdf && timeline.length > 0 ? (
                        <Button type="button" size="sm" variant="outline" className="gap-2" asChild>
                            <a
                                href={links.historial_pdf}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <FileDown className="size-4" strokeWidth={2.25} />
                                {t('historial.action_historial_pdf')}
                            </a>
                        </Button>
                    ) : null}
                </div>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Calendar className="size-4 text-primary" strokeWidth={2.25} />
                            {t('historial.timeline_title')}
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">{t('historial.timeline_hint')}</p>
                    </CardHeader>
                    <CardContent>
                        {!permisos.consultas_ver && !permisos.vacunas_ver ? (
                            <p className="text-sm text-muted-foreground">{t('historial.sin_permisos')}</p>
                        ) : timeline.length === 0 ? (
                            <p className="text-sm text-muted-foreground">{t('historial.timeline_empty')}</p>
                        ) : (
                            <ul className="relative m-0 list-none p-0">
                                {timeline.map((item) => (
                                    <TimelineRow
                                        key={`${item.kind}-${item.id}`}
                                        item={item}
                                        appLocale={String(appLocale ?? 'es')}
                                        appTz={appTz}
                                        permisos={permisos}
                                    />
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Can permission="propietarios.view">
                    <p className="text-xs text-muted-foreground">
                        <Link
                            href={clinica.propietarios.show.url({ propietario: paciente.propietario_id })}
                            className="font-medium text-primary underline-offset-4 hover:underline"
                        >
                            {t('historial.ver_titular')}
                        </Link>
                    </p>
                </Can>
            </div>
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
