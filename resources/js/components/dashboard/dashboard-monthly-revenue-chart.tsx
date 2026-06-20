import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { DashboardChartEmpty } from '@/components/dashboard/dashboard-chart-empty';
import { DashboardChartShell } from '@/components/dashboard/dashboard-chart-shell';
import { cn } from '@/lib/utils';
import type { ComparacionIngresosMes, IngresosMensualRow } from '@/pages/dashboard/types';

type Props = {
    data: IngresosMensualRow[];
    comparacion: ComparacionIngresosMes | null;
    moneda: string;
    locale: string;
    labels: {
        vsPrevious: string;
        ticketAvg: string;
        sales: string;
        noChange: string;
    };
};

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

function isEmpty(data: IngresosMensualRow[]): boolean {
    return data.every((row) => row.total === 0 && row.count === 0);
}

export function DashboardMonthlyRevenueChart({
    data,
    comparacion,
    moneda,
    locale,
    labels,
}: Props) {
    if (isEmpty(data)) {
        return <DashboardChartEmpty />;
    }

    const variacion = comparacion?.variacion_pct ?? null;
    const trendUp = variacion !== null && variacion > 0;
    const trendDown = variacion !== null && variacion < 0;

    return (
        <div className="space-y-3">
            {comparacion !== null && (
                <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border/60 bg-card/60 px-3 py-2 text-xs">
                    <div className="flex items-center gap-1.5 font-medium text-foreground">
                        {trendUp && <ArrowUpRight className="size-3.5 text-emerald-600" />}
                        {trendDown && <ArrowDownRight className="size-3.5 text-rose-600" />}
                        {!trendUp && !trendDown && <Minus className="size-3.5 text-muted-foreground" />}
                        <span>
                            {variacion === null
                                ? labels.noChange
                                : `${variacion > 0 ? '+' : ''}${variacion}% ${labels.vsPrevious}`}
                        </span>
                    </div>
                    <span className="text-muted-foreground">
                        {formatMoney(comparacion.mes_actual_total, moneda, locale)} ·{' '}
                        {comparacion.mes_actual_count} {labels.sales}
                    </span>
                    <span className="text-muted-foreground">
                        {labels.ticketAvg}:{' '}
                        {formatMoney(comparacion.ticket_promedio_actual, moneda, locale)}
                    </span>
                </div>
            )}

            <DashboardChartShell>
                {({ width, height }) => (
                    <BarChart
                        width={width}
                        height={height}
                        data={data}
                        margin={{ top: 12, right: 8, left: 0, bottom: 0 }}
                    >
                        <defs>
                            <linearGradient id="dashboardMonthlyBar" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="var(--brand-500)" stopOpacity={0.95} />
                                <stop offset="100%" stopColor="var(--brand-700)" stopOpacity={0.85} />
                            </linearGradient>
                            <linearGradient id="dashboardMonthlyBarMuted" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="var(--chart-3)" stopOpacity={0.75} />
                                <stop offset="100%" stopColor="var(--chart-3)" stopOpacity={0.55} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid
                            stroke="var(--border)"
                            strokeOpacity={0.45}
                            strokeDasharray="4 4"
                            vertical={false}
                        />
                        <XAxis
                            dataKey="label"
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            tickLine={false}
                            axisLine={false}
                        />
                        <YAxis
                            tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                            tickLine={false}
                            axisLine={false}
                            width={72}
                            tickFormatter={(v: number) =>
                                new Intl.NumberFormat(locale, {
                                    notation: 'compact',
                                    maximumFractionDigits: 1,
                                }).format(v)
                            }
                        />
                        <Tooltip
                            cursor={{ fill: 'var(--brand-500)', opacity: 0.08 }}
                            content={({ active, payload }) => {
                                if (!active || !payload?.length) {
                                    return null;
                                }

                                const row = payload[0].payload as IngresosMensualRow;
                                const ticket = row.count > 0 ? row.total / row.count : 0;

                                return (
                                    <div className="rounded-lg border border-brand-200/60 bg-popover px-3 py-2 text-xs shadow-lg">
                                        <p className="font-semibold text-foreground">{row.label}</p>
                                        <p className="mt-0.5 text-muted-foreground">
                                            {formatMoney(row.total, moneda, locale)} · {row.count}{' '}
                                            {labels.sales}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {labels.ticketAvg}: {formatMoney(ticket, moneda, locale)}
                                        </p>
                                    </div>
                                );
                            }}
                        />
                        <Bar dataKey="total" radius={[8, 8, 0, 0]} maxBarSize={48}>
                            {data.map((entry) => (
                                <Cell
                                    key={entry.month}
                                    fill={
                                        entry.is_current
                                            ? 'url(#dashboardMonthlyBar)'
                                            : 'url(#dashboardMonthlyBarMuted)'
                                    }
                                    className={cn(entry.is_current && 'drop-shadow-sm')}
                                />
                            ))}
                        </Bar>
                    </BarChart>
                )}
            </DashboardChartShell>
        </div>
    );
}
