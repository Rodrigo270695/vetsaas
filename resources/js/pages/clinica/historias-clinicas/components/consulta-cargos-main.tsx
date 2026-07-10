import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    AlertTriangle,
    CalendarDays,
    CheckCircle2,
    Info,
    Loader2,
    Plus,
    Printer,
    Receipt,
    Trash2,
    Wallet,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { PageHeader } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/forms';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { PosPanel } from '@/pages/caja/ventas/components/pos-panel';
import caja from '@/routes/caja';
import { CargoProductoPicker } from './cargo-producto-picker';

type LineaForm = {
    tipo_linea: string;
    concepto: string;
    cantidad: string;
    precio_unitario: string;
    descuento_importe: string;
    producto_id: string | null;
    producto_label: string | null;
};

type LineaApi = {
    id: string;
    tipo_linea: string;
    concepto: string;
    cantidad: string;
    precio_unitario: string;
    descuento_importe: string;
    producto?: { id: string; nombre: string; sku: string | null; unidad: string | null } | null;
};

export type ConsultaCargosMainProps = {
    title: string;
    historiasUrl: string;
    headerStats: {
        label: string;
        value: string;
        variant: 'default' | 'muted' | 'primary';
    }[];
    atendidoLabel: string;
    cargo: {
        moneda: string;
        subtotal_sin_igv: string;
        igv_importe: string;
        total: string;
        lineas: LineaApi[];
    };
    consulta: {
        id: string;
        /** Solo aplica en cargos de consulta clínica; omitir en internamiento. */
        cerrada_at?: string | null;
        veterinario: { name: string } | null;
    };
    productosBuscarUrl?: string;
    onSugerirDiasEstadia?: () => void;
    clinic_billing: {
        igv_porcentaje: number;
        precio_incluye_igv: boolean;
        ticket_ancho_mm: '56' | '58' | '80';
    };
    cobro: {
        venta_id: string | null;
        venta_numero: string | null;
        puede_cobrar: boolean;
        requiere_sesion_caja: boolean;
        url_cobrar: string;
        url_sesiones_caja: string;
    };
    esBorrador: boolean;
    puedeEditar: boolean;
    puedeEditarCargos: boolean;
    puedeCerrarConsulta?: boolean;
    onSolicitarCerrarConsulta?: () => void;
    hayLineasGuardadas: boolean;
    lineasSoloLectura: boolean;
    data: { notas: string; lineas: LineaForm[] };
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: (e: FormEvent) => void;
    onConfirmar: () => void;
    addLinea: () => void;
    removeLinea: (idx: number) => void;
    updateLinea: (idx: number, patch: Partial<LineaForm>) => void;
    setNotas: (value: string) => void;
    abrirTicketEnModal: () => void;
    formatMonto: (amount: string | null, moneda: string) => string;
    lineaImporteMostrar: (ln: {
        cantidad: string;
        precio_unitario: string;
        descuento_importe: string;
    }) => number;
    formatCantidadOnBlur: (raw: string) => string;
    formatImporteOnBlur: (raw: string) => string;
    formatDecimal2: (raw: string | number | null | undefined) => string;
};

function StatusBanner({
    children,
    icon: Icon,
    action,
    tone = 'neutral',
}: {
    children: ReactNode;
    icon: typeof Info;
    action?: ReactNode;
    tone?: 'neutral' | 'success' | 'warning' | 'primary' | 'danger';
}) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border px-3 py-2 text-sm',
                tone === 'neutral' && 'border-border/40 bg-muted/25 text-muted-foreground',
                tone === 'success' &&
                    'border-emerald-200/50 bg-emerald-50/50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-950/20 dark:text-emerald-100',
                tone === 'warning' &&
                    'border-amber-200/50 bg-amber-50/50 text-amber-900 dark:border-amber-500/20 dark:bg-amber-950/20 dark:text-amber-100',
                tone === 'primary' && 'border-primary/15 bg-primary/5 text-foreground',
                tone === 'danger' &&
                    'border-destructive/20 bg-destructive/5 text-destructive dark:text-destructive-foreground',
            )}
        >
            <Icon className="size-3.5 shrink-0 opacity-70" aria-hidden />
            <span className="min-w-0 flex-1 leading-snug">{children}</span>
            {action ? <div className="shrink-0">{action}</div> : null}
        </div>
    );
}

