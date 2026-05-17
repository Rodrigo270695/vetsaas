import {
    Area,
    AreaChart,
    CartesianGrid,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { DashboardChartEmpty } from '@/components/dashboard/dashboard-chart-empty';
import { DashboardChartShell } from '@/components/dashboard/dashboard-chart-shell';
import type { ConsultasPorDiaRow } from '@/pages/dashboard/types';

type Props = {
    data: ConsultasPorDiaRow[];
};

function isEmpty(data: ConsultasPorDiaRow[]): boolean {
    return data.every((row) => row.count === 0);
}

export function DashboardConsultasChart({ data }: Props) {
    if (isEmpty(data)) {
        return <DashboardChartEmpty />;
    }

    return (
        <DashboardChartShell>
            {({ width, height }) => (
                <AreaChart
                    width={width}
                    height={height}
                    data={data}
                    margin={{ top: 12, right: 8, left: 0, bottom: 0 }}
                >
                    <defs>
                        <linearGradient id="dashboardConsultasArea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="var(--chart-2)" stopOpacity={0.35} />
                            <stop offset="100%" stopColor="var(--chart-2)" stopOpacity={0.02} />
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
                        width={36}
                        allowDecimals={false}
                    />
                    <Tooltip
                        content={({ active, payload }) => {
                            if (!active || !payload?.length) {
                                return null;
                            }

                            const row = payload[0].payload as ConsultasPorDiaRow;

                            return (
                                <div className="rounded-lg border border-sky-200/60 bg-popover px-3 py-2 text-xs shadow-lg">
                                    <p className="font-semibold text-foreground">{row.label}</p>
                                    <p className="mt-0.5 text-muted-foreground">{row.count}</p>
                                </div>
                            );
                        }}
                    />
                    <Area
                        type="monotone"
                        dataKey="count"
                        stroke="var(--chart-2)"
                        strokeWidth={2.5}
                        fill="url(#dashboardConsultasArea)"
                        dot={{ r: 3, fill: 'var(--chart-2)', strokeWidth: 0 }}
                        activeDot={{ r: 5, fill: 'var(--chart-2)' }}
                    />
                </AreaChart>
            )}
        </DashboardChartShell>
    );
}
