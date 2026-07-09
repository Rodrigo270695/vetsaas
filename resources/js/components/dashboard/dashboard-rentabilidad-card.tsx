import { AlertTriangle, ChevronDown, ChevronUp, Coins, Loader2, PiggyBank, Receipt, TrendingUp } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    DEFAULT_RENTABILIDAD_FILTROS,
    RentabilidadComprobanteFilterBar,
    RentabilidadPorComprobanteGrid,
    buildRentabilidadUrl,
} from '@/components/dashboard/rentabilidad-comprobante-section';
import { cn } from '@/lib/utils';
import type { RentabilidadComprobanteFiltros, RentabilidadPeriodo, RentabilidadResumen } from '@/pages/dashboard/types';

type Props = {
    initial: RentabilidadResumen;
    moneda: string;
    locale: string;
};

const PERIODOS: RentabilidadPeriodo[] = ['semana', 'mes_actual', 'mes_pasado'];

function formatMoney(value: number, moneda: string, locale: string): string {
    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: moneda,
            minimumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${moneda} ${value.toFixed(2)}`;
    }
}

function formatDateRange(desde: string, hasta: string, locale: string): string {
    try {
        const fmt = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short' });
        const d = new Date(`${desde}T00:00:00`);
        const h = new Date(`${hasta}T00:00:00`);
        return `${fmt.format(d)} – ${fmt.format(h)}`;
    } catch {
        return `${desde} – ${hasta}`;
    }
}

/** Color del margen según salud del negocio. */
function marginTone(pct: number | null): { text: string; bar: string } {
    if (pct === null) {
        return { text: 'text-muted-foreground', bar: 'bg-muted-foreground/40' };
    }
    if (pct < 0) {
        return { text: 'text-rose-600 dark:text-rose-400', bar: 'bg-rose-500' };
    }
    if (pct < 20) {
        return { text: 'text-amber-600 dark:text-amber-400', bar: 'bg-amber-500' };
    }
    return { text: 'text-emerald-600 dark:text-emerald-400', bar: 'bg-emerald-500' };
}

export function DashboardRentabilidadCard({ initial, moneda, locale }: Props) {
    const { t } = useTranslation(['dashboard', 'common']);
    const [periodo, setPeriodo] = useState<RentabilidadPeriodo>(initial.periodo);
    const [filtros, setFiltros] = useState<RentabilidadComprobanteFiltros>(
        initial.filtros ?? DEFAULT_RENTABILIDAD_FILTROS,
    );
    const [data, setData] = useState<RentabilidadResumen>(initial);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const [showAll, setShowAll] = useState(false);
    const requestId = useRef(0);

    const TOP_PREVIEW = 3;

    const fetchData = useCallback((nextPeriodo: RentabilidadPeriodo, nextFiltros: RentabilidadComprobanteFiltros) => {
        const id = ++requestId.current;
        setPeriodo(nextPeriodo);
        setFiltros(nextFiltros);
        setLoading(true);
        setError(false);
        setShowAll(false);

        fetch(buildRentabilidadUrl('/dashboard/rentabilidad', nextPeriodo, nextFiltros), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }
                return res.json();
            })
            .then((payload: RentabilidadResumen) => {
                if (id === requestId.current) {
                    setData(payload);
                    setLoading(false);
                }
            })
            .catch(() => {
                if (id === requestId.current) {
                    setError(true);
                    setLoading(false);
                }
            });
    }, []);

    // Si el periodo inicial provisto por el servidor cambia (recarga Inertia), sincroniza.
    useEffect(() => {
        setData(initial);
        setPeriodo(initial.periodo);
        setFiltros(initial.filtros ?? DEFAULT_RENTABILIDAD_FILTROS);
    }, [initial]);

    const tone = marginTone(data.margen_pct);
    const isEmpty = data.ingresos === 0 && data.costo === 0;
    const costoPct = data.ingresos > 0 ? Math.max(0, Math.min(100, (data.costo / data.ingresos) * 100)) : 0;
    const gananciaPct = data.ingresos > 0 ? Math.max(0, 100 - costoPct) : 0;

    return (
        <Card className="min-w-0 gap-0 overflow-hidden border-border/80 py-0 shadow-sm transition-shadow hover:shadow-md">
            <CardHeader className="border-b border-emerald-200/60 bg-linear-to-br from-emerald-50/95 via-emerald-50/50 to-emerald-50/10 pb-4 pt-5 dark:border-emerald-800/40 dark:from-emerald-950/50 dark:via-emerald-950/25 dark:to-transparent">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200/50 dark:bg-emerald-900/40 dark:text-emerald-200">
                            <PiggyBank className="size-4" aria-hidden />
                        </div>
                        <div className="min-w-0">
                            <h3 className="text-base font-semibold text-foreground">
                                {t('rentabilidad.title')}
                            </h3>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {t('rentabilidad.subtitle')}
                            </p>
                        </div>
                    </div>

                    <div
                        role="group"
                        aria-label={t('rentabilidad.title')}
                        className="inline-flex shrink-0 items-center gap-0.5 self-start rounded-lg border border-border/70 bg-card/80 p-0.5 shadow-sm"
                    >
                        {PERIODOS.map((p) => {
                            const active = p === periodo;
                            return (
                                <button
                                    key={p}
                                    type="button"
                                    aria-pressed={active}
                                    disabled={loading}
                                    onClick={() => !active && fetchData(p, filtros)}
                                    className={cn(
                                        'cursor-pointer rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                                        active
                                            ? 'bg-emerald-600 text-white shadow-sm'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                        loading && !active && 'opacity-60',
                                    )}
                                >
                                    {t(`rentabilidad.periodo.${p}`)}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                    {loading ? (
                        <>
                            <Loader2 className="size-3.5 animate-spin" aria-hidden />
                            {t('rentabilidad.loading')}
                        </>
                    ) : (
                        formatDateRange(data.desde, data.hasta, locale)
                    )}
                </p>

                <RentabilidadComprobanteFilterBar
                    filtros={filtros}
                    loading={loading}
                    onChange={(next) => fetchData(periodo, next)}
                />
            </CardHeader>

            <CardContent className="min-w-0 space-y-5 bg-muted/20 px-6 pb-6 pt-5">
                {error ? (
                    <p className="rounded-lg border border-rose-200/60 bg-rose-50/60 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300">
                        {t('common:error', { defaultValue: 'Ocurrió un error. Intenta de nuevo.' })}
                    </p>
                ) : isEmpty ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border/80 bg-background/60 px-6 py-10 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <PiggyBank className="size-6" aria-hidden />
                        </div>
                        <p className="max-w-xs text-sm text-muted-foreground">
                            {t('rentabilidad.empty')}
                        </p>
                    </div>
                ) : (
                    <div className={cn('space-y-5 transition-opacity', loading && 'opacity-60')}>
                        {/* KPIs */}
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <StatTile
                                icon={Receipt}
                                accent="sky"
                                label={t('rentabilidad.ingresos')}
                                value={formatMoney(data.ingresos, moneda, locale)}
                            />
                            <StatTile
                                icon={Coins}
                                accent="amber"
                                label={t('rentabilidad.costo')}
                                value={formatMoney(data.costo, moneda, locale)}
                            />
                            <StatTile
                                icon={PiggyBank}
                                accent={data.ganancia < 0 ? 'rose' : 'emerald'}
                                label={t('rentabilidad.ganancia')}
                                value={formatMoney(data.ganancia, moneda, locale)}
                            />
                            <StatTile
                                icon={TrendingUp}
                                accent="violet"
                                label={t('rentabilidad.margen')}
                                value={data.margen_pct === null ? '—' : `${data.margen_pct.toFixed(1)}%`}
                                valueClassName={tone.text}
                            />
                        </div>

                        {/* Barra de composición ingreso = costo + ganancia */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-xs font-medium text-muted-foreground">
                                <span>{t('rentabilidad.composicion')}</span>
                                <span className="tabular-nums">
                                    {data.unidades.toLocaleString(locale)} {t('rentabilidad.unidades')}
                                </span>
                            </div>
                            <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full bg-amber-400/80 transition-all"
                                    style={{ width: `${costoPct}%` }}
                                    title={`${t('rentabilidad.costo_label')}: ${formatMoney(data.costo, moneda, locale)}`}
                                />
                                <div
                                    className={cn('h-full transition-all', data.ganancia < 0 ? 'bg-rose-500' : 'bg-emerald-500')}
                                    style={{ width: `${gananciaPct}%` }}
                                    title={`${t('rentabilidad.ganancia_label')}: ${formatMoney(data.ganancia, moneda, locale)}`}
                                />
                            </div>
                            <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-2.5 rounded-sm bg-amber-400/80" />
                                    {t('rentabilidad.costo_label')} · {costoPct.toFixed(0)}%
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <span className={cn('size-2.5 rounded-sm', data.ganancia < 0 ? 'bg-rose-500' : 'bg-emerald-500')} />
                                    {t('rentabilidad.ganancia_label')} · {gananciaPct.toFixed(0)}%
                                </span>
                            </div>
                        </div>

                        {data.por_comprobante && (
                            <RentabilidadPorComprobanteGrid
                                data={data.por_comprobante}
                                moneda={moneda}
                                locale={locale}
                                unidadesLabel={t('rentabilidad.unidades')}
                            />
                        )}

                        {/* Top productos rentables */}
                        {data.items.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-xs font-medium text-muted-foreground">
                                    {t('rentabilidad.top_title')}
                                </p>
                                <div className="overflow-hidden rounded-lg border border-border/60">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-border/60 bg-muted/40 text-xs text-muted-foreground">
                                                <th className="px-3 py-2 text-left font-medium">
                                                    {t('rentabilidad.col_producto')}
                                                </th>
                                                <th className="px-3 py-2 text-right font-medium">
                                                    {t('rentabilidad.col_uds')}
                                                </th>
                                                <th className="px-3 py-2 text-right font-medium">
                                                    {t('rentabilidad.col_ganancia')}
                                                </th>
                                                <th className="px-3 py-2 text-right font-medium">
                                                    {t('rentabilidad.margen')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(showAll ? data.items : data.items.slice(0, TOP_PREVIEW)).map((item, idx) => {
                                                const itemTone = marginTone(item.margen_pct);
                                                return (
                                                    <tr
                                                        key={`${item.nombre}-${idx}`}
                                                        className="border-b border-border/40 last:border-0 odd:bg-background/40"
                                                    >
                                                        <td className="max-w-[220px] truncate px-3 py-2 font-medium text-foreground">
                                                            {item.nombre}
                                                        </td>
                                                        <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">
                                                            {item.cantidad.toLocaleString(locale)}
                                                        </td>
                                                        <td className="px-3 py-2 text-right tabular-nums font-semibold text-foreground">
                                                            {formatMoney(item.ganancia, moneda, locale)}
                                                        </td>
                                                        <td className={cn('px-3 py-2 text-right tabular-nums font-semibold', itemTone.text)}>
                                                            {item.margen_pct === null ? '—' : `${item.margen_pct.toFixed(1)}%`}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                {data.items.length > TOP_PREVIEW && (
                                    <button
                                        type="button"
                                        onClick={() => setShowAll((v) => !v)}
                                        className="flex w-full cursor-pointer items-center justify-center gap-1 rounded-lg border border-border/60 bg-card/60 py-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        {showAll ? (
                                            <>
                                                {t('rentabilidad.ver_menos')}
                                                <ChevronUp className="size-3.5" aria-hidden />
                                            </>
                                        ) : (
                                            <>
                                                {t('rentabilidad.ver_mas', { count: data.items.length - TOP_PREVIEW })}
                                                <ChevronDown className="size-3.5" aria-hidden />
                                            </>
                                        )}
                                    </button>
                                )}
                            </div>
                        )}

                        {/* Aviso: productos sin costo */}
                        {data.productos_sin_costo > 0 && (
                            <div className="flex items-start gap-2.5 rounded-lg border border-amber-200/70 bg-amber-50/60 px-3 py-2.5 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
                                <div className="space-y-0.5">
                                    <p className="font-medium">
                                        {t('rentabilidad.sin_costo_aviso', { count: data.productos_sin_costo })}
                                    </p>
                                    <p className="opacity-80">{t('rentabilidad.sin_costo_cta')}</p>
                                </div>
                            </div>
                        )}

                        <p className="text-[11px] leading-relaxed text-muted-foreground">
                            {t('rentabilidad.nota')}
                        </p>
                        <p className="text-[11px] leading-relaxed text-muted-foreground">
                            {t('rentabilidad.nota_comprobantes')}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

type TileAccent = 'sky' | 'amber' | 'emerald' | 'violet' | 'rose';

const tileAccent: Record<TileAccent, { card: string; icon: string }> = {
    sky: {
        card: 'border-sky-200/50 bg-linear-to-br from-sky-50/70 to-card dark:from-sky-950/25',
        icon: 'bg-sky-100 text-sky-700 ring-sky-200/70 dark:bg-sky-900/40 dark:text-sky-200',
    },
    amber: {
        card: 'border-amber-200/60 bg-linear-to-br from-amber-50/80 to-card dark:from-amber-950/30',
        icon: 'bg-amber-100 text-amber-800 ring-amber-200/70 dark:bg-amber-900/40 dark:text-amber-200',
    },
    emerald: {
        card: 'border-emerald-200/50 bg-linear-to-br from-emerald-50/70 to-card dark:from-emerald-950/25',
        icon: 'bg-emerald-100 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-900/40 dark:text-emerald-200',
    },
    violet: {
        card: 'border-violet-200/50 bg-linear-to-br from-violet-50/70 to-card dark:from-violet-950/25',
        icon: 'bg-violet-100 text-violet-700 ring-violet-200/70 dark:bg-violet-900/40 dark:text-violet-200',
    },
    rose: {
        card: 'border-rose-200/50 bg-linear-to-br from-rose-50/60 to-card dark:from-rose-950/20',
        icon: 'bg-rose-100 text-rose-700 ring-rose-200/70 dark:bg-rose-900/40 dark:text-rose-200',
    },
};

function StatTile({
    icon: Icon,
    accent,
    label,
    value,
    valueClassName,
}: {
    icon: typeof PiggyBank;
    accent: TileAccent;
    label: string;
    value: string;
    valueClassName?: string;
}) {
    const styles = tileAccent[accent];
    return (
        <div className={cn('rounded-xl border p-3 shadow-sm', styles.card)}>
            <div className="flex items-center gap-2">
                <div className={cn('flex size-7 shrink-0 items-center justify-center rounded-lg ring-1', styles.icon)}>
                    <Icon className="size-3.5" aria-hidden />
                </div>
                <p className="min-w-0 truncate text-xs font-medium text-muted-foreground">{label}</p>
            </div>
            <p className={cn('mt-2 text-xl font-bold tabular-nums tracking-tight text-foreground', valueClassName)}>
                {value}
            </p>
        </div>
    );
}
