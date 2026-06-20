import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { DashboardChartEmpty } from '@/components/dashboard/dashboard-chart-empty';
import { DashboardChartShell } from '@/components/dashboard/dashboard-chart-shell';
import type { NuevosClientesMensualRow } from '@/pages/dashboard/types';

type Props = {
    data: NuevosClientesMensualRow[];
    showPacientes: boolean;
    showPropietarios: boolean;
    labels: {
        pacientes: string;
        propietarios: string;
    };
};

function isEmpty(data: NuevosClientesMensualRow[]): boolean {
    return data.every((row) => row.pacientes === 0 && row.propietarios === 0);
}

export function DashboardClientesMensualesChart({
    data,
    showPacientes,
    showPropietarios,
    labels,
}: Props) {
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
                        width={36}
                        allowDecimals={false}
                    />
                    <Tooltip
                        content={({ active, payload, label }) => {
                            if (!active || !payload?.length) {
                                return null;
                            }

                            return (
                                <div className="rounded-lg border border-sky-200/60 bg-popover px-3 py-2 text-xs shadow-lg">
                                    <p className="font-semibold text-foreground">{label}</p>
                                    {payload.map((entry) => (
                                        <p key={entry.dataKey} className="mt-0.5 text-muted-foreground">
                                            {entry.name}: {entry.value}
                                        </p>
                                    ))}
                                </div>
                            );
                        }}
                    />
                    <Legend
                        verticalAlign="top"
                        height={28}
                        formatter={(value: string) => (
                            <span className="text-xs font-medium text-muted-foreground">{value}</span>
                        )}
                    />
                    {showPacientes && (
                        <Bar
                            dataKey="pacientes"
                            name={labels.pacientes}
                            fill="var(--chart-1)"
                            radius={[6, 6, 0, 0]}
                            maxBarSize={28}
                        />
                    )}
                    {showPropietarios && (
                        <Bar
                            dataKey="propietarios"
                            name={labels.propietarios}
                            fill="var(--chart-2)"
                            radius={[6, 6, 0, 0]}
                            maxBarSize={28}
                        />
                    )}
                </BarChart>
            )}
        </DashboardChartShell>
    );
}
