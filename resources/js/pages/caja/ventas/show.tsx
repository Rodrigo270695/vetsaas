import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ExternalLink,
    FileText,
    Loader2,
    Printer,
    Receipt,
    Stethoscope,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import caja from '@/routes/caja';
import clinicaRoutes from '@/routes/clinica';
import type { VentaShowProps } from './types';

function formatMonto(amount: string | null, moneda: string, locale: string): string {
    if (amount === null || amount === '') {
        return '—';
    }

    const n = Number(amount);

    if (Number.isNaN(n)) {
        return amount;
    }

    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return new Intl.NumberFormat(locale, { style: 'currency', currency: cur }).format(n);
}

const METODO_PAGO_KEYS: Record<string, string> = {
    efectivo: 'caja:ventas.create.mp_efectivo',
    yape: 'caja:ventas.create.mp_yape',
    plin: 'caja:ventas.create.mp_plin',
    tarjeta: 'caja:ventas.create.mp_tarjeta',
    transferencia: 'caja:ventas.create.mp_transferencia',
};

export default function Show({
    venta,
    clinica,
    fel,
    anulacion,
    consulta_vinculo: consultaVinculo,
}: VentaShowProps) {
    const { t, i18n } = useTranslation(['caja', 'common']);
    const { t: tCommon } = useTranslation('common');
    const [emitiendoFel, setEmitiendoFel] = useState(false);
    const [anularOpen, setAnularOpen] = useState(false);
    const [motivoAnulacion, setMotivoAnulacion] = useState('');
    const [anulando, setAnulando] = useState(false);
    const [ticketModalOpen, setTicketModalOpen] = useState(false);
    const [ticketIframeBust, setTicketIframeBust] = useState(() => Date.now());
    const ticketIframeRef = useRef<HTMLIFrameElement>(null);

    const fecha = venta.fecha_pago ?? venta.created_at;

    const ticketIframeSrc = useMemo(() => {
        const base = caja.ventas.ticket.url(venta.id);
        const sep = base.includes('?') ? '&' : '?';

        return `${base}${sep}_pv=${ticketIframeBust}`;
    }, [venta.id, ticketIframeBust]);

    const abrirTicketEnModal = () => {
        setTicketIframeBust(Date.now());
        setTicketModalOpen(true);
    };

    const imprimirTicketDesdeIframe = () => {
        const win = ticketIframeRef.current?.contentWindow;
        if (win) {
            win.focus();
            win.print();
        }
    };

    const metodoLabel = venta.metodo_pago
        ? t(METODO_PAGO_KEYS[venta.metodo_pago] ?? venta.metodo_pago, { defaultValue: venta.metodo_pago })
        : '—';

    const felPdfUrl =
        venta.fel_document?.url_pdf ??
        venta.fel_document?.enlace_consulta ??
        null;

    const reintentarFel = () => {
        setEmitiendoFel(true);
        router.post(
            fel.emitir_url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setEmitiendoFel(false),
            },
        );
    };

    const confirmarAnulacion = () => {
        setAnulando(true);
        router.post(
            anulacion.anular_url,
            { motivo: motivoAnulacion },
            {
                preserveScroll: true,
                onFinish: () => {
                    setAnulando(false);
                    setAnularOpen(false);
                },
            },
        );
    };

    const esAnulada = venta.estado === 'anulado';

    const headerStats = useMemo(
        () => [
            {
                label: t('caja:ventas.show.stat_cliente'),
                value: venta.cliente,
                variant: 'default' as const,
            },
            {
                label: t('caja:ventas.show.stat_sede'),
                value: venta.sede,
                variant: 'muted' as const,
            },
            {
                label: t('caja:ventas.show.stat_estado'),
                value: t(`caja:ventas.estado_valor.${venta.estado}`, { defaultValue: venta.estado }),
                variant: 'primary' as const,
            },
        ],
        [t, venta.cliente, venta.estado, venta.sede],
    );

    return (
        <>
            <Head title={t('caja:ventas.show.title', { numero: venta.numero })} />

            <Dialog open={anularOpen} onOpenChange={setAnularOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('caja:ventas.show.anular_venta')}</DialogTitle>
                        <DialogDescription>{t('caja:ventas.show.anular_venta_desc')}</DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="motivo-anulacion">{t('caja:ventas.show.anular_motivo')}</Label>
                        <Textarea
                            id="motivo-anulacion"
                            rows={4}
                            value={motivoAnulacion}
                            onChange={(e) => setMotivoAnulacion(e.target.value)}
                            placeholder={t('caja:ventas.show.anular_motivo_ph')}
                        />
                    </div>
                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAnularOpen(false)}
                            disabled={anulando}
                        >
                            {t('caja:ventas.show.anular_cancelar')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={anulando || motivoAnulacion.trim().length < 5}
                            onClick={confirmarAnulacion}
                        >
                            {anulando ? (
                                <Loader2 className="size-4 animate-spin" aria-hidden />
                            ) : null}
                            {t('caja:ventas.show.anular_confirmar')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={ticketModalOpen} onOpenChange={setTicketModalOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-[calc(100%-1rem)] flex-col gap-3 p-4 sm:max-w-2xl sm:p-6">
                    <DialogHeader className="shrink-0 space-y-1 pr-8 text-left">
                        <DialogTitle>{t('caja:ventas.show.ticket_modal_title')}</DialogTitle>
                        <DialogDescription>{t('caja:ventas.show.ticket_modal_description')}</DialogDescription>
                    </DialogHeader>
                    {ticketModalOpen ? (
                        <iframe
                            ref={ticketIframeRef}
                            title={t('caja:ventas.show.ticket_iframe_title')}
                            src={ticketIframeSrc}
                            className="min-h-[50vh] w-full flex-1 rounded-md border border-border bg-white"
                        />
                    ) : null}
                    <DialogFooter className="shrink-0 gap-2 sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setTicketModalOpen(false)}
                        >
                            {tCommon('actions.close')}
                        </Button>
                        <Button type="button" className="gap-1.5" onClick={imprimirTicketDesdeIframe}>
                            <Printer className="size-4 shrink-0" aria-hidden />
                            {t('caja:ventas.show.ticket_modal_print')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={venta.numero}
                    description={t('caja:ventas.show.description')}
                    stats={headerStats}
                    action={
                        <Button variant="outline" size="sm" asChild className="gap-1.5">
                            <Link href={caja.ventas.index.url()}>
                                <ArrowLeft className="size-4" aria-hidden />
                                {t('caja:ventas.show.volver')}
                            </Link>
                        </Button>
                    }
                />

                {esAnulada ? (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" aria-hidden />
                        <AlertTitle>{t('caja:ventas.show.anulada_banner_title')}</AlertTitle>
                        <AlertDescription>
                            {anulacion.anulado_at
                                ? t('caja:ventas.show.anulada_banner_body', {
                                      fecha: new Intl.DateTimeFormat(i18n.language, {
                                          dateStyle: 'short',
                                          timeStyle: 'short',
                                      }).format(new Date(anulacion.anulado_at)),
                                      motivo:
                                          anulacion.motivo ??
                                          t('caja:ventas.show.anulada_sin_motivo'),
                                  })
                                : (anulacion.motivo ?? t('caja:ventas.show.anulada_sin_motivo'))}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {consultaVinculo ? (
                    <Alert className="border-primary/20 bg-primary/5">
                        <Stethoscope className="size-4 text-primary" aria-hidden />
                        <AlertTitle>{t('caja:ventas.show.consulta_vinculo_title')}</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>
                                {t('caja:ventas.show.consulta_vinculo_body', {
                                    paciente: consultaVinculo.paciente ?? '—',
                                })}
                            </span>
                            <Button asChild variant="secondary" size="sm" className="shrink-0">
                                <Link
                                    href={clinicaRoutes.historiasClinicas.consultas.cargos.show.url(
                                        consultaVinculo.id,
                                    )}
                                >
                                    {t('caja:ventas.show.consulta_vinculo_cta')}
                                </Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-5 lg:grid-cols-[1fr_minmax(280px,340px)]">
                    <div className="flex min-w-0 flex-col gap-3">
                        <div className="flex flex-col gap-2 rounded-lg border border-border/60 bg-muted/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-fit gap-1.5 border-primary/35 font-medium text-primary hover:bg-primary/5"
                                onClick={abrirTicketEnModal}
                            >
                                <Printer className="size-4 shrink-0" aria-hidden />
                                {t('caja:ventas.show.imprimir_ticket')}
                            </Button>
                            <p className="text-xs leading-snug text-muted-foreground">
                                {t('caja:ventas.show.imprimir_ticket_ayuda', {
                                    mm: clinica.ticket_ancho_mm,
                                })}
                            </p>
                        </div>
                        <Card className="border-border/60">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Receipt className="size-4 text-primary" aria-hidden />
                                {t('caja:ventas.show.lineas_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-0 pb-0">
                            {venta.lineas.length === 0 ? (
                                <p className="px-4 pb-4 text-sm text-muted-foreground">
                                    {t('caja:ventas.show.lineas_vacio')}
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[520px] border-collapse text-sm">
                                        <thead className="bg-muted/40">
                                            <tr>
                                                <th className="border-b border-border/60 px-4 py-3 text-left text-xs font-semibold text-muted-foreground">
                                                    {t('caja:ventas.show.col_producto')}
                                                </th>
                                                <th className="w-24 border-b border-border/60 px-4 py-3 text-right text-xs font-semibold text-muted-foreground">
                                                    {t('caja:ventas.show.col_cantidad')}
                                                </th>
                                                <th className="w-28 border-b border-border/60 px-4 py-3 text-right text-xs font-semibold text-muted-foreground">
                                                    {t('caja:ventas.show.col_pu')}
                                                </th>
                                                <th className="w-28 border-b border-border/60 px-4 py-3 text-right text-xs font-semibold text-muted-foreground">
                                                    {t('caja:ventas.show.col_sub')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {venta.lineas.map((ln) => (
                                                <tr
                                                    key={ln.id}
                                                    className="border-b border-border/40 last:border-b-0"
                                                >
                                                    <td className="px-4 py-3 align-middle">
                                                        <div className="flex flex-col gap-0.5">
                                                            <span className="font-medium">{ln.descripcion}</span>
                                                            {ln.sku ? (
                                                                <span className="font-mono text-xs text-muted-foreground">
                                                                    SKU {ln.sku}
                                                                    {ln.unidad ? ` · ${ln.unidad}` : ''}
                                                                </span>
                                                            ) : ln.unidad ? (
                                                                <span className="text-xs text-muted-foreground">
                                                                    {ln.unidad}
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-right align-middle tabular-nums">
                                                        {Number(ln.cantidad).toLocaleString(i18n.language, {
                                                            maximumFractionDigits: 3,
                                                        })}
                                                    </td>
                                                    <td className="px-4 py-3 text-right align-middle tabular-nums text-muted-foreground">
                                                        {formatMonto(ln.precio_unitario, venta.moneda, i18n.language)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right align-middle font-medium tabular-nums">
                                                        {formatMonto(ln.subtotal, venta.moneda, i18n.language)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <p className="border-t border-border/50 px-4 py-2 text-xs text-muted-foreground">
                                {t('caja:ventas.show.pu_sin_igv_hint')}
                            </p>
                        </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-4">
                        <Card className="border-border/60">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">{t('caja:ventas.show.resumen_title')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <dl className="space-y-2">
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">{t('caja:ventas.show.fecha')}</dt>
                                        <dd className="text-right tabular-nums">
                                            {fecha
                                                ? new Intl.DateTimeFormat(i18n.language, {
                                                      dateStyle: 'short',
                                                      timeStyle: 'short',
                                                  }).format(new Date(fecha))
                                                : '—'}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">{t('caja:ventas.show.cajero')}</dt>
                                        <dd className="text-right">{venta.cajero}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">{t('caja:ventas.show.metodo_pago')}</dt>
                                        <dd className="text-right">{metodoLabel}</dd>
                                    </div>
                                    {venta.cliente_doc ? (
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-muted-foreground">{t('caja:ventas.show.doc_cliente')}</dt>
                                            <dd className="text-right font-mono text-xs">{venta.cliente_doc}</dd>
                                        </div>
                                    ) : null}
                                    {venta.paciente ? (
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-muted-foreground">{t('caja:ventas.show.paciente')}</dt>
                                            <dd className="text-right">{venta.paciente}</dd>
                                        </div>
                                    ) : null}
                                </dl>

                                <div
                                    className={cn(
                                        'space-y-2 rounded-lg border border-primary/15 bg-primary/5 p-3',
                                    )}
                                >
                                    <div className="flex justify-between gap-3">
                                        <span className="text-muted-foreground">
                                            {t('caja:ventas.create.res_subtotal')}
                                        </span>
                                        <span className="font-medium tabular-nums">
                                            {formatMonto(venta.subtotal, venta.moneda, i18n.language)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <span className="text-muted-foreground">
                                            {t('caja:ventas.create.res_igv', { pct: clinica.igv_porcentaje })}
                                        </span>
                                        <span className="font-medium tabular-nums">
                                            {formatMonto(venta.igv_monto, venta.moneda, i18n.language)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between gap-3 border-t border-primary/15 pt-2">
                                        <span className="font-semibold">{t('caja:ventas.create.res_total')}</span>
                                        <span className="text-lg font-bold text-primary tabular-nums">
                                            {formatMonto(venta.total, venta.moneda, i18n.language)}
                                        </span>
                                    </div>
                                    {venta.metodo_pago === 'efectivo' && venta.monto_recibido ? (
                                        <>
                                            <div className="flex justify-between gap-3 text-muted-foreground">
                                                <span>{t('caja:ventas.create.monto_recibido')}</span>
                                                <span className="tabular-nums">
                                                    {formatMonto(
                                                        venta.monto_recibido,
                                                        venta.moneda,
                                                        i18n.language,
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex justify-between gap-3 text-muted-foreground">
                                                <span>{t('caja:ventas.create.res_vuelto')}</span>
                                                <span className="tabular-nums">
                                                    {formatMonto(venta.vuelto, venta.moneda, i18n.language)}
                                                </span>
                                            </div>
                                        </>
                                    ) : null}
                                </div>

                                <div className="space-y-2 rounded-lg border border-border/40 bg-muted/15 p-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-xs font-medium text-muted-foreground">
                                            {t('caja:ventas.show.fel_title')}
                                        </span>
                                        <Badge
                                            variant={
                                                venta.fel_estado === 'emitido'
                                                    ? 'default'
                                                    : venta.fel_estado === 'rechazado'
                                                      ? 'destructive'
                                                      : 'outline'
                                            }
                                        >
                                            {t(`caja:ventas.fel.${venta.fel_estado}`, {
                                                defaultValue: venta.fel_estado,
                                            })}
                                        </Badge>
                                    </div>
                                    {venta.fel_document?.numero_completo ? (
                                        <p className="text-sm font-medium tabular-nums">
                                            {venta.fel_document.numero_completo}
                                        </p>
                                    ) : null}
                                    {venta.fel_estado === 'rechazado' &&
                                    venta.fel_document?.error_mensaje ? (
                                        <p className="text-xs leading-snug text-destructive">
                                            {venta.fel_document.error_mensaje}
                                        </p>
                                    ) : null}
                                    {venta.fel_estado === 'pendiente_emision' &&
                                    !clinica.nubefact_configurado ? (
                                        <p className="text-xs text-muted-foreground">
                                            {t('caja:ventas.show.fel_sin_nubefact')}
                                        </p>
                                    ) : null}
                                    <div className="flex flex-wrap gap-2">
                                        {felPdfUrl ? (
                                            <Button variant="outline" size="sm" className="h-8 gap-1.5" asChild>
                                                <a href={felPdfUrl} target="_blank" rel="noreferrer">
                                                    <FileText className="size-3.5" aria-hidden />
                                                    {t('caja:ventas.show.fel_ver_pdf')}
                                                    <ExternalLink className="size-3" aria-hidden />
                                                </a>
                                            </Button>
                                        ) : null}
                                        {fel.puede_emitir && !esAnulada ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                className="h-8 gap-1.5"
                                                disabled={emitiendoFel}
                                                onClick={reintentarFel}
                                            >
                                                {emitiendoFel ? (
                                                    <Loader2 className="size-3.5 animate-spin" aria-hidden />
                                                ) : null}
                                                {t('caja:ventas.show.fel_emitir')}
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>

                                {anulacion.puede_anular && !esAnulada ? (
                                    <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-3">
                                        <p className="text-sm font-medium text-destructive">
                                            {t('caja:ventas.show.anular_venta')}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {t('caja:ventas.show.anular_venta_desc')}
                                        </p>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            className="mt-3"
                                            onClick={() => setAnularOpen(true)}
                                        >
                                            {t('caja:ventas.show.anular_venta')}
                                        </Button>
                                    </div>
                                ) : null}

                                {venta.notas ? (
                                    <div className="rounded-md border border-border/50 bg-muted/20 p-3">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {t('caja:ventas.create.notas')}
                                        </p>
                                        <p className="mt-1 text-sm whitespace-pre-wrap">{venta.notas}</p>
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

Show.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Caja' },
            { title: 'Ventas', href: caja.ventas.index.url() },
            { title: 'Detalle' },
        ]}
    >
        {page}
    </AppLayout>
);
