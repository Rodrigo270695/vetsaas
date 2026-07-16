import { Link } from '@inertiajs/react';
import {
    Activity,
    ChevronDown,
    ClipboardList,
    ExternalLink,
    FileDown,
    Heart,
    Stethoscope,
    Syringe,
    Thermometer,
    Wind,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { formatAtendidoInAppTimezone } from '../../historias-clinicas/format-atendido';
import type {
    TimelineAplicacionDetalle,
    TimelineConsultaDetalle,
    TimelineConsultaVinculos,
    TimelineItem,
} from '../show';

type TimelineRowProps = {
    item: TimelineItem;
    appLocale: string;
    appTz: string | undefined;
    permisos: {
        consultas_ver: boolean;
        vacunas_ver: boolean;
    };
    isLast: boolean;
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

function SoapBlock({ label, text }: { label: string; text: string | null }) {
    if (!text) {
        return null;
    }

    return (
        <div className="rounded-lg border border-border/50 bg-background/60 p-2.5">
            <p className="text-[0.6rem] font-bold uppercase tracking-wider text-muted-foreground">{label}</p>
            <p className="mt-1 whitespace-pre-wrap text-xs leading-relaxed text-foreground">{text}</p>
        </div>
    );
}

function VitalChip({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: typeof Activity;
    label: string;
    value: string;
    tone: 'sky' | 'rose' | 'violet' | 'teal';
}) {
    const tones = {
        sky: 'border-sky-500/20 bg-sky-500/10 text-sky-900 dark:text-sky-100',
        rose: 'border-rose-500/20 bg-rose-500/10 text-rose-900 dark:text-rose-100',
        violet: 'border-violet-500/20 bg-violet-500/10 text-violet-900 dark:text-violet-100',
        teal: 'border-teal-500/20 bg-teal-500/10 text-teal-900 dark:text-teal-100',
    };

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[0.65rem] font-medium',
                tones[tone],
            )}
        >
            <Icon className="size-3 opacity-80" />
            <span className="opacity-70">{label}</span>
            <span>{value}</span>
        </span>
    );
}

function itemTheme(item: TimelineItem) {
    if (item.kind === 'consulta') {
        return {
            stripe: 'bg-sky-500',
            dot: 'border-sky-500 bg-sky-500 text-white shadow-sky-500/30',
            iconBg: 'bg-sky-500/15',
            iconText: 'text-sky-600 dark:text-sky-400',
            cardHover: 'hover:border-sky-500/35 hover:shadow-sky-500/5',
            Icon: ClipboardList,
        };
    }

    const cat = (item.categoria ?? 'vacuna').toLowerCase();
    if (cat === 'desparasitacion') {
        return {
            stripe: 'bg-amber-500',
            dot: 'border-amber-500 bg-amber-500 text-white shadow-amber-500/30',
            iconBg: 'bg-amber-500/15',
            iconText: 'text-amber-700 dark:text-amber-300',
            cardHover: 'hover:border-amber-500/35 hover:shadow-amber-500/5',
            Icon: Syringe,
        };
    }
    if (cat === 'otro') {
        return {
            stripe: 'bg-violet-500',
            dot: 'border-violet-500 bg-violet-500 text-white shadow-violet-500/30',
            iconBg: 'bg-violet-500/15',
            iconText: 'text-violet-600 dark:text-violet-400',
            cardHover: 'hover:border-violet-500/35 hover:shadow-violet-500/5',
            Icon: Syringe,
        };
    }

    return {
        stripe: 'bg-emerald-500',
        dot: 'border-emerald-500 bg-emerald-500 text-white shadow-emerald-500/30',
        iconBg: 'bg-emerald-500/15',
        iconText: 'text-emerald-600 dark:text-emerald-400',
        cardHover: 'hover:border-emerald-500/35 hover:shadow-emerald-500/5',
        Icon: Syringe,
    };
}

