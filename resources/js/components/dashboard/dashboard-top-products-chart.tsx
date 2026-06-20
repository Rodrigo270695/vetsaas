import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { CHART_COLORS } from '@/components/dashboard/chart-colors';
import { DashboardChartEmpty } from '@/components/dashboard/dashboard-chart-empty';
import { DashboardChartShell } from '@/components/dashboard/dashboard-chart-shell';
import type { TopProductoRow } from '@/pages/dashboard/types';

type Props = {
    data: TopProductoRow[];
    moneda: string;
    locale: string;
    qtyLabel: string;
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

function truncateLabel(value: string, max = 28): string {
    return value.length > max ? `${value.slice(0, max - 1)}…` : value;
}

export function DashboardTopProductsChart({ data, moneda, locale, qtyLabel }: Props) {
    const chartData = data.map((row) => ({
        ...row,
        shortName: truncateLabel(row.nombre),
    }));

    if (chartData.length === 0 || chartData.every((row) => row.total === 0)) {
        return <DashboardChartEmpty />;
    }

    return (
        <DashboardChartShell>
            {({ width, height }) => (
                <BarChart
                    width={width}
                    height={height}
                    data={chartData}
                    layout="vertical"
                    margin={{ top: 4, right: 12, left: 4, bottom: 0 }}
                >
                    <CartesianGrid
                        stroke="var(--border)"
                        strokeOpacity={0.45}
                        strokeDasharray="4 4"
                        horizontal={false}
                    />
                    <XAxis
                        type="number"
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={(v: number) =>
                            new Intl.NumberFormat(locale, {
                                notation: 'compact',
                                maximumFractionDigits: 1,
                            }).format(v)
                        }
                    />
                    <YAxis
                        type="category"
                        dataKey="shortName"
                        width={108}
                        tick={{ fontSize: 10, fill: 'var(--muted-foreground)' }}
                        tickLine={false}
                        axisLine={false}
                    />
                    <Tooltip
                        cursor={{ fill: 'var(--muted)', opacity: 0.35 }}
                        content={({ active, payload }) => {
                            if (!active || !payload?.length) {
                                return null;
                            }

                            const row = payload[0].payload as TopProductoRow;

                            return (
                                <div className="rounded-lg border border-emerald-200/50 bg-popover px-3 py-2 text-xs shadow-lg">
                                    <p className="font-semibold text-foreground">{row.nombre}</p>
                                    <p className="mt-0.5 text-muted-foreground">
                                        {formatMoney(row.total, moneda, locale)} · {row.cantidad}{' '}
                                        {qtyLabel}
                                    </p>
                                </div>
                            );
                        }}
                    />
                    <Bar dataKey="total" radius={[0, 6, 6, 0]} maxBarSize={22}>
                        {chartData.map((_, index) => (
                            <Cell
                                key={`prod-${index}`}
                                fill={CHART_COLORS[index % CHART_COLORS.length]}
                            />
                        ))}
                    </Bar>
                </BarChart>
            )}
        </DashboardChartShell>
    );
}
