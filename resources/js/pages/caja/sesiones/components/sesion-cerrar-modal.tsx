import { useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    FileText,
    Loader2,
    Receipt,
    Scale,
    Ticket,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import caja from '@/routes/caja';
import { arqueo as arqueoRoute } from '@/routes/caja/sesiones';
import type { QueryParams } from '@/wayfinder';
import type { CajaSesionRow } from '../types';

type SesionCerrarModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sesion: CajaSesionRow | null;
    listQuery: QueryParams;
};

type FormData = {
    saldo_cierre_efectivo: string;
    notas: string;
};

type ArqueoMetodo = {
    codigo: string;
    count: number;
    total: string;
};

type ArqueoVenta = {
    id: string;
    numero: string;
    fecha: string | null;
    cliente: string;
    metodo: string;
    comprobante: string;
    total: string;
    estado: string;
};

type ArqueoPayload = {
    moneda: string;
    ventas_count: number;
    ventas_total: string;
    no_efectivo_total: string;
    anuladas_count: number;
    anuladas_total: string;
    comprobantes: {
        tickets: { count: number; total: string };
        boletas: { count: number; total: string };
        facturas: { count: number; total: string };
    };
    metodos: ArqueoMetodo[];
    ventas: ArqueoVenta[];
    saldo_apertura: string;
    efectivo_ventas: string;
    efectivo_esperado: string;
    efectivo_contado: string | null;
    diferencia: string | null;
};

const empty: FormData = {
    saldo_cierre_efectivo: '',
    notas: '',
};

function formatMoney(value: string | null | undefined, moneda: string, locale: string): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const n = Number(value);
    if (Number.isNaN(n)) {
        return value;
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: moneda === 'USD' ? 'USD' : 'PEN',
    }).format(n);
}

function formatShortDate(iso: string | null, locale: string): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale, {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}

function csrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta instanceof HTMLMetaElement && meta.content) {
        return meta.content;
    }

    return '';
}