export function PacienteTimelineRow({ item, appLocale, appTz, permisos, isLast }: TimelineRowProps) {
    const { t } = useTranslation(['pacientes', 'recetas', 'laboratorio', 'cirugia', 'common']);
    const [resumenAbierto, setResumenAbierto] = useState(false);
    const theme = itemTheme(item);

    const fechaFmt = formatAtendidoInAppTimezone(item.ocurrido_at, String(appLocale ?? 'es'), appTz);

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

    const vinculosCount =
        item.kind === 'consulta'
            ? item.detalle.vinculos.recetas.length +
              item.detalle.vinculos.laboratorio.length +
              item.detalle.vinculos.cirugias.length +
              item.detalle.vinculos.internamientos.length
            : 0;

    return (
        <li className="relative flex gap-3 pb-5 last:pb-0 sm:gap-4">
            {!isLast ? (
                <div
                    className="absolute left-[15px] top-9 hidden h-[calc(100%-1.25rem)] w-0.5 bg-gradient-to-b from-border via-primary/20 to-transparent sm:block"
                    aria-hidden
                />
            ) : null}

            <div
                className={cn(
                    'relative z-[1] hidden size-8 shrink-0 items-center justify-center rounded-full border-2 shadow-md sm:flex',
                    theme.dot,
                )}
            >
                <theme.Icon className="size-3.5" strokeWidth={2.5} />
            </div>

            <article
                className={cn(
                    'relative min-w-0 flex-1 overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm transition-all duration-200',
                    'ring-1 ring-black/[0.03] dark:ring-white/5',
                    theme.cardHover,
                    'hover:shadow-md',
                )}
            >
                <div className={cn('absolute inset-y-0 left-0 w-1', theme.stripe)} aria-hidden />

                <div className="p-3 pl-4 sm:p-3.5 sm:pl-5">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                        <div className="min-w-0 flex-1 space-y-1.5">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <span
                                    className={cn(
                                        'flex size-7 shrink-0 items-center justify-center rounded-lg sm:hidden',
                                        theme.iconBg,
                                    )}
                                >
                                    <theme.Icon className={cn('size-3.5', theme.iconText)} strokeWidth={2.25} />
                                </span>
                                <Badge
                                    variant="outline"
                                    className={cn(
                                        'border-0 text-[0.65rem] font-semibold',
                                        item.kind === 'consulta'
                                            ? 'bg-sky-500/12 text-sky-800 dark:text-sky-200'
                                            : 'bg-emerald-500/12 text-emerald-800 dark:text-emerald-200',
                                    )}
                                >
                                    {item.kind === 'consulta'
                                        ? t('historial.badge_consulta')
                                        : t('historial.badge_aplicacion')}
                                </Badge>
                                {item.kind === 'consulta' ? (
                                    item.cerrada ? (
                                        <Badge className="border-0 bg-amber-500/15 text-[0.65rem] font-medium text-amber-950 dark:text-amber-100">
                                            {t('historial.badge_cerrada')}
                                        </Badge>
                                    ) : (
                                        <Badge className="border-0 bg-emerald-500/15 text-[0.65rem] font-medium text-emerald-900 dark:text-emerald-100">
                                            {t('historial.badge_abierta')}
                                        </Badge>
                                    )
                                ) : (
                                    <Badge variant="secondary" className="text-[0.65rem] font-medium">
                                        {categoriaEtiqueta(item.categoria)}
                                    </Badge>
                                )}
                                {vinculosCount > 0 ? (
                                    <Badge variant="outline" className="text-[0.6rem] font-normal text-muted-foreground">
                                        +{vinculosCount} {t('historial.vinculos_corto')}
                                    </Badge>
                                ) : null}
                            </div>

                            <h3 className="text-sm font-semibold leading-snug text-foreground sm:text-[0.95rem]">
                                {item.titulo}
                            </h3>

                            <p className="text-xs text-muted-foreground">
                                {item.veterinario ? (
                                    <span className="inline-flex items-center gap-1">
                                        <Stethoscope className="size-3 text-primary/70" />
                                        {item.veterinario}
                                        <span className="opacity-40">·</span>
                                    </span>
                                ) : null}
                                <time dateTime={item.ocurrido_at}>{fechaFmt}</time>
                            </p>

                            {item.kind === 'consulta' &&
                            (item.detalle.peso_kg ||
                                item.detalle.temperatura_c ||
                                item.detalle.fc_lpm != null ||
                                item.detalle.fr_rpm != null) ? (
                                <div className="flex flex-wrap gap-1 pt-0.5">
                                    {item.detalle.peso_kg ? (
                                        <VitalChip
                                            icon={Activity}
                                            label={t('historial.det_peso')}
                                            value={`${item.detalle.peso_kg} kg`}
                                            tone="sky"
                                        />
                                    ) : null}
                                    {item.detalle.temperatura_c ? (
                                        <VitalChip
                                            icon={Thermometer}
                                            label={t('historial.det_temp')}
                                            value={`${item.detalle.temperatura_c} °C`}
                                            tone="rose"
                                        />
                                    ) : null}
                                    {item.detalle.fc_lpm != null ? (
                                        <VitalChip
                                            icon={Heart}
                                            label={t('historial.det_fc')}
                                            value={`${item.detalle.fc_lpm}`}
                                            tone="violet"
                                        />
                                    ) : null}
                                    {item.detalle.fr_rpm != null ? (
                                        <VitalChip
                                            icon={Wind}
                                            label={t('historial.det_fr')}
                                            value={`${item.detalle.fr_rpm}`}
                                            tone="teal"
                                        />
                                    ) : null}
                                </div>
                            ) : null}
                        </div>

                        <div className="flex shrink-0 flex-wrap gap-1.5 sm:flex-col sm:items-stretch">
                            {item.kind === 'consulta' && permisos.consultas_ver ? (
                                <>
                                    <Button type="button" size="sm" className="h-8 gap-1.5 px-2.5 text-xs" asChild>
                                        <a href={item.historia_url}>
                                            <ExternalLink className="size-3.5" strokeWidth={2.25} />
                                            <span className="hidden sm:inline">{t('historial.ver_consulta_corta')}</span>
                                            <span className="sm:hidden">{t('historial.ver_consulta_completa')}</span>
                                        </a>
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="h-8 gap-1.5 px-2.5 text-xs"
                                        asChild
                                    >
                                        <a href={item.pdf_url} target="_blank" rel="noopener noreferrer">
                                            <FileDown className="size-3.5" strokeWidth={2.25} />
                                            PDF
                                        </a>
                                    </Button>
                                </>
                            ) : null}
                            {item.kind === 'aplicacion' && permisos.vacunas_ver ? (
                                <>
                                    <Button type="button" size="sm" className="h-8 gap-1.5 px-2.5 text-xs" asChild>
                                        <Link href={item.vacunaciones_url} prefetch>
                                            <ExternalLink className="size-3.5" strokeWidth={2.25} />
                                            <span className="hidden sm:inline">{t('historial.ver_aplicacion_corta')}</span>
                                            <span className="sm:hidden">{t('historial.ver_aplicacion_completa')}</span>
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="h-8 gap-1.5 px-2.5 text-xs"
                                        asChild
                                    >
                                        <a href={item.pdf_url} target="_blank" rel="noopener noreferrer">
                                            <FileDown className="size-3.5" strokeWidth={2.25} />
                                            PDF
                                        </a>
                                    </Button>
                                </>
                            ) : null}
                        </div>
                    </div>

                    {item.kind === 'consulta' && hayResumen ? (
                        <Collapsible open={resumenAbierto} onOpenChange={setResumenAbierto} className="mt-2">
                            <CollapsibleTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 gap-1 px-1.5 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <ChevronDown
                                        className={cn('size-3.5 transition-transform', resumenAbierto && 'rotate-180')}
                                    />
                                    {resumenAbierto ? t('historial.ocultar_resumen') : t('historial.ver_resumen')}
                                </Button>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <div className="mt-2 space-y-2.5 rounded-lg border border-border/60 bg-muted/25 p-2.5">
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <SoapBlock
                                            label={t('historial.det_subjetivo')}
                                            text={item.detalle.subjetivo}
                                        />
                                        <SoapBlock
                                            label={t('historial.det_objetivo')}
                                            text={item.detalle.objetivo}
                                        />
                                        <SoapBlock label={t('historial.det_analisis')} text={item.detalle.analisis} />
                                        <SoapBlock label={t('historial.det_plan_soap')} text={item.detalle.plan} />
                                    </div>
                                    {vinculosConsultaTieneContenido(item.detalle.vinculos) ? (
                                        <VinculosBlock vinculos={item.detalle.vinculos} t={t} />
                                    ) : null}
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    ) : null}

                    {item.kind === 'aplicacion' && permisos.vacunas_ver && hayResumen ? (
                        <Collapsible open={resumenAbierto} onOpenChange={setResumenAbierto} className="mt-2">
                            <CollapsibleTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 gap-1 px-1.5 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <ChevronDown
                                        className={cn('size-3.5 transition-transform', resumenAbierto && 'rotate-180')}
                                    />
                                    {resumenAbierto ? t('historial.ocultar_resumen') : t('historial.ver_resumen')}
                                </Button>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <div className="mt-2 space-y-2 rounded-lg border border-border/60 bg-muted/25 p-2.5 text-xs">
                                    {item.detalle.producto_nombre ? (
                                        <p>
                                            <span className="font-semibold text-foreground">
                                                {t('historial.det_producto')}:
                                            </span>{' '}
                                            {item.detalle.producto_nombre}
                                            {item.detalle.producto_sku ? ` · ${item.detalle.producto_sku}` : ''}
                                        </p>
                                    ) : null}
                                    <div className="flex flex-wrap gap-x-3 gap-y-1 text-muted-foreground">
                                        {item.detalle.lote ? (
                                            <span>
                                                <span className="font-medium text-foreground">
                                                    {t('historial.det_lote')}:
                                                </span>{' '}
                                                {item.detalle.lote}
                                            </span>
                                        ) : null}
                                        {item.detalle.numero_dosis != null ? (
                                            <span>
                                                <span className="font-medium text-foreground">
                                                    {t('historial.det_dosis')}:
                                                </span>{' '}
                                                {item.detalle.numero_dosis}
                                            </span>
                                        ) : null}
                                        {item.detalle.fecha_proxima_sugerida ? (
                                            <span>
                                                <span className="font-medium text-foreground">
                                                    {t('historial.det_proxima')}:
                                                </span>{' '}
                                                {item.detalle.fecha_proxima_sugerida}
                                            </span>
                                        ) : null}
                                    </div>
                                    {item.detalle.esquema_antigenos ? (
                                        <div>
                                            <p className="text-[0.6rem] font-bold uppercase tracking-wider text-muted-foreground">
                                                {t('historial.det_esquema')}
                                            </p>
                                            <p className="mt-0.5 whitespace-pre-wrap leading-relaxed">
                                                {item.detalle.esquema_antigenos}
                                            </p>
                                        </div>
                                    ) : null}
                                    {item.detalle.notas ? (
                                        <div>
                                            <p className="text-[0.6rem] font-bold uppercase tracking-wider text-muted-foreground">
                                                {t('historial.det_notas')}
                                            </p>
                                            <p className="mt-0.5 whitespace-pre-wrap leading-relaxed">
                                                {item.detalle.notas}
                                            </p>
                                        </div>
                                    ) : null}
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    ) : null}
                </div>
            </article>
        </li>
    );
}

