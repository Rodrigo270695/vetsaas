import { ArrowDownCircle, Banknote, FileText, Receipt, Scale, Ticket } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { formatArqueoMoney, type ArqueoPayload } from './arqueo-types';

type ArqueoResumenProps = {
    arqueo: ArqueoPayload;
    /** Si se pasa, se usa en el KPI de diferencia (cierre en vivo). Si no, usa arqueo.diferencia. */
    diferenciaOverride?: string | null;
    diffTone?: 'ok' | 'over' | 'short' | 'muted';
};

export function ArqueoResumen({
    arqueo,
    diferenciaOverride,
    diffTone = 'muted',
}: ArqueoResumenProps) {
    const { t, i18n } = useTranslation('caja');
    const moneda = arqueo.moneda || 'PEN';
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';
    const diferencia = diferenciaOverride !== undefined ? diferenciaOverride : arqueo.diferencia;

    const metodoLabel = (codigo: string): string =>
        t(`sesiones.dialog_cerrar.metodos.${codigo}`, {
            defaultValue: codigo,
        });

    const tone =
        diffTone !== 'muted'
            ? diffTone
            : diferencia === null || diferencia === undefined || diferencia === ''
              ? 'muted'
              : Number(diferencia) === 0
                ? 'ok'
                : Number(diferencia) > 0
                  ? 'over'
                  : 'short';

    return (
        <>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                <div className="rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5">
                    <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        {t('sesiones.dialog_cerrar.kpi_ventas')}
                    </p>
                    <p className="mt-1 text-lg font-semibold tabular-nums">{arqueo.ventas_count}</p>
                    <p className="text-xs text-muted-foreground">
                        {formatArqueoMoney(arqueo.ventas_total, moneda, locale)}
                    </p>
                </div>
                <div className="rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5">
                    <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        {t('sesiones.dialog_cerrar.kpi_otros')}
                    </p>
                    <p className="mt-1 text-lg font-semibold tabular-nums">
                        {formatArqueoMoney(arqueo.no_efectivo_total, moneda, locale)}
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
                        {formatArqueoMoney(arqueo.efectivo_esperado, moneda, locale)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {t('sesiones.dialog_cerrar.kpi_esperado_hint')}
                    </p>
                </div>
                <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2.5">
                    <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        {t('sesiones.dialog_cerrar.kpi_egresos')}
                    </p>
                    <p className="mt-1 text-lg font-semibold tabular-nums text-rose-700 dark:text-rose-300">
                        {formatArqueoMoney(arqueo.egresos_total ?? '0.00', moneda, locale)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {t('sesiones.dialog_cerrar.kpi_egresos_hint', {
                            count: arqueo.egresos_count ?? 0,
                        })}
                    </p>
                </div>
                <div
                    className={cn(
                        'rounded-xl border px-3 py-2.5',
                        tone === 'ok' && 'border-emerald-500/40 bg-emerald-500/10',
                        tone === 'over' && 'border-sky-500/40 bg-sky-500/10',
                        tone === 'short' && 'border-amber-500/40 bg-amber-500/10',
                        tone === 'muted' && 'border-border/60 bg-muted/30',
                    )}
                >
                    <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        {t('sesiones.dialog_cerrar.kpi_diferencia')}
                    </p>
                    <p className="mt-1 text-lg font-semibold tabular-nums">
                        {formatArqueoMoney(diferencia, moneda, locale)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {tone === 'ok'
                            ? t('sesiones.dialog_cerrar.diff_ok')
                            : tone === 'over'
                              ? t('sesiones.dialog_cerrar.diff_over')
                              : tone === 'short'
                                ? t('sesiones.dialog_cerrar.diff_short')
                                : t('sesiones.dialog_cerrar.diff_pending')}
                    </p>
                </div>
            </div>

            <div className="rounded-xl border border-primary/20 bg-primary/5 px-3 py-2.5 text-xs text-foreground/90">
                {t('sesiones.dialog_cerrar.clarify', {
                    count: arqueo.ventas_count,
                    total: formatArqueoMoney(arqueo.ventas_total, moneda, locale),
                    productos: formatArqueoMoney(arqueo.productos_total, moneda, locale),
                    servicios: formatArqueoMoney(arqueo.servicios_total, moneda, locale),
                    esperado: formatArqueoMoney(arqueo.efectivo_esperado, moneda, locale),
                    otros: formatArqueoMoney(arqueo.no_efectivo_total, moneda, locale),
                    egresos: formatArqueoMoney(arqueo.egresos_total ?? '0.00', moneda, locale),
                })}
            </div>

            {(arqueo.egresos_count ?? 0) > 0 ? (
                <div className="overflow-hidden rounded-xl border border-rose-500/25">
                    <div className="flex items-center gap-2 border-b border-rose-500/20 bg-rose-500/10 px-3 py-2">
                        <ArrowDownCircle className="size-3.5 text-rose-600 dark:text-rose-300" />
                        <p className="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">
                            {t('sesiones.dialog_cerrar.egresos_title')}
                        </p>
                    </div>
                    <ul className="divide-y divide-border/40">
                        {(arqueo.egresos ?? []).map((row) => (
                            <li
                                key={row.id}
                                className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                            >
                                <span className="min-w-0 truncate font-medium">
                                    {row.motivo_label}
                                    {row.notas ? (
                                        <span className="text-muted-foreground"> · {row.notas}</span>
                                    ) : null}
                                </span>
                                <span className="shrink-0 tabular-nums text-rose-700 dark:text-rose-300">
                                    −{formatArqueoMoney(row.monto, moneda, locale)}
                                </span>
                            </li>
                        ))}
                        <li className="flex items-center justify-between gap-3 bg-muted/30 px-3 py-2.5 text-sm font-semibold">
                            <span>{t('sesiones.dialog_cerrar.egresos_total')}</span>
                            <span className="tabular-nums text-rose-700 dark:text-rose-300">
                                −{formatArqueoMoney(arqueo.egresos_total ?? '0.00', moneda, locale)}
                            </span>
                        </li>
                    </ul>
                </div>
            ) : null}

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
                                    · {formatArqueoMoney(row.total, moneda, locale)}
                                </span>
                            </p>
                        </div>
                    </div>
                ))}
            </div>

            <div className="overflow-hidden rounded-xl border border-border/50">
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
                                {row.count} · {formatArqueoMoney(row.total, moneda, locale)}
                            </span>
                        </li>
                    ))}
                    <li className="flex items-center justify-between gap-3 bg-muted/30 px-3 py-2.5 text-sm font-semibold">
                        <span>{t('sesiones.dialog_cerrar.metodos_total')}</span>
                        <span className="tabular-nums">
                            {arqueo.ventas_count} · {formatArqueoMoney(arqueo.ventas_total, moneda, locale)}
                        </span>
                    </li>
                </ul>
                <div className="border-t border-border/40 px-3 py-2 text-xs text-muted-foreground">
                    {t('sesiones.dialog_cerrar.rubros', {
                        productos: formatArqueoMoney(arqueo.productos_total, moneda, locale),
                        servicios: formatArqueoMoney(arqueo.servicios_total, moneda, locale),
                    })}
                </div>
            </div>

            <div className="rounded-xl border border-dashed border-border/70 bg-muted/20 px-3 py-2.5 text-xs text-muted-foreground">
                <div className="flex items-start gap-2">
                    <Scale className="mt-0.5 size-3.5 shrink-0" />
                    <p>
                        {t('sesiones.dialog_cerrar.formula', {
                            apertura: formatArqueoMoney(arqueo.saldo_apertura, moneda, locale),
                            ventas: formatArqueoMoney(arqueo.efectivo_ventas, moneda, locale),
                            egresos: formatArqueoMoney(arqueo.egresos_total ?? '0.00', moneda, locale),
                            esperado: formatArqueoMoney(arqueo.efectivo_esperado, moneda, locale),
                        })}
                    </p>
                </div>
                {arqueo.anuladas_count > 0 ? (
                    <p className="mt-1.5 pl-5">
                        {t('sesiones.dialog_cerrar.anuladas_hint', {
                            count: arqueo.anuladas_count,
                            total: formatArqueoMoney(arqueo.anuladas_total, moneda, locale),
                        })}
                    </p>
                ) : null}
            </div>
        </>
    );
}
