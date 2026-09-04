import { Link } from '@inertiajs/react';
import {
    Activity,
    CalendarDays,
    ChevronDown,
    Clock,
    ClipboardList,
    ExternalLink,
    FileDown,
    FilePenLine,
    FlaskConical,
    Heart,
    Loader2,
    MessageCircle,
    MoreHorizontal,
    Receipt,
    Stethoscope,
    Syringe,
    Thermometer,
    Trash2,
    Wind,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { HistorialArchivoPreview } from './historial-archivo-preview';
import {
    dateKeyInAppTimezone,
    formatAtendidoInAppTimezone,
    formatFullDateLabelInAppTimezone,
    formatTimeOnlyInAppTimezone,
} from '../../historias-clinicas/format-atendido';
import type {
    TimelineAplicacionDetalle,
    TimelineCobro,
    TimelineCobroVenta,
    TimelineConsultaDetalle,
    TimelineConsultaVinculos,
    TimelineItem,
    TimelineLabLinea,
} from '../show';

type TimelineRowProps = {
    item: TimelineItem;
    /** Posición en la línea de tiempo (0 = más reciente). Anima la entrada y resalta lo último. */
    index?: number;
    /** true si este es el primer ítem de un nuevo día: dibuja el encabezado de fecha. */
    showDateHeader?: boolean;
    appTz: string | undefined;
    permisos: {
        consultas_ver: boolean;
        vacunas_ver: boolean;
        laboratorio_crear?: boolean;
        laboratorio_eliminar?: boolean;
        consultas_eliminar?: boolean;
    };
    isLast: boolean;
    consultaOpeningId?: string | null;
    onOpenConsulta?: (item: Extract<TimelineItem, { kind: 'consulta' }>) => void;
    onOpenAplicacion?: (item: Extract<TimelineItem, { kind: 'aplicacion' }>) => void;
    onShareConsulta?: (item: Extract<TimelineItem, { kind: 'consulta' }>) => void;
    onUploadLaboratorio?: (consultaId: string) => void;
    onDeleteConsulta?: (item: Extract<TimelineItem, { kind: 'consulta' }>) => void;
    onSendAutorizacion?: (consultaId: string) => void;
    variant?: 'admin' | 'public';
};

function vinculosConsultaTieneContenido(v: TimelineConsultaVinculos): boolean {
    return (
        v.recetas.length > 0 ||
        v.laboratorio.length > 0 ||
        v.cirugias.length > 0 ||
        v.internamientos.length > 0 ||
        (v.documentos_autorizacion?.length ?? 0) > 0
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
                d.plan ||
                d.motivo ||
                d.anotaciones ||
                d.medico_tratante ||
                (d.examenes && d.examenes.length > 0),
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

function comprobanteHref(venta: TimelineCobroVenta): string | null {
    return venta.fel_pdf_url || venta.ticket_url || venta.show_url;
}

function comprobanteLabelKey(tipo: number): string {
    if (tipo === 1) {
        return 'historial.comprobante_factura';
    }
    if (tipo === 2) {
        return 'historial.comprobante_boleta';
    }
    return 'historial.comprobante_ticket';
}

/**
 * Chip compacto de pago: si hay una sola venta cobrada, es un enlace directo
 * al comprobante; con varias, despliega un menú. Vive separado de los
 * badges de estado clínico para no competir visualmente con ellos.
 */
function CobroPill({
    cobro,
    isPublic,
}: {
    cobro: TimelineCobro | null | undefined;
    isPublic: boolean;
}) {
    const { t } = useTranslation('pacientes');
    if (isPublic || !cobro?.ventas?.length) {
        return null;
    }

    const ventasConLink = cobro.ventas.filter((v) => comprobanteHref(v) !== null);
    if (ventasConLink.length === 0) {
        return null;
    }

    const pillClass =
        'inline-flex h-7 items-center gap-1.5 rounded-full border border-violet-500/25 bg-gradient-to-r from-violet-500/10 to-violet-500/5 px-2.5 text-[0.7rem] font-medium text-violet-700 transition-all duration-200 hover:scale-[1.03] hover:border-violet-500/40 hover:from-violet-500/15 hover:to-violet-500/10 hover:shadow-sm dark:border-violet-400/25 dark:from-violet-400/12 dark:to-violet-400/5 dark:text-violet-300';

    if (ventasConLink.length === 1) {
        const venta = ventasConLink[0];
        const href = comprobanteHref(venta) as string;
        const label = t(comprobanteLabelKey(venta.tipo_comprobante_sunat));
        const numero = venta.fel_numero || venta.numero;

        return (
            <a href={href} target="_blank" rel="noopener noreferrer" className={pillClass}>
                <Receipt className="size-3" strokeWidth={2.25} />
                {label}
                <span className="opacity-60">·</span>
                <span className="font-mono text-[0.65rem]">{numero}</span>
            </a>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button type="button" className={cn(pillClass, 'cursor-pointer')}>
                    <Receipt className="size-3" strokeWidth={2.25} />
                    {t('historial.badge_cobrado')}
                    <span className="opacity-60">({ventasConLink.length})</span>
                    <ChevronDown className="size-3 opacity-60" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                {ventasConLink.map((venta) => (
                    <DropdownMenuItem key={venta.id} asChild className="cursor-pointer gap-2">
                        <a href={comprobanteHref(venta) as string} target="_blank" rel="noopener noreferrer">
                            <Receipt className="size-3.5 shrink-0 opacity-70" />
                            <span className="truncate">
                                {t(comprobanteLabelKey(venta.tipo_comprobante_sunat))} ·{' '}
                                {venta.fel_numero || venta.numero}
                            </span>
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
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

/**
 * Encabezado de grupo por día: convierte la fecha en el elemento más prominente
 * del bloque (en vez de un texto gris diluido dentro de cada tarjeta). "Hoy" se
 * resalta con el color primario para ubicar de inmediato la visita más reciente.
 */
function TimelineDateHeader({ label, isToday }: { label: string; isToday: boolean }) {
    return (
        <div className="mb-3 flex items-center gap-3 pl-0 sm:pl-0.5">
            <span
                className={cn(
                    'inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1 text-[0.82rem] font-bold capitalize tracking-tight',
                    isToday
                        ? 'bg-primary text-primary-foreground shadow-sm shadow-primary/25'
                        : 'bg-muted text-foreground/90',
                )}
            >
                <CalendarDays className="size-3.5 opacity-80" strokeWidth={2.25} />
                {label}
            </span>
            <span
                className={cn(
                    'h-px flex-1 bg-gradient-to-r to-transparent',
                    isToday ? 'from-primary/40' : 'from-border',
                )}
                aria-hidden
            />
        </div>
    );
}

function itemTheme(item: TimelineItem) {
    if (item.kind === 'consulta') {
        return {
            stripe: 'bg-gradient-to-b from-sky-400 to-sky-600',
            dot: 'border-sky-400/70 bg-gradient-to-br from-sky-400 to-sky-600 text-white shadow-sky-500/25',
            dotGlow: 'group-hover:shadow-[0_0_0_5px_rgba(14,165,233,0.16)]',
            ringPulse: 'bg-sky-500/50',
            iconBg: 'bg-gradient-to-br from-sky-500/20 to-sky-500/5',
            iconText: 'text-sky-600 dark:text-sky-400',
            cardHover: 'hover:border-sky-500/35 hover:shadow-sky-500/10',
            Icon: ClipboardList,
        };
    }

    const cat = (item.categoria ?? 'vacuna').toLowerCase();

    if (cat === 'desparasitacion') {
        return {
            stripe: 'bg-gradient-to-b from-amber-400 to-amber-600',
            dot: 'border-amber-400/70 bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-amber-500/25',
            dotGlow: 'group-hover:shadow-[0_0_0_5px_rgba(245,158,11,0.16)]',
            ringPulse: 'bg-amber-500/50',
            iconBg: 'bg-gradient-to-br from-amber-500/20 to-amber-500/5',
            iconText: 'text-amber-700 dark:text-amber-300',
            cardHover: 'hover:border-amber-500/35 hover:shadow-amber-500/10',
            Icon: Syringe,
        };
    }

    if (cat === 'otro') {
        return {
            stripe: 'bg-gradient-to-b from-violet-400 to-violet-600',
            dot: 'border-violet-400/70 bg-gradient-to-br from-violet-400 to-violet-600 text-white shadow-violet-500/25',
            dotGlow: 'group-hover:shadow-[0_0_0_5px_rgba(139,92,246,0.16)]',
            ringPulse: 'bg-violet-500/50',
            iconBg: 'bg-gradient-to-br from-violet-500/20 to-violet-500/5',
            iconText: 'text-violet-600 dark:text-violet-400',
            cardHover: 'hover:border-violet-500/35 hover:shadow-violet-500/10',
            Icon: Syringe,
        };
    }

    return {
        stripe: 'bg-gradient-to-b from-emerald-400 to-emerald-600',
        dot: 'border-emerald-400/70 bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-emerald-500/25',
        dotGlow: 'group-hover:shadow-[0_0_0_5px_rgba(16,185,129,0.16)]',
        ringPulse: 'bg-emerald-500/50',
        iconBg: 'bg-gradient-to-br from-emerald-500/20 to-emerald-500/5',
        iconText: 'text-emerald-600 dark:text-emerald-400',
        cardHover: 'hover:border-emerald-500/35 hover:shadow-emerald-500/10',
        Icon: Syringe,
    };
}

export function PacienteTimelineRow({
    item,
    index = 0,
    showDateHeader = false,
    appTz,
    permisos,
    isLast,
    consultaOpeningId = null,
    onOpenConsulta,
    onOpenAplicacion,
    onShareConsulta,
    onUploadLaboratorio,
    onDeleteConsulta,
    onSendAutorizacion,
    variant = 'admin',
}: TimelineRowProps) {
    const { t, i18n } = useTranslation(['pacientes', 'recetas', 'laboratorio', 'cirugia', 'common']);
    const [resumenAbierto, setResumenAbierto] = useState(false);
    const theme = itemTheme(item);
    const isPublic = variant === 'public';
    const isFirst = index === 0;
    const enterDelayMs = Math.min(index, 8) * 45;
    const tz = appTz ?? 'UTC';
    // El idioma de la fecha sigue al idioma activo de la UI (i18next), no al locale
    // del servidor: así el timeline nunca "cambia de idioma" a mitad de pantalla.
    const localeCode = i18n.language?.toLowerCase().startsWith('en') ? 'en' : 'es';

    const horaFmt = formatTimeOnlyInAppTimezone(item.ocurrido_at, tz);
    const fechaCompletaTitle = formatAtendidoInAppTimezone(item.ocurrido_at, localeCode, tz);

    const dateHeaderLabel = showDateHeader
        ? (() => {
              const itemKey = dateKeyInAppTimezone(item.ocurrido_at, tz);
              const todayKey = dateKeyInAppTimezone(new Date().toISOString(), tz);
              const yesterdayKey = dateKeyInAppTimezone(
                  new Date(Date.now() - 86_400_000).toISOString(),
                  tz,
              );

              if (itemKey === todayKey) {
                  return t('historial.fecha_hoy');
              }
              if (itemKey === yesterdayKey) {
                  return t('historial.fecha_ayer');
              }
              const label = formatFullDateLabelInAppTimezone(item.ocurrido_at, localeCode, tz);
              return label.charAt(0).toUpperCase() + label.slice(1);
          })()
        : '';
    const isHeaderToday =
        showDateHeader && dateKeyInAppTimezone(item.ocurrido_at, tz) === dateKeyInAppTimezone(new Date().toISOString(), tz);

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
              item.detalle.vinculos.internamientos.length +
              (item.detalle.vinculos.documentos_autorizacion?.length ?? 0)
            : 0;

    const archivosConsulta: TimelineLabLinea[] =
        item.kind === 'consulta'
            ? [
                  ...item.detalle.vinculos.laboratorio.flatMap((p) =>
                      p.lineas.filter((l) => Boolean(l.resultado_archivo_url)),
                  ),
                  ...(item.detalle.vinculos.documentos_autorizacion ?? []).filter((d) =>
                      Boolean(d.resultado_archivo_url),
                  ),
              ]
            : [];

    return (
        <li className="relative pb-5 last:pb-0">
            {showDateHeader ? (
                <TimelineDateHeader label={dateHeaderLabel} isToday={isHeaderToday} />
            ) : null}

            <div
                className="group relative flex gap-3 motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-2 motion-safe:fill-mode-both sm:gap-4"
                style={{ animationDelay: `${enterDelayMs}ms`, animationDuration: '420ms' }}
            >
                {!isLast ? (
                    <div
                        className="absolute left-[15px] top-9 bottom-0 hidden w-px bg-gradient-to-b from-border via-primary/25 to-transparent sm:block"
                        aria-hidden
                    />
                ) : null}

                <div className="relative z-[1] hidden size-8 shrink-0 items-center justify-center sm:flex">
                    {isFirst ? (
                        <span
                            className={cn(
                                'absolute inset-0 rounded-full motion-safe:animate-ping motion-safe:[animation-duration:2.2s]',
                                theme.ringPulse,
                            )}
                            aria-hidden
                        />
                    ) : null}
                    <div
                        className={cn(
                            'relative flex size-8 items-center justify-center rounded-full border-2 shadow-md transition-all duration-300 group-hover:scale-110',
                            theme.dot,
                            theme.dotGlow,
                        )}
                    >
                        <theme.Icon className="size-3.5" strokeWidth={2.5} />
                    </div>
                </div>

                <article
                    className={cn(
                        'relative min-w-0 flex-1 overflow-hidden rounded-xl border border-border/60 bg-card shadow-xs transition-all duration-300 ease-out',
                        'ring-1 ring-black/[0.02] dark:ring-white/[0.03]',
                        theme.cardHover,
                        'hover:-translate-y-0.5 hover:shadow-lg',
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

                            <h3
                                className={cn(
                                    'text-sm font-semibold leading-snug sm:text-[0.95rem]',
                                    item.titulo === '—'
                                        ? 'italic text-muted-foreground'
                                        : 'text-foreground',
                                )}
                            >
                                {item.titulo === '—' ? t('historial.sin_motivo') : item.titulo}
                            </h3>

                            <div className="flex flex-wrap items-center gap-2">
                                <time
                                    dateTime={item.ocurrido_at}
                                    title={fechaCompletaTitle}
                                    className="inline-flex items-center gap-1 rounded-md bg-foreground/[0.06] px-1.5 py-0.5 font-mono text-[0.72rem] font-bold tabular-nums text-foreground/90 dark:bg-foreground/10"
                                >
                                    <Clock className="size-3 opacity-70" strokeWidth={2.5} />
                                    {horaFmt}
                                </time>
                                {item.veterinario ? (
                                    <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                        <Stethoscope className="size-3 text-primary/70" />
                                        {item.veterinario}
                                    </span>
                                ) : null}
                            </div>

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

                            {archivosConsulta.length > 0 ? (
                                <div className="flex flex-wrap gap-1.5 pt-1">
                                    {archivosConsulta.map((archivo) => (
                                        <HistorialArchivoPreview
                                            key={archivo.id}
                                            archivo={archivo}
                                            canDelete={
                                                variant !== 'public' &&
                                                Boolean(permisos.laboratorio_eliminar)
                                            }
                                        />
                                    ))}
                                </div>
                            ) : null}
                        </div>

                        <div className="flex shrink-0 flex-col items-end gap-1.5">
                            <CobroPill cobro={item.cobro} isPublic={isPublic} />

                            <div className="flex flex-wrap items-center justify-end gap-1.5">
                                {item.kind === 'consulta' && permisos.consultas_ver ? (
                                    <>
                                        {!isPublic && (onOpenConsulta || item.historia_url) ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                className="group/btn h-8 gap-1.5 px-2.5 text-xs"
                                                disabled={consultaOpeningId === item.id}
                                                onClick={() => {
                                                    if (onOpenConsulta) {
                                                        onOpenConsulta(item);
                                                        return;
                                                    }
                                                    if (item.historia_url) {
                                                        window.location.href = item.historia_url;
                                                    }
                                                }}
                                            >
                                                {consultaOpeningId === item.id ? (
                                                    <Loader2 className="size-3.5 animate-spin" strokeWidth={2.25} />
                                                ) : (
                                                    <ExternalLink
                                                        className="size-3.5 transition-transform duration-200 group-hover/btn:translate-x-0.5"
                                                        strokeWidth={2.25}
                                                    />
                                                )}
                                                <span className="hidden sm:inline">{t('historial.ver_consulta_corta')}</span>
                                                <span className="sm:hidden">{t('historial.ver_consulta_completa')}</span>
                                            </Button>
                                        ) : null}
                                        {item.pdf_url ? (
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
                                        ) : null}
                                        {!isPublic &&
                                        ((onShareConsulta && item.whatsapp_url) ||
                                            onUploadLaboratorio ||
                                            onSendAutorizacion ||
                                            (permisos.consultas_eliminar && onDeleteConsulta)) ? (
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-8 shrink-0 cursor-pointer text-muted-foreground"
                                                    >
                                                        <MoreHorizontal className="size-4" strokeWidth={2.25} />
                                                        <span className="sr-only">
                                                            {t('common:actions.more_options')}
                                                        </span>
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-52">
                                                    {onShareConsulta && item.whatsapp_url ? (
                                                        <DropdownMenuItem
                                                            onSelect={() => onShareConsulta(item)}
                                                            className="cursor-pointer gap-2 text-emerald-700 dark:text-emerald-300"
                                                        >
                                                            <MessageCircle className="size-3.5" strokeWidth={2.25} />
                                                            {t('historial.action_whatsapp')}
                                                        </DropdownMenuItem>
                                                    ) : null}
                                                    {onUploadLaboratorio ? (
                                                        <DropdownMenuItem
                                                            onSelect={() => onUploadLaboratorio(item.id)}
                                                            className="cursor-pointer gap-2"
                                                        >
                                                            <FlaskConical className="size-3.5" strokeWidth={2.25} />
                                                            {t('historial.action_lab_consulta')}
                                                        </DropdownMenuItem>
                                                    ) : null}
                                                    {onSendAutorizacion ? (
                                                        <DropdownMenuItem
                                                            onSelect={() => onSendAutorizacion(item.id)}
                                                            className="cursor-pointer gap-2"
                                                        >
                                                            <FilePenLine className="size-3.5" strokeWidth={2.25} />
                                                            {t('historial.action_autorizacion')}
                                                        </DropdownMenuItem>
                                                    ) : null}
                                                    {permisos.consultas_eliminar && onDeleteConsulta ? (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onSelect={() => onDeleteConsulta(item)}
                                                                className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                                            >
                                                                <Trash2 className="size-3.5" strokeWidth={2.25} />
                                                                {t('historial.eliminar_consulta')}
                                                            </DropdownMenuItem>
                                                        </>
                                                    ) : null}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        ) : null}
                                    </>
                                ) : null}
                                {item.kind === 'aplicacion' && permisos.vacunas_ver ? (
                                    <>
                                        {!isPublic && (onOpenAplicacion || item.vacunaciones_url) ? (
                                            onOpenAplicacion && item.registro ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    className="group/btn h-8 gap-1.5 px-2.5 text-xs"
                                                    onClick={() => onOpenAplicacion(item)}
                                                >
                                                    <ExternalLink
                                                        className="size-3.5 transition-transform duration-200 group-hover/btn:translate-x-0.5"
                                                        strokeWidth={2.25}
                                                    />
                                                    <span className="hidden sm:inline">{t('historial.ver_aplicacion_corta')}</span>
                                                    <span className="sm:hidden">{t('historial.ver_aplicacion_completa')}</span>
                                                </Button>
                                            ) : item.vacunaciones_url ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    className="group/btn h-8 gap-1.5 px-2.5 text-xs"
                                                    asChild
                                                >
                                                    <Link href={item.vacunaciones_url} prefetch>
                                                        <ExternalLink
                                                            className="size-3.5 transition-transform duration-200 group-hover/btn:translate-x-0.5"
                                                            strokeWidth={2.25}
                                                        />
                                                        <span className="hidden sm:inline">{t('historial.ver_aplicacion_corta')}</span>
                                                        <span className="sm:hidden">{t('historial.ver_aplicacion_completa')}</span>
                                                    </Link>
                                                </Button>
                                            ) : null
                                        ) : null}
                                        {item.pdf_url ? (
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
                                        ) : null}
                                    </>
                                ) : null}
                            </div>
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
                                            label={t('historial.det_motivo')}
                                            text={item.detalle.motivo ?? null}
                                        />
                                        <SoapBlock
                                            label={t('historial.det_subjetivo')}
                                            text={item.detalle.subjetivo}
                                        />
                                        <SoapBlock
                                            label={t('historial.det_objetivo')}
                                            text={item.detalle.objetivo}
                                        />
                                        <SoapBlock
                                            label={t('historial.det_examenes')}
                                            text={
                                                item.detalle.examenes && item.detalle.examenes.length > 0
                                                    ? item.detalle.examenes.join('\n')
                                                    : null
                                            }
                                        />
                                        <SoapBlock label={t('historial.det_analisis')} text={item.detalle.analisis} />
                                        <SoapBlock label={t('historial.det_plan_soap')} text={item.detalle.plan} />
                                        <SoapBlock
                                            label={t('historial.det_anotaciones')}
                                            text={item.detalle.anotaciones ?? null}
                                        />
                                        <SoapBlock
                                            label={t('historial.det_medico')}
                                            text={item.detalle.medico_tratante ?? null}
                                        />
                                    </div>
                                    {vinculosConsultaTieneContenido(item.detalle.vinculos) ? (
                                        <VinculosBlock
                                            vinculos={item.detalle.vinculos}
                                            t={t}
                                            publicMode={isPublic}
                                        />
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
            </div>
        </li>
    );
}

function VinculosBlock({
    vinculos,
    t,
    publicMode = false,
}: {
    vinculos: TimelineConsultaVinculos;
    t: (k: string, o?: Record<string, string | number>) => string;
    publicMode?: boolean;
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
                        url: publicMode ? '' : r.url,
                    }))}
                    t={t}
                />
            ) : null}
            {vinculos.laboratorio.length > 0 ? (
                <div className="space-y-2">
                    <p className="text-xs font-semibold text-foreground">
                        {t('historial.vinculos_sec_lab')}
                    </p>
                    <ul className="m-0 list-none space-y-2 p-0">
                        {vinculos.laboratorio.map((p) => (
                            <li
                                key={p.id}
                                className="rounded-md border border-border/40 bg-background/70 px-2.5 py-2"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                                        <Badge
                                            variant="outline"
                                            className="text-[0.6rem] font-normal"
                                        >
                                            {t(`laboratorio:estado.${p.estado}`, {
                                                defaultValue: p.estado,
                                            })}
                                        </Badge>
                                        <span className="truncate text-[0.7rem] text-muted-foreground">
                                            {t('historial.vinculos_exam', {
                                                count: p.lineas_count,
                                            })}
                                        </span>
                                    </div>
                                    {!publicMode && p.url ? (
                                        <Button
                                            type="button"
                                            variant="link"
                                            size="sm"
                                            className="h-6 shrink-0 px-1 text-xs"
                                            asChild
                                        >
                                            <a href={p.url}>{t('historial.vinculos_abrir')}</a>
                                        </Button>
                                    ) : null}
                                </div>
                                {p.lineas.length > 0 ? (
                                    <ul className="mt-2 space-y-1.5 border-t border-border/40 pt-2">
                                        {p.lineas.map((linea) => (
                                            <li
                                                key={linea.id}
                                                className="rounded-md bg-muted/30 px-2 py-1.5"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <p className="text-xs font-medium text-foreground">
                                                            {linea.nombre_examen}
                                                        </p>
                                                        {linea.resultado_at ? (
                                                            <p className="text-[0.65rem] text-muted-foreground">
                                                                {t(
                                                                    'historial.lab_examen_fecha',
                                                                    {
                                                                        date: linea.resultado_at,
                                                                    },
                                                                )}
                                                            </p>
                                                        ) : null}
                                                        {linea.resultado ? (
                                                            <p className="mt-1 whitespace-pre-wrap text-[0.7rem] text-muted-foreground">
                                                                {linea.resultado}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                    {linea.resultado_archivo_url ? (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-7 shrink-0 gap-1 px-2 text-[0.65rem]"
                                                            asChild
                                                        >
                                                            <a
                                                                href={
                                                                    linea.resultado_archivo_url
                                                                }
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                <FileDown className="size-3" />
                                                                {t(
                                                                    'historial.lab_examen_descargar',
                                                                )}
                                                            </a>
                                                        </Button>
                                                    ) : (
                                                        <span className="shrink-0 text-[0.65rem] text-muted-foreground">
                                                            {t(
                                                                'historial.lab_examen_sin_archivo',
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                                {linea.resultado_archivo_original_name ? (
                                                    <p className="mt-1 truncate text-[0.6rem] text-muted-foreground">
                                                        {
                                                            linea.resultado_archivo_original_name
                                                        }
                                                    </p>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
            {vinculos.cirugias.length > 0 ? (
                <VinculoList
                    title={t('historial.vinculos_sec_ciru')}
                    items={vinculos.cirugias.map((c) => ({
                        id: c.id,
                        badge: t(`cirugia:estado.${c.estado}`, { defaultValue: c.estado }),
                        meta: c.titulo,
                        url: publicMode ? '' : c.url,
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
                        url: publicMode ? '' : h.url,
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
                        {item.url ? (
                            <Button type="button" variant="link" size="sm" className="h-6 shrink-0 px-1 text-xs" asChild>
                                <a href={item.url}>{t('historial.vinculos_abrir')}</a>
                            </Button>
                        ) : null}
                    </li>
                ))}
            </ul>
        </div>
    );
}