function VinculosBlock({
    vinculos,
    t,
}: {
    vinculos: TimelineConsultaVinculos;
    t: (k: string, o?: Record<string, string | number>) => string;
}) {
    return (
        <div className="space-y-3 border-t border-border/50 pt-2.5">
            <p className="text-[0.6rem] font-bold uppercase tracking-wider text-muted-foreground">
                {t('historial.vinculos_lead')}
            </p>
            {vinculos.recetas.length > 0 ? (
                <VinculoList
                    title={t('historial.vinculos_sec_recetas')}
                    items={vinculos.recetas.map((r) => ({
                        id: r.id,
                        badge: t(`recetas:estado.${r.estado}`, { defaultValue: r.estado }),
                        meta: t('historial.vinculos_meds', { count: r.lineas_count }),
                        url: r.url,
                    }))}
                    t={t}
                />
            ) : null}
            {vinculos.laboratorio.length > 0 ? (
                <VinculoList
                    title={t('historial.vinculos_sec_lab')}
                    items={vinculos.laboratorio.map((p) => ({
                        id: p.id,
                        badge: t(`laboratorio:estado.${p.estado}`, { defaultValue: p.estado }),
                        meta: t('historial.vinculos_exam', { count: p.lineas_count }),
                        url: p.url,
                    }))}
                    t={t}
                />
            ) : null}
            {vinculos.cirugias.length > 0 ? (
                <VinculoList
                    title={t('historial.vinculos_sec_ciru')}
                    items={vinculos.cirugias.map((c) => ({
                        id: c.id,
                        badge: t(`cirugia:estado.${c.estado}`, { defaultValue: c.estado }),
                        meta: c.titulo,
                        url: c.url,
                    }))}
                    t={t}
                />
            ) : null}
            {vinculos.internamientos.length > 0 ? (
                <VinculoList
                    title={t('historial.vinculos_sec_hosp')}
                    items={vinculos.internamientos.map((h) => ({
                        id: h.id,
                        badge: t(`hospitalizacion:estado.${h.estado}`, { defaultValue: h.estado }),
                        meta: h.titulo,
                        url: h.url,
                    }))}
                    t={t}
                />
            ) : null}
        </div>
    );
}

function VinculoList({
    title,
    items,
    t,
}: {
    title: string;
    items: { id: string; badge: string; meta: string; url: string }[];
    t: (k: string) => string;
}) {
    return (
        <div className="space-y-1.5">
            <p className="text-xs font-semibold text-foreground">{title}</p>
            <ul className="m-0 list-none space-y-1 p-0">
                {items.map((item) => (
                    <li
                        key={item.id}
                        className="flex items-center justify-between gap-2 rounded-md border border-border/40 bg-background/70 px-2 py-1.5"
                    >
                        <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                            <Badge variant="outline" className="text-[0.6rem] font-normal">
                                {item.badge}
                            </Badge>
                            <span className="truncate text-[0.7rem] text-muted-foreground">{item.meta}</span>
                        </div>
                        <Button type="button" variant="link" size="sm" className="h-6 shrink-0 px-1 text-xs" asChild>
                            <a href={item.url}>{t('historial.vinculos_abrir')}</a>
                        </Button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