function StatusBanners({
    esBorrador,
    puedeEditarCargos,
    hayLineasGuardadas,
    cobro,
    consulta,
    puedeCerrarConsulta,
    onSolicitarCerrarConsulta,
    t,
}: Pick<
    ConsultaCargosMainProps,
    | 'esBorrador'
    | 'puedeEditarCargos'
    | 'hayLineasGuardadas'
    | 'cobro'
    | 'consulta'
    | 'puedeCerrarConsulta'
    | 'onSolicitarCerrarConsulta'
> & { t: (key: string, opts?: Record<string, unknown>) => string }) {
    const items: ReactNode[] = [];
    const consultaAbierta = consulta.cerrada_at === null;

    if (consultaAbierta) {
        items.push(
            <StatusBanner
                key="consulta-abierta"
                icon={AlertTriangle}
                tone="warning"
                action={
                    puedeCerrarConsulta && onSolicitarCerrarConsulta ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-7 text-xs"
                            onClick={onSolicitarCerrarConsulta}
                        >
                            {t('consulta_abierta.cerrar_cta')}
                        </Button>
                    ) : undefined
                }
            >
                {cobro.venta_id
                    ? t('consulta_abierta.cobrada_hint')
                    : t('consulta_abierta.banner')}
            </StatusBanner>,
        );
    }

    if (!puedeEditarCargos && esBorrador && !hayLineasGuardadas) {
        items.push(
            <StatusBanner key="permiso" icon={Info} tone="warning">
                {t('solo_lectura_sin_permiso')}
            </StatusBanner>,
        );
    }

    if (cobro.venta_id) {
        items.push(
            <StatusBanner
                key="cobrado"
                icon={CheckCircle2}
                tone="success"
                action={
                    <Can permission="ventas.view">
                        <Button asChild variant="outline" size="sm" className="h-7 text-xs">
                            <Link href={caja.ventas.show.url(cobro.venta_id!)}>
                                {t('cobro.ver_venta_cta')}
                            </Link>
                        </Button>
                    </Can>
                }
            >
                {t('cobro.ya_cobrado_body', {
                    numero: cobro.venta_numero ?? cobro.venta_id,
                })}
            </StatusBanner>,
        );
    } else if (!esBorrador && cobro.requiere_sesion_caja) {
        items.push(
            <StatusBanner
                key="sesion-caja"
                icon={Wallet}
                tone="warning"
                action={
                    <Button asChild variant="outline" size="sm" className="h-7 text-xs">
                        <Link href={cobro.url_sesiones_caja}>{t('cobro.abrir_sesion_cta')}</Link>
                    </Button>
                }
            >
                {t('cobro.sin_sesion_caja')}
            </StatusBanner>,
        );
    } else if (!esBorrador && cobro.puede_cobrar) {
        items.push(
            <StatusBanner
                key="cobrar"
                icon={Wallet}
                tone="primary"
                action={
                    <Button asChild size="sm" className="h-7 text-xs">
                        <Link href={cobro.url_cobrar}>{t('cobro.cobrar_cta')}</Link>
                    </Button>
                }
            >
                {t('cobro.alert_body')}
            </StatusBanner>,
        );
    } else if (!esBorrador) {
        items.push(
            <StatusBanner key="confirmado" icon={CheckCircle2} tone="success">
                {t('estado_confirmado_aviso')}
            </StatusBanner>,
        );

        if (!cobro.puede_cobrar && !cobro.requiere_sesion_caja) {
            items.push(
                <StatusBanner key="sin-permiso" icon={Info} tone="danger">
                    {t('cobro.sin_permiso')}
                </StatusBanner>,
            );
        }
    }

    if (items.length === 0) {
        return null;
    }

    return <div className="flex flex-col gap-2">{items}</div>;
}

