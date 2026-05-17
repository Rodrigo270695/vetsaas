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
import type { VentasPorDiaRow } from '@/pages/dashboard/types';

type Props = {
    data: VentasPorDiaRow[];
    moneda: string;
    locale: string;
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

function isEmpty(data: VentasPorDiaRow[]): boolean {
    return data.every((row) => row.total === 0 && row.count === 0);
}

export function DashboardSalesChart({ data, moneda, locale }: Props) {
    if (isEmpty(data)) {
        return <DashboardChartEmpty />;
    }

    return (
        <DashboardChartShell>
            {({ width, height }) => (
                <BarChart
                    width={width}
                    height={height}
                    data={data}
                    margin={{ top: 12, right: 8, left: 0, bottom: 0 }}
                >
                    <defs>
                        <linearGradient id="dashboardSalesBar" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="var(--brand-500)" stopOpacity={0.95} />
                            <stop offset="100%" stopColor="var(--brand-700)" stopOpacity={0.85} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid stroke="var(--border)" strokeOpacity={0.45} strokeDasharray="4 4" vertical={false} />
                    <XAxis
                        dataKey="label"
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                        tickLine={false}
                        axisLine={false}
                        interval={0}
                        angle={-22}
                        textAnchor="end"
                        height={48}
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

                            const row = payload[0].payload as VentasPorDiaRow;

                            return (
                                <div className="rounded-lg border border-brand-200/60 bg-popover px-3 py-2 text-xs shadow-lg">
                                    <p className="font-semibold text-foreground">{row.label}</p>
                                    <p className="mt-0.5 text-muted-foreground">
                                        {formatMoney(row.total, moneda, locale)} · {row.count}
                                    </p>
                                </div>
                            );
                        }}
                    />
                    <Bar dataKey="total" radius={[8, 8, 0, 0]} maxBarSize={44}>
                        {data.map((entry) => (
                            <Cell
                                key={entry.date}
                                fill={entry.total > 0 ? 'url(#dashboardSalesBar)' : 'var(--muted)'}
                            />
                        ))}
                    </Bar>
                </BarChart>
            )}
        </DashboardChartShell>
    );
}
