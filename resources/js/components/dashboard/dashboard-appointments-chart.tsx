import { Cell, Legend, Pie, PieChart, Tooltip } from 'recharts';
import { CHART_COLORS } from '@/components/dashboard/chart-colors';
import { DashboardChartEmpty } from '@/components/dashboard/dashboard-chart-empty';
import { DashboardChartShell } from '@/components/dashboard/dashboard-chart-shell';
import type { CitasPorEstadoRow } from '@/pages/dashboard/types';

type Props = {
    data: CitasPorEstadoRow[];
    estadoLabel: (estado: string) => string;
};

export function DashboardAppointmentsChart({ data, estadoLabel }: Props) {
    const chartData = data.map((row) => ({
        ...row,
        name: estadoLabel(row.estado),
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
                        cy="44%"
                        innerRadius={58}
                        outerRadius={82}
                        paddingAngle={3}
                        stroke="var(--card)"
                        strokeWidth={2}
                    >
                        {chartData.map((_, index) => (
                            <Cell
                                key={`cell-${index}`}
                                fill={CHART_COLORS[index % CHART_COLORS.length]}
                            />
                        ))}
                    </Pie>
                    <Tooltip
                        content={({ active, payload }) => {
                            if (!active || !payload?.length) {
                                return null;
                            }

                            const item = payload[0].payload as { name: string; count: number };

                            return (
                                <div className="rounded-lg border border-border bg-popover px-3 py-2 text-xs shadow-lg">
                                    <p className="font-semibold text-foreground">{item.name}</p>
                                    <p className="text-muted-foreground">{item.count}</p>
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
