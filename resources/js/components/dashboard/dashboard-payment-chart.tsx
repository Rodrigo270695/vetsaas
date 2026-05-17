import { Cell, Legend, Pie, PieChart, Tooltip } from 'recharts';
import { CHART_COLORS } from '@/components/dashboard/chart-colors';
import { DashboardChartEmpty } from '@/components/dashboard/dashboard-chart-empty';
import { DashboardChartShell } from '@/components/dashboard/dashboard-chart-shell';
import type { VentasPorMetodoRow } from '@/pages/dashboard/types';

type Props = {
    data: VentasPorMetodoRow[];
    metodoLabel: (metodo: string) => string;
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

export function DashboardPaymentChart({ data, metodoLabel, moneda, locale }: Props) {
    const chartData = data.map((row) => ({
        ...row,
        name: metodoLabel(row.metodo),
    }));

    const total = chartData.reduce((sum, row) => sum + row.total, 0);

    if (total === 0) {
        return <DashboardChartEmpty />;
    }

    return (
        <DashboardChartShell>
            {({ width, height }) => (
                <PieChart width={width} height={height}>
                    <Pie
                        data={chartData}
                        dataKey="total"
                        nameKey="name"
                        cx="50%"
                        cy="44%"
                        innerRadius={54}
                        outerRadius={78}
                        paddingAngle={2}
                        stroke="var(--card)"
                        strokeWidth={2}
                    >
                        {chartData.map((_, index) => (
                            <Cell
                                key={`pay-${index}`}
                                fill={CHART_COLORS[index % CHART_COLORS.length]}
                            />
                        ))}
                    </Pie>
                    <Tooltip
                        content={({ active, payload }) => {
                            if (!active || !payload?.length) {
                                return null;
                            }

                            const item = payload[0].payload as VentasPorMetodoRow & { name: string };

                            return (
                                <div className="rounded-lg border border-emerald-200/50 bg-popover px-3 py-2 text-xs shadow-lg">
                                    <p className="font-semibold text-foreground">{item.name}</p>
                                    <p className="mt-0.5 text-muted-foreground">
                                        {formatMoney(item.total, moneda, locale)} · {item.count}
                                    </p>
                                </div>
                            );
                        }}
                    />
                    <Legend
                        verticalAlign="bottom"
                        height={40}
                        formatter={(value: string) => (
                            <span className="text-xs font-medium text-muted-foreground">{value}</span>
                        )}
                    />
                </PieChart>
            )}
        </DashboardChartShell>
    );
}