export function ConsultaCargosMain({
    title,
    historiasUrl,
    headerStats,
    atendidoLabel,
    cargo,
    consulta,
    productosBuscarUrl,
    onSugerirDiasEstadia,
    clinic_billing,
    cobro,
    esBorrador,
    puedeEditar,
    puedeEditarCargos,
    puedeCerrarConsulta = false,
    onSolicitarCerrarConsulta,
    hayLineasGuardadas,
    lineasSoloLectura,
    data,
    errors,
    processing,
    onSubmit,
    onConfirmar,
    addLinea,
    removeLinea,
    updateLinea,
    setNotas,
    abrirTicketEnModal,
    formatMonto,
    lineaImporteMostrar,
    formatCantidadOnBlur,
    formatImporteOnBlur,
    formatDecimal2,
}: ConsultaCargosMainProps) {
    const { t } = useTranslation(['consulta-cargos', 'caja']);
    const { t: tCommon } = useTranslation('common');

    const igvHint = clinic_billing.precio_incluye_igv
        ? t('hint_precio_incluye_igv', { pct: clinic_billing.igv_porcentaje })
        : t('hint_precio_sin_igv', { pct: clinic_billing.igv_porcentaje });

    const notasVisibles = puedeEditar || Boolean(data.notas?.trim());

    const totalesPanel = (
        <aside className="rounded-xl border border-border/50 bg-card/80 p-4 shadow-sm lg:sticky lg:top-4">
            <p className="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {t('totales.resumen_title')}
            </p>
            <dl className="space-y-1.5 text-sm">
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">{t('totales.subtotal')}</dt>
                    <dd className="font-medium tabular-nums">{formatMonto(cargo.subtotal_sin_igv, cargo.moneda)}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">
                        {t('totales.igv')} ({clinic_billing.igv_porcentaje}%)
                    </dt>
                    <dd className="font-medium tabular-nums">{formatMonto(cargo.igv_importe, cargo.moneda)}</dd>
                </div>
                <div className="flex justify-between gap-3 border-t border-border/40 pt-2">
                    <dt className="font-semibold text-foreground">{t('totales.total')}</dt>
                    <dd className="text-base font-semibold tabular-nums text-primary">
                        {formatMonto(cargo.total, cargo.moneda)}
                    </dd>
                </div>
            </dl>
            <p className="mt-2.5 text-[0.7rem] leading-snug text-muted-foreground">{t('totales.hint_refresh')}</p>
            {puedeEditar ? (
                <div className="mt-4 flex flex-col gap-2">
                    <Button type="submit" disabled={processing} className="w-full gap-2" size="sm">
                        {processing ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
                        {t('save_draft')}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-full"
                        disabled={processing}
                        onClick={onConfirmar}
                    >
                        {t('confirmar')}
                    </Button>
                </div>
            ) : null}
        </aside>
    );

    return (
        <div className="flex flex-1 flex-col gap-4 p-4 sm:gap-5 sm:p-6">
            <PageHeader
                title={title}
                description={t('page_description')}
                stats={headerStats}
                className="border-border/40 pb-4"
                action={
                    <Button variant="outline" size="sm" asChild className="gap-1.5">
                        <Link href={historiasUrl}>
                            <ArrowLeft className="size-4" aria-hidden />
                            {t('back_list')}
                        </Link>
                    </Button>
                }
            />

            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground sm:text-sm">
                <span className="inline-flex items-center gap-1.5">
                    <CalendarDays className="size-3.5 shrink-0 opacity-60" aria-hidden />
                    {atendidoLabel}
                </span>
                <span className="text-border/50" aria-hidden>
                    ·
                </span>
                <span className="tabular-nums">{t('label_moneda', { code: cargo.moneda })}</span>
                {consulta.veterinario ? (
                    <>
                        <span className="text-border/50" aria-hidden>
                            ·
                        </span>
                        <span className="truncate">{consulta.veterinario.name}</span>
                    </>
                ) : null}
                <Badge
                    variant={esBorrador ? 'secondary' : 'default'}
                    className="ml-auto h-5 px-2 text-[0.65rem] font-medium sm:ml-0"
                >
                    {esBorrador ? t('estado.borrador') : t('estado.confirmado')}
                </Badge>
            </div>

            <StatusBanners
                esBorrador={esBorrador}
                puedeEditarCargos={puedeEditarCargos}
                hayLineasGuardadas={hayLineasGuardadas}
                cobro={cobro}
                consulta={consulta}
                puedeCerrarConsulta={puedeCerrarConsulta}
                onSolicitarCerrarConsulta={onSolicitarCerrarConsulta}
                t={t}
            />

            <form
                onSubmit={onSubmit}
                className="grid gap-4 lg:grid-cols-[1fr_minmax(240px,280px)] lg:items-start lg:gap-5"
            >
                <div className="flex min-w-0 flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/40 bg-muted/15 px-3 py-2">
                        <p className="min-w-0 flex-1 text-[0.7rem] leading-snug text-muted-foreground sm:text-xs">
                            {igvHint}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 shrink-0 gap-1.5 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                            onClick={abrirTicketEnModal}
                            title={t('imprimir_ticket_ayuda')}
                        >
                            <Printer className="size-3.5" aria-hidden />
                            {t('imprimir_ticket')}
                        </Button>
                    </div>

                    <PosPanel
                        title={t('lineas_title')}
                        description={t('lineas_subtitle')}
                        icon={Receipt}
                        badge={
                            cargo.lineas.length > 0 ? (
                                <Badge variant="outline" className="h-5 px-1.5 text-[0.65rem] tabular-nums">
                                    {cargo.lineas.length}
                                </Badge>
                            ) : null
                        }
                        className="min-h-0 shadow-none ring-0"
                        contentClassName="min-h-0 gap-0 p-0 sm:p-0"
                    >
                        {puedeEditar ? (
                            <div className="space-y-2 p-3 sm:p-4">
                                <div className="flex flex-wrap justify-end gap-2">
                                    {onSugerirDiasEstadia ? (
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            className="h-7 gap-1 text-xs"
                                            onClick={onSugerirDiasEstadia}
                                        >
                                            {t('cargos.sugerir_dias', { ns: 'hospitalizacion' })}
                                        </Button>
                                    ) : null}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-7 gap-1 border-dashed text-xs"
                                        onClick={addLinea}
                                    >
                                        <Plus className="size-3.5" aria-hidden />
                                        {t('add_linea')}
                                    </Button>
                                </div>
                                {data.lineas.length === 0 ? (
                                    <p className="rounded-md border border-dashed border-border/50 bg-muted/10 px-3 py-6 text-center text-sm text-muted-foreground">
                                        {t('sin_lineas')}
                                    </p>
                                ) : (
                                    data.lineas.map((linea, idx) => (
                                        <div
                                            key={idx}
                                            className="rounded-lg border border-border/40 bg-background/60"
                                        >
                                            <div className="flex items-center justify-between gap-2 border-b border-border/30 px-2.5 py-1.5">
                                                <span className="text-[0.65rem] font-medium text-muted-foreground">
                                                    #{idx + 1}
                                                </span>
                                                {data.lineas.length > 1 ? (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-7 text-muted-foreground hover:text-destructive"
                                                        onClick={() => removeLinea(idx)}
                                                        aria-label={tCommon('actions.delete')}
                                                    >
                                                        <Trash2 className="size-3.5" aria-hidden />
                                                    </Button>
                                                ) : null}
                                            </div>
                                            <div className="space-y-2 p-2.5">
                                                <div className="grid gap-2 sm:grid-cols-[7rem_1fr]">
                                                    <FormField
                                                        id={`cargo-linea-${idx}-tipo`}
                                                        label={t('field_tipo')}
                                                        labelClassName="text-[0.65rem]"
                                                        className="gap-1"
                                                        error={errors[`lineas.${idx}.tipo_linea`]}
                                                    >
                                                        <Select
                                                            value={linea.tipo_linea}
                                                            onValueChange={(v) => {
                                                                updateLinea(idx, {
                                                                    tipo_linea: v,
                                                                    producto_id:
                                                                        v === 'producto'
                                                                            ? linea.producto_id
                                                                            : null,
                                                                    producto_label:
                                                                        v === 'producto'
                                                                            ? linea.producto_label
                                                                            : null,
                                                                });
                                                            }}
                                                        >
                                                            <SelectTrigger className="h-8 text-xs">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="servicio">
                                                                    {t('tipo.servicio')}
                                                                </SelectItem>
                                                                <SelectItem value="producto">
                                                                    {t('tipo.producto')}
                                                                </SelectItem>
                                                                <SelectItem value="otro">
                                                                    {t('tipo.otro')}
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </FormField>
                                                    <FormField
                                                        id={`cargo-linea-${idx}-concepto`}
                                                        label={t('field_concepto')}
                                                        labelClassName="text-[0.65rem]"
                                                        className="gap-1"
                                                        error={errors[`lineas.${idx}.concepto`]}
                                                    >
                                                        <Input
                                                            value={linea.concepto}
                                                            onChange={(e) =>
                                                                updateLinea(idx, {
                                                                    concepto: e.target.value,
                                                                })
                                                            }
                                                            className="h-8 text-sm"
                                                        />
                                                    </FormField>
                                                </div>
                                                <div className="grid grid-cols-3 gap-2">
                                                    <FormField
                                                        id={`cargo-linea-${idx}-cantidad`}
                                                        label={t('field_cantidad')}
                                                        labelClassName="text-[0.65rem]"
                                                        className="gap-1"
                                                        error={errors[`lineas.${idx}.cantidad`]}
                                                    >
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            value={linea.cantidad}
                                                            onChange={(e) =>
                                                                updateLinea(idx, {
                                                                    cantidad: e.target.value,
                                                                })
                                                            }
                                                            onBlur={() =>
                                                                updateLinea(idx, {
                                                                    cantidad: formatCantidadOnBlur(
                                                                        linea.cantidad,
                                                                    ),
                                                                })
                                                            }
                                                            className="h-8 tabular-nums text-sm"
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        id={`cargo-linea-${idx}-precio`}
                                                        label={t('field_precio_unitario')}
                                                        labelClassName="text-[0.65rem]"
                                                        className="gap-1"
                                                        error={errors[`lineas.${idx}.precio_unitario`]}
                                                    >
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={linea.precio_unitario}
                                                            onChange={(e) =>
                                                                updateLinea(idx, {
                                                                    precio_unitario: e.target.value,
                                                                })
                                                            }
                                                            onBlur={() =>
                                                                updateLinea(idx, {
                                                                    precio_unitario: formatImporteOnBlur(
                                                                        linea.precio_unitario,
                                                                    ),
                                                                })
                                                            }
                                                            className="h-8 tabular-nums text-sm"
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        id={`cargo-linea-${idx}-descuento`}
                                                        label={t('field_descuento')}
                                                        labelClassName="text-[0.65rem]"
                                                        className="gap-1"
                                                        error={errors[`lineas.${idx}.descuento_importe`]}
                                                    >
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={linea.descuento_importe}
                                                            onChange={(e) =>
                                                                updateLinea(idx, {
                                                                    descuento_importe: e.target.value,
                                                                })
                                                            }
                                                            onBlur={() =>
                                                                updateLinea(idx, {
                                                                    descuento_importe: formatImporteOnBlur(
                                                                        linea.descuento_importe,
                                                                    ),
                                                                })
                                                            }
                                                            className="h-8 tabular-nums text-sm"
                                                        />
                                                    </FormField>
                                                </div>
                                                {linea.tipo_linea === 'producto' ? (
                                                    <CargoProductoPicker
                                                        consultaId={consulta.id}
                                                        productosBuscarUrl={productosBuscarUrl}
                                                        value={linea.producto_id}
                                                        labelResolved={linea.producto_label}
                                                        onSelect={(opt) => {
                                                            if (opt === null) {
                                                                updateLinea(idx, {
                                                                    producto_id: null,
                                                                    producto_label: null,
                                                                });
                                                                return;
                                                            }
                                                            const precio =
                                                                opt.precio_venta != null &&
                                                                String(opt.precio_venta).trim() !== ''
                                                                    ? formatDecimal2(opt.precio_venta)
                                                                    : linea.precio_unitario;
                                                            updateLinea(idx, {
                                                                producto_id: opt.id,
                                                                producto_label: opt.nombre,
                                                                concepto:
                                                                    linea.concepto.trim() === ''
                                                                        ? opt.nombre
                                                                        : linea.concepto,
                                                                precio_unitario: precio,
                                                            });
                                                        }}
                                                    />
                                                ) : null}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        ) : lineasSoloLectura ? (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[520px] border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b border-border/40 bg-muted/20">
                                            <th className="px-3 py-2 text-left text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                                                {t('col_concepto')}
                                            </th>
                                            <th className="w-20 px-3 py-2 text-left text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                                                {t('field_tipo')}
                                            </th>
                                            <th className="w-16 px-3 py-2 text-right text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                                                {t('field_cantidad')}
                                            </th>
                                            <th className="w-24 px-3 py-2 text-right text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                                                {t('field_precio_unitario')}
                                            </th>
                                            <th className="w-24 px-3 py-2 text-right text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                                                {t('col_importe')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cargo.lineas.map((ln) => (
                                            <tr
                                                key={ln.id}
                                                className="border-b border-border/30 last:border-b-0 hover:bg-muted/15"
                                            >
                                                <td className="px-3 py-2 align-middle">
                                                    <div className="font-medium text-foreground">
                                                        {ln.concepto}
                                                    </div>
                                                    {ln.producto?.sku ? (
                                                        <span className="text-[0.65rem] text-muted-foreground">
                                                            SKU {ln.producto.sku}
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className="px-3 py-2 align-middle">
                                                    <span className="text-xs text-muted-foreground">
                                                        {t(`tipo.${ln.tipo_linea}`, {
                                                            defaultValue: ln.tipo_linea,
                                                        })}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 text-right align-middle tabular-nums text-muted-foreground">
                                                    {ln.cantidad}
                                                </td>
                                                <td className="px-3 py-2 text-right align-middle tabular-nums text-muted-foreground">
                                                    {formatMonto(ln.precio_unitario, cargo.moneda)}
                                                </td>
                                                <td className="px-3 py-2 text-right align-middle font-medium tabular-nums">
                                                    {formatMonto(
                                                        String(
                                                            lineaImporteMostrar({
                                                                cantidad: ln.cantidad,
                                                                precio_unitario: ln.precio_unitario,
                                                                descuento_importe:
                                                                    ln.descuento_importe ?? '0',
                                                            }),
                                                        ),
                                                        cargo.moneda,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="px-3 py-6 text-center text-sm text-muted-foreground sm:px-4">
                                {t('sin_lineas')}
                            </p>
                        )}
                    </PosPanel>

                    {notasVisibles ? (
                        puedeEditar ? (
                            <div className="rounded-lg border border-border/40 bg-muted/10 px-3 py-2.5">
                                <FormField
                                    id="cargo-notas"
                                    label={t('field_notas')}
                                    labelClassName="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground"
                                    error={errors.notas}
                                    className="gap-1.5"
                                >
                                    <Textarea
                                        id="cargo-notas"
                                        value={data.notas}
                                        onChange={(e) => setNotas(e.target.value)}
                                        rows={2}
                                        className="min-h-0 resize-y border-border/50 bg-background text-sm"
                                        placeholder={t('field_notas_placeholder')}
                                    />
                                </FormField>
                            </div>
                        ) : (
                            <div className="rounded-lg border border-border/40 bg-muted/10 px-3 py-2.5">
                                <p className="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                                    {t('field_notas')}
                                </p>
                                <p className="mt-1 text-sm leading-snug text-foreground">{data.notas}</p>
                            </div>
                        )
                    ) : null}
                </div>

                {totalesPanel}
            </form>
        </div>
    );
}