export function SesionCerrarModal({ open, onOpenChange, sesion, listQuery }: SesionCerrarModalProps) {
    const { t, i18n } = useTranslation('caja');
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(empty);
    const [arqueo, setArqueo] = useState<ArqueoPayload | null>(null);
    const [loadingArqueo, setLoadingArqueo] = useState(false);
    const [arqueoError, setArqueoError] = useState<string | null>(null);

    useEffect(() => {
        if (!open || !sesion) {
            return;
        }

        reset();
        clearErrors();
        setData(empty);
        setArqueo(null);
        setArqueoError(null);
        setLoadingArqueo(true);

        const url = arqueoRoute.url({ caja_sesion: sesion.id });
        const token = csrfToken();

        void fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error('arqueo_failed');
                }

                return res.json() as Promise<{ arqueo: ArqueoPayload }>;
            })
            .then((json) => {
                setArqueo(json.arqueo);
                if (json.arqueo.efectivo_esperado) {
                    setData('saldo_cierre_efectivo', json.arqueo.efectivo_esperado);
                }
            })
            .catch(() => {
                setArqueoError(t('sesiones.dialog_cerrar.arqueo_error'));
            })
            .finally(() => {
                setLoadingArqueo(false);
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, sesion?.id]);

    const moneda = arqueo?.moneda ?? sesion?.moneda ?? 'PEN';
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';

    const diferenciaLive = useMemo(() => {
        if (!arqueo) {
            return null;
        }

        const esperado = Number(arqueo.efectivo_esperado);
        const contado = Number(data.saldo_cierre_efectivo);
        if (Number.isNaN(esperado) || Number.isNaN(contado) || data.saldo_cierre_efectivo.trim() === '') {
            return null;
        }

        return (contado - esperado).toFixed(2);
    }, [arqueo, data.saldo_cierre_efectivo]);

    const diffTone =
        diferenciaLive === null
            ? 'muted'
            : Number(diferenciaLive) === 0
              ? 'ok'
              : Number(diferenciaLive) > 0
                ? 'over'
                : 'short';

    const metodoLabel = (codigo: string): string =>
        t(`sesiones.dialog_cerrar.metodos.${codigo}`, {
            defaultValue: codigo,
        });

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (!sesion) {
            return;
        }

        const actionUrl = caja.sesiones.cerrar.url({ caja_sesion: sesion.id }, { query: listQuery });
        post(actionUrl, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                clearErrors();
                setArqueo(null);
            },
        });
    };

    return (
        <FormModal
            open={open && sesion !== null}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('sesiones.dialog_cerrar.title')}
            description={t('sesiones.dialog_cerrar.description')}
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" className="cursor-pointer" onClick={() => onOpenChange(false)}>
                        {t('sesiones.dialog_cerrar.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing || !sesion || loadingArqueo}
                        className="cursor-pointer gap-2"
                    >
                        {processing ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
                        {t('sesiones.dialog_cerrar.submit')}
                    </Button>
                </>
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-5">
                {loadingArqueo ? (
                    <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        {t('sesiones.dialog_cerrar.loading_arqueo')}
                    </div>
                ) : null}

                {arqueoError ? (
                    <div className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <span>{arqueoError}</span>
                    </div>
                ) : null}

                {arqueo ? (
                    <>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5">
                                <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                    {t('sesiones.dialog_cerrar.kpi_ventas')}
                                </p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">{arqueo.ventas_count}</p>
                                <p className="text-xs text-muted-foreground">
                                    {formatMoney(arqueo.ventas_total, moneda, locale)}
                                </p>
                            </div>
                            <div className="rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5">
                                <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                    {t('sesiones.dialog_cerrar.kpi_otros')}
                                </p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">
                                    {formatMoney(arqueo.no_efectivo_total, moneda, locale)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('sesiones.dialog_cerrar.kpi_otros_hint')}
                                </p>
                            </div>
                            <div className="rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5">
                                <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                    {t('sesiones.dialog_cerrar.kpi_esperado')}
                                </p>
                                <p className="mt-1 text-lg font-semibold tabular-nums text-primary">
                                    {formatMoney(arqueo.efectivo_esperado, moneda, locale)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('sesiones.dialog_cerrar.kpi_esperado_hint')}
                                </p>
                            </div>
                            <div
                                className={cn(
                                    'rounded-xl border px-3 py-2.5',
                                    diffTone === 'ok' && 'border-emerald-500/40 bg-emerald-500/10',
                                    diffTone === 'over' && 'border-sky-500/40 bg-sky-500/10',
                                    diffTone === 'short' && 'border-amber-500/40 bg-amber-500/10',
                                    diffTone === 'muted' && 'border-border/60 bg-muted/30',
                                )}
                            >
                                <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                    {t('sesiones.dialog_cerrar.kpi_diferencia')}
                                </p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">
                                    {formatMoney(diferenciaLive, moneda, locale)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {diffTone === 'ok'
                                        ? t('sesiones.dialog_cerrar.diff_ok')
                                        : diffTone === 'over'
                                          ? t('sesiones.dialog_cerrar.diff_over')
                                          : diffTone === 'short'
                                            ? t('sesiones.dialog_cerrar.diff_short')
                                            : t('sesiones.dialog_cerrar.diff_pending')}
                                </p>
                            </div>
                        </div>

                        <div className="rounded-xl border border-primary/20 bg-primary/5 px-3 py-2.5 text-xs text-foreground/90">
                            {t('sesiones.dialog_cerrar.clarify', {
                                count: arqueo.ventas_count,
                                total: formatMoney(arqueo.ventas_total, moneda, locale),
                                esperado: formatMoney(arqueo.efectivo_esperado, moneda, locale),
                                otros: formatMoney(arqueo.no_efectivo_total, moneda, locale),
                            })}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3">
                            {(
                                [
                                    ['tickets', Ticket, arqueo.comprobantes.tickets],
                                    ['boletas', Receipt, arqueo.comprobantes.boletas],
                                    ['facturas', FileText, arqueo.comprobantes.facturas],
                                ] as const
                            ).map(([key, Icon, row]) => (
                                <div
                                    key={key}
                                    className="flex items-center gap-3 rounded-xl border border-border/50 bg-card px-3 py-2.5"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Icon className="size-4" strokeWidth={2.25} />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {t(`sesiones.dialog_cerrar.comprobantes.${key}`)}
                                        </p>
                                        <p className="truncate text-sm font-semibold tabular-nums">
                                            {row.count}{' '}
                                            <span className="font-normal text-muted-foreground">
                                                · {formatMoney(row.total, moneda, locale)}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="rounded-xl border border-border/50 overflow-hidden">
                            <div className="flex items-center gap-2 border-b border-border/50 bg-muted/40 px-3 py-2">
                                <Banknote className="size-3.5 text-muted-foreground" />
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    {t('sesiones.dialog_cerrar.metodos_title')}
                                </p>
                            </div>
                            <ul className="divide-y divide-border/40">
                                {arqueo.metodos.map((row) => (
                                    <li
                                        key={row.codigo}
                                        className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                    >
                                        <span className="font-medium">{metodoLabel(row.codigo)}</span>
                                        <span className="tabular-nums text-muted-foreground">
                                            {row.count} · {formatMoney(row.total, moneda, locale)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="rounded-xl border border-border/50 overflow-hidden">
                            <div className="border-b border-border/50 bg-muted/40 px-3 py-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    {t('sesiones.dialog_cerrar.ventas_title')} ({arqueo.ventas_count})
                                </p>
                            </div>
                            {(arqueo.ventas?.length ?? 0) === 0 ? (
                                <p className="px-3 py-3 text-sm text-muted-foreground">
                                    {t('sesiones.dialog_cerrar.ventas_empty')}
                                </p>
                            ) : (
                                <ul className="max-h-44 divide-y divide-border/40 overflow-y-auto">
                                    {arqueo.ventas.map((v) => (
                                        <li
                                            key={v.id}
                                            className="flex items-start justify-between gap-3 px-3 py-2 text-sm"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{v.numero}</p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {formatShortDate(v.fecha, locale)} · {metodoLabel(v.metodo)} ·{' '}
                                                    {v.cliente}
                                                </p>
                                            </div>
                                            <span className="shrink-0 tabular-nums font-semibold">
                                                {formatMoney(v.total, moneda, locale)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div className="rounded-xl border border-dashed border-border/70 bg-muted/20 px-3 py-2.5 text-xs text-muted-foreground">
                            <div className="flex items-start gap-2">
                                <Scale className="mt-0.5 size-3.5 shrink-0" />
                                <p>
                                    {t('sesiones.dialog_cerrar.formula', {
                                        apertura: formatMoney(arqueo.saldo_apertura, moneda, locale),
                                        ventas: formatMoney(arqueo.efectivo_ventas, moneda, locale),
                                        esperado: formatMoney(arqueo.efectivo_esperado, moneda, locale),
                                    })}
                                </p>
                            </div>
                            {arqueo.anuladas_count > 0 ? (
                                <p className="mt-1.5 pl-5">
                                    {t('sesiones.dialog_cerrar.anuladas_hint', {
                                        count: arqueo.anuladas_count,
                                        total: formatMoney(arqueo.anuladas_total, moneda, locale),
                                    })}
                                </p>
                            ) : null}
                        </div>
                    </>
                ) : null}

                <FormField
                    id="cerrar-saldo"
                    label={t('sesiones.fields.saldo_cierre')}
                    error={errors.saldo_cierre_efectivo}
                    hint={t('sesiones.dialog_cerrar.contado_hint')}
                >
                    <Input
                        type="number"
                        inputMode="decimal"
                        min={0}
                        step="0.01"
                        value={data.saldo_cierre_efectivo}
                        onChange={(ev) => setData('saldo_cierre_efectivo', ev.target.value)}
                        className="h-11 text-base tabular-nums font-semibold"
                        autoFocus
                    />
                </FormField>

                <FormField id="cerrar-notas" label={t('sesiones.fields.notas_cierre')} error={errors.notas}>
                    <Textarea
                        value={data.notas}
                        onChange={(ev) => setData('notas', ev.target.value)}
                        rows={2}
                        className="resize-y"
                        placeholder={t('sesiones.dialog_cerrar.notas_placeholder')}
                    />
                </FormField>
            </div>
        </FormModal>
    );
}
