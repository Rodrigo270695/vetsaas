import { Cell, Legend, Pie, PieChart, Tooltip } from 'recharts';
import { CHART_COLORS } from '@/components/dashboard/chart-colors';
import { DashboardChartEmpty } from '@/components/dashboard/dashboard-chart-empty';
import { DashboardChartShell } from '@/components/dashboard/dashboard-chart-shell';
import type { MascotasPorEspecieRow } from '@/pages/dashboard/types';

type Props = {
    data: MascotasPorEspecieRow[];
    especieLabel: (especie: string) => string;
    countLabel: (count: number) => string;
};

export function DashboardMascotasEspecieChart({
    data,
    especieLabel,
    countLabel,
}: Props) {
    const chartData = data.map((row) => ({
        ...row,
        name: especieLabel(row.especie),
    }));

    const total = chartData.reduce((sum, row) => sum + row.count, 0);

    if (total === 0) {
        return <DashboardChartEmpty />;
    }

    return (
        <DashboardChartShell>
            {({ width, height }) => (
                <PieChart width={width} height={height}>
                    <Pie
                        data={chartData}
                        dataKey="count"
                        nameKey="name"
                        cx="50%"
                        cy="42%"
                        innerRadius={54}
                        outerRadius={80}
                        paddingAngle={2}
                        stroke="var(--card)"
                        strokeWidth={2}
                    >
                        {chartData.map((_, index) => (
                            <Cell
                                key={`especie-${index}`}
                                fill={CHART_COLORS[index % CHART_COLORS.length]}
                            />
                        ))}
                    </Pie>
                    <Tooltip
                        content={({ active, payload }) => {
                            if (!active || !payload?.length) {
                                return null;
                            }

                            const item = payload[0].payload as MascotasPorEspecieRow & {
                                name: string;
                            };
                            const pct = total > 0 ? Math.round((item.count / total) * 100) : 0;

                            return (
                                <div className="rounded-lg border border-sky-200/50 bg-popover px-3 py-2 text-xs shadow-lg">
                                    <p className="font-semibold text-foreground">{item.name}</p>
                                    <p className="mt-0.5 text-muted-foreground">
                                        {countLabel(item.count)} · {pct}%
                                    </p>
                                </div>
                            );
                        }}
                    />
                    <Legend
                        verticalAlign="bottom"
                        height={52}
                        formatter={(value: string, entry) => {
                            const count = (entry.payload as MascotasPorEspecieRow | undefined)?.count;

                            return (
                                <span className="text-xs font-medium text-muted-foreground">
                                    {value}
                                    {typeof count === 'number' ? ` (${count})` : ''}
                                </span>
                            );
                        }}
                    />
                </PieChart>
            )}
        </DashboardChartShell>
    );
}
