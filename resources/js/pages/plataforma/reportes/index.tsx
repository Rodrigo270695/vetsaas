import { Head } from '@inertiajs/react';
import {
    BarChart3,
    Flame,
    Gift,
    MapPin,
    Megaphone,
    TrendingUp,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { type ReactNode, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Pie,
    PieChart,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { CHART_COLORS } from '@/components/dashboard/chart-colors';
import { DashboardChartCard } from '@/components/dashboard/dashboard-chart-card';
import { DashboardChartEmpty } from '@/components/dashboard/dashboard-chart-empty';
import { DashboardChartShell } from '@/components/dashboard/dashboard-chart-shell';
import {
    DashboardKpiGrid,
    type DashboardKpiItem,
} from '@/components/dashboard/dashboard-kpi-grid';
import { PageHeader, StatBadge } from '@/components/data-page';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';

type GeoCount = { id: number | null; name: string; count: number };
type HeatCell = GeoCount & { intensity: number };
type SegmentBlock = {
    total: number;
    con_ubicacion: number;
    sin_ubicacion: number;
    por_departamento: GeoCount[];
    por_provincia: GeoCount[];
    heatmap_departamentos: HeatCell[];
    top_departamentos: GeoCount[];
};

type Snapshot = {
    generated_at: string;
    kpis: {
        total_vivos: number;
        paid: number;
        free: number;
        cancelled: number;
        paid_sin_ubicacion: number;
        free_sin_ubicacion: number;
        pct_paid: number;
        pct_free: number;
    };
    insights: {
        top_paid_departamento: string | null;
        top_free_departamento: string | null;
        oportunidad_ads: string | null;
        cobertura_geo_pct: number;
    };
    paid: SegmentBlock;
    free: SegmentBlock;
    comparativo_departamentos: Array<{
        name: string;
        paid: number;
        free: number;
        total: number;
    }>;
    flujo_suscripciones: {
        labels: string[];
        values: number[];
        total: number;
    };
    crecimiento_mensual: Array<{
        month: string;
        label: string;
        paid: number;
        free: number;
    }>;
    ingresos_mensuales: Array<{
        month: string;
        label: string;
        total: number;
        count: number;
    }>;
    mix_planes: Array<{ codigo: string; nombre: string; count: number }>;
    canales: Array<{
        canal: string;
        paid: number;
        free: number;
        total: number;
    }>;
    estados_tenant: Array<{ estado: string; count: number }>;
};

type Props = { snapshot: Snapshot };

const PAID_COLOR = 'var(--chart-1)';
const FREE_COLOR = 'var(--chart-3)';

function formatMoney(value: number): string {
    try {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN',
            maximumFractionDigits: 0,
        }).format(value);
    } catch {
        return `S/ ${value.toFixed(0)}`;
    }
}

function heatBg(intensity: number): string {
    const alpha = 0.12 + intensity * 0.78;
    return `color-mix(in oklab, var(--chart-1) ${Math.round(alpha * 100)}%, transparent)`;
}

function HeatmapGrid({
    cells,
    emptyLabel,
}: {
    cells: HeatCell[];
    emptyLabel: string;
}) {
    if (cells.length === 0) {
        return <DashboardChartEmpty message={emptyLabel} />;
    }

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
            {cells.map((cell) => (
                <div
                    key={`${cell.name}-${cell.id ?? 'x'}`}
                    className="rounded-lg border border-border/60 px-3 py-2.5 shadow-sm"
                    style={{ background: heatBg(cell.intensity) }}
                    title={`${cell.name}: ${cell.count}`}
                >
                    <p className="truncate text-xs font-medium text-foreground">
                        {cell.name}
                    </p>
                    <p className="mt-1 text-lg font-semibold tabular-nums text-foreground">
                        {cell.count}
                    </p>
                </div>
            ))}
        </div>
    );
}

function HorizontalDeptChart({
    data,
    emptyLabel,
}: {
    data: GeoCount[];
    emptyLabel: string;
}) {
    if (data.length === 0) {
        return <DashboardChartEmpty message={emptyLabel} />;
    }

    const chartData = data.slice(0, 12);

    return (
        <DashboardChartShell height={Math.max(220, chartData.length * 28)}>
            {({ width, height }) => (
                <BarChart
                    width={width}
                    height={height}
                    data={chartData}
                    layout="vertical"
                    margin={{ top: 4, right: 16, left: 8, bottom: 4 }}
                >
                    <CartesianGrid
                        strokeDasharray="3 3"
                        horizontal={false}
                        className="stroke-border/50"
                    />
                    <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11 }} />
                    <YAxis
                        type="category"
                        dataKey="name"
                        width={110}
                        tick={{ fontSize: 11 }}
                    />
                    <Tooltip
                        content={({ active, payload }) => {
                            if (!active || !payload?.length) {
                                return null;
                            }
                            const row = payload[0].payload as GeoCount;
                            return (
                                <div className="rounded-lg border bg-popover px-3 py-2 text-xs shadow-lg">
                                    <p className="font-semibold">{row.name}</p>
                                    <p className="text-muted-foreground">{row.count}</p>
                                </div>
                            );
                        }}
                    />
                    <Bar dataKey="count" radius={[0, 4, 4, 0]} fill={PAID_COLOR} />
                </BarChart>
            )}
        </DashboardChartShell>
    );
}

function SegmentSection({
    title,
    hint,
    icon: Icon,
    accent,
    block,
    emptyLabel,
    tableLabels,
}: {
    title: string;
    hint: string;
    icon: LucideIcon;
    accent: 'emerald' | 'sky';
    block: SegmentBlock;
    emptyLabel: string;
    tableLabels: { departamento: string; total: string; pct: string };
}) {
    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="flex items-center gap-2 text-lg font-semibold tracking-tight">
                        <Icon className="size-5" aria-hidden />
                        {title}
                    </h2>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{hint}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <StatBadge label="Total" value={String(block.total)} />
                    <StatBadge
                        label="Geo"
                        value={String(block.con_ubicacion)}
                        variant="success"
                    />
                    <StatBadge
                        label="Sin geo"
                        value={String(block.sin_ubicacion)}
                        variant={block.sin_ubicacion > 0 ? 'warning' : 'default'}
                    />
                </div>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <DashboardChartCard
                    title={title}
                    description="Top departamentos"
                    icon={BarChart3}
                    accent={accent}
                >
                    <HorizontalDeptChart
                        data={block.por_departamento}
                        emptyLabel={emptyLabel}
                    />
                </DashboardChartCard>

                <DashboardChartCard
                    title="Puntos calientes"
                    description="Mapa de calor por departamento"
                    icon={Flame}
                    accent={accent === 'emerald' ? 'amber' : 'violet'}
                >
                    <HeatmapGrid
                        cells={block.heatmap_departamentos}
                        emptyLabel={emptyLabel}
                    />
                </DashboardChartCard>
            </div>

            {block.top_departamentos.length > 0 ? (
                <div className="overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2.5 font-medium">
                                    {tableLabels.departamento}
                                </th>
                                <th className="px-4 py-2.5 font-medium tabular-nums">
                                    {tableLabels.total}
                                </th>
                                <th className="px-4 py-2.5 font-medium">
                                    {tableLabels.pct}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {block.top_departamentos.map((row) => {
                                const pct =
                                    block.total > 0
                                        ? Math.round((row.count / block.total) * 1000) /
                                          10
                                        : 0;
                                return (
                                    <tr
                                        key={`top-${row.name}`}
                                        className="border-b border-border/50 last:border-0"
                                    >
                                        <td className="px-4 py-2.5 font-medium">
                                            {row.name}
                                        </td>
                                        <td className="px-4 py-2.5 tabular-nums">
                                            {row.count}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <div className="flex items-center gap-2">
                                                <div className="h-1.5 w-24 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className={cn(
                                                            'h-full rounded-full',
                                                            accent === 'emerald'
                                                                ? 'bg-emerald-500'
                                                                : 'bg-sky-500',
                                                        )}
                                                        style={{
                                                            width: `${Math.min(100, pct)}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="tabular-nums text-muted-foreground">
                                                    {pct}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            ) : null}
        </section>
    );
}

export default function Index({ snapshot }: Props) {
    const { t } = useTranslation(['plataforma-reportes', 'common']);
    const { kpis, insights } = snapshot;

    const kpiItems = useMemo<DashboardKpiItem[]>(
        () => [
            {
                key: 'total',
                label: t('kpis.total'),
                value: kpis.total_vivos,
                icon: MapPin,
                accent: 'brand',
            },
            {
                key: 'paid',
                label: t('kpis.paid'),
                value: kpis.paid,
                icon: Wallet,
                accent: 'emerald',
                highlight: true,
            },
            {
                key: 'free',
                label: t('kpis.free'),
                value: kpis.free,
                icon: Gift,
                accent: 'sky',
            },
            {
                key: 'pct',
                label: t('kpis.pct_paid'),
                value: `${kpis.pct_paid}%`,
                icon: TrendingUp,
                accent: 'amber',
            },
        ],
        [kpis, t],
    );

    const flujoData = snapshot.flujo_suscripciones.labels.map((label, i) => ({
        name: label,
        count: snapshot.flujo_suscripciones.values[i] ?? 0,
    }));

    const mixData = snapshot.mix_planes.map((p) => ({
        name: p.nombre,
        count: p.count,
    }));

    const estadosData = snapshot.estados_tenant.map((e) => ({
        name: e.estado,
        count: e.count,
    }));

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('subtitle')}
                    actions={
                        <StatBadge
                            label="Geo"
                            value={`${insights.cobertura_geo_pct}%`}
                            variant={
                                insights.cobertura_geo_pct >= 70
                                    ? 'success'
                                    : 'warning'
                            }
                            icon={MapPin}
                        />
                    }
                />

                <DashboardKpiGrid items={kpiItems} />

                <div className="grid gap-3 rounded-xl border border-amber-200/60 bg-gradient-to-br from-amber-50/80 to-card p-4 shadow-sm dark:from-amber-950/20 md:grid-cols-4">
                    <Insight
                        label={t('insights.top_paid')}
                        value={
                            insights.top_paid_departamento ?? t('insights.none')
                        }
                        icon={Wallet}
                    />
                    <Insight
                        label={t('insights.top_free')}
                        value={
                            insights.top_free_departamento ?? t('insights.none')
                        }
                        icon={Gift}
                    />
                    <Insight
                        label={t('insights.ads')}
                        value={insights.oportunidad_ads ?? t('insights.none')}
                        icon={Megaphone}
                        emphasize
                    />
                    <Insight
                        label={t('insights.cobertura')}
                        value={`${insights.cobertura_geo_pct}%`}
                        icon={MapPin}
                    />
                </div>

                <SegmentSection
                    title={t('sections.paid')}
                    hint={t('sections.paid_hint')}
                    icon={Wallet}
                    accent="emerald"
                    block={snapshot.paid}
                    emptyLabel={t('chart.empty')}
                    tableLabels={{
                        departamento: t('table.departamento'),
                        total: t('table.total'),
                        pct: t('table.pct'),
                    }}
                />

                <SegmentSection
                    title={t('sections.free')}
                    hint={t('sections.free_hint')}
                    icon={Gift}
                    accent="sky"
                    block={snapshot.free}
                    emptyLabel={t('chart.empty')}
                    tableLabels={{
                        departamento: t('table.departamento'),
                        total: t('table.total'),
                        pct: t('table.pct'),
                    }}
                />

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            {t('sections.comparativo')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('sections.comparativo_hint')}
                        </p>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-2">
                        <DashboardChartCard
                            title={t('sections.comparativo')}
                            description={t('sections.comparativo_hint')}
                            icon={BarChart3}
                            accent="brand"
                        >
                            {snapshot.comparativo_departamentos.length === 0 ? (
                                <DashboardChartEmpty message={t('chart.empty')} />
                            ) : (
                                <DashboardChartShell height={320}>
                                    {({ width, height }) => (
                                        <BarChart
                                            width={width}
                                            height={height}
                                            data={snapshot.comparativo_departamentos.slice(
                                                0,
                                                12,
                                            )}
                                            margin={{
                                                top: 8,
                                                right: 8,
                                                left: 0,
                                                bottom: 48,
                                            }}
                                        >
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                className="stroke-border/50"
                                            />
                                            <XAxis
                                                dataKey="name"
                                                interval={0}
                                                angle={-35}
                                                textAnchor="end"
                                                height={60}
                                                tick={{ fontSize: 10 }}
                                            />
                                            <YAxis
                                                allowDecimals={false}
                                                tick={{ fontSize: 11 }}
                                            />
                                            <Tooltip />
                                            <Legend />
                                            <Bar
                                                dataKey="paid"
                                                name={t('chart.paid')}
                                                stackId="a"
                                                fill={PAID_COLOR}
                                            />
                                            <Bar
                                                dataKey="free"
                                                name={t('chart.free')}
                                                stackId="a"
                                                fill={FREE_COLOR}
                                                radius={[4, 4, 0, 0]}
                                            />
                                        </BarChart>
                                    )}
                                </DashboardChartShell>
                            )}
                        </DashboardChartCard>

                        <DashboardChartCard
                            title={t('sections.flujo')}
                            description={t('sections.flujo_hint')}
                            icon={TrendingUp}
                            accent="violet"
                        >
                            {flujoData.every((r) => r.count === 0) ? (
                                <DashboardChartEmpty message={t('chart.empty')} />
                            ) : (
                                <DashboardChartShell>
                                    {({ width, height }) => (
                                        <BarChart
                                            width={width}
                                            height={height}
                                            data={flujoData}
                                            margin={{ top: 8, right: 8, left: 0, bottom: 8 }}
                                        >
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                className="stroke-border/50"
                                            />
                                            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                                            <Tooltip />
                                            <Bar
                                                dataKey="count"
                                                fill="var(--chart-4)"
                                                radius={[4, 4, 0, 0]}
                                            />
                                        </BarChart>
                                    )}
                                </DashboardChartShell>
                            )}
                        </DashboardChartCard>
                    </div>
                </section>

                <section className="grid gap-4 xl:grid-cols-2">
                    <DashboardChartCard
                        title={t('sections.crecimiento')}
                        description={t('sections.crecimiento_hint')}
                        icon={TrendingUp}
                        accent="sky"
                    >
                        <DashboardChartShell>
                            {({ width, height }) => (
                                <AreaChart
                                    width={width}
                                    height={height}
                                    data={snapshot.crecimiento_mensual}
                                    margin={{ top: 8, right: 8, left: 0, bottom: 8 }}
                                >
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        className="stroke-border/50"
                                    />
                                    <XAxis dataKey="label" tick={{ fontSize: 10 }} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                                    <Tooltip />
                                    <Legend />
                                    <Area
                                        type="monotone"
                                        dataKey="paid"
                                        name={t('chart.paid')}
                                        stroke={PAID_COLOR}
                                        fill={PAID_COLOR}
                                        fillOpacity={0.25}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="free"
                                        name={t('chart.free')}
                                        stroke={FREE_COLOR}
                                        fill={FREE_COLOR}
                                        fillOpacity={0.2}
                                    />
                                </AreaChart>
                            )}
                        </DashboardChartShell>
                    </DashboardChartCard>

                    <DashboardChartCard
                        title={t('sections.ingresos')}
                        description={t('sections.ingresos_hint')}
                        icon={Wallet}
                        accent="emerald"
                    >
                        <DashboardChartShell>
                            {({ width, height }) => (
                                <AreaChart
                                    width={width}
                                    height={height}
                                    data={snapshot.ingresos_mensuales}
                                    margin={{ top: 8, right: 8, left: 0, bottom: 8 }}
                                >
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        className="stroke-border/50"
                                    />
                                    <XAxis dataKey="label" tick={{ fontSize: 10 }} />
                                    <YAxis tick={{ fontSize: 11 }} />
                                    <Tooltip
                                        formatter={(value) =>
                                            formatMoney(Number(value ?? 0))
                                        }
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="total"
                                        name="PEN"
                                        stroke="var(--chart-2)"
                                        fill="var(--chart-2)"
                                        fillOpacity={0.28}
                                    />
                                </AreaChart>
                            )}
                        </DashboardChartShell>
                    </DashboardChartCard>
                </section>

                <section className="grid gap-4 md:grid-cols-3">
                    <DashboardChartCard
                        title={t('sections.planes')}
                        icon={Wallet}
                        accent="emerald"
                    >
                        {mixData.length === 0 ? (
                            <DashboardChartEmpty message={t('chart.empty')} />
                        ) : (
                            <DashboardChartShell>
                                {({ width, height }) => (
                                    <PieChart width={width} height={height}>
                                        <Pie
                                            data={mixData}
                                            dataKey="count"
                                            nameKey="name"
                                            cx="50%"
                                            cy="45%"
                                            innerRadius={48}
                                            outerRadius={72}
                                            paddingAngle={2}
                                        >
                                            {mixData.map((_, i) => (
                                                <Cell
                                                    key={`plan-${i}`}
                                                    fill={
                                                        CHART_COLORS[
                                                            i % CHART_COLORS.length
                                                        ]
                                                    }
                                                />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                        <Legend verticalAlign="bottom" height={36} />
                                    </PieChart>
                                )}
                            </DashboardChartShell>
                        )}
                    </DashboardChartCard>

                    <DashboardChartCard
                        title={t('sections.canales')}
                        icon={Megaphone}
                        accent="amber"
                    >
                        {snapshot.canales.length === 0 ? (
                            <DashboardChartEmpty message={t('chart.empty')} />
                        ) : (
                            <DashboardChartShell>
                                {({ width, height }) => (
                                    <BarChart
                                        width={width}
                                        height={height}
                                        data={snapshot.canales.slice(0, 8)}
                                        margin={{ top: 8, right: 8, left: 0, bottom: 32 }}
                                    >
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            className="stroke-border/50"
                                        />
                                        <XAxis
                                            dataKey="canal"
                                            tick={{ fontSize: 10 }}
                                            interval={0}
                                            angle={-25}
                                            textAnchor="end"
                                        />
                                        <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                                        <Tooltip />
                                        <Legend />
                                        <Bar
                                            dataKey="paid"
                                            name={t('chart.paid')}
                                            stackId="c"
                                            fill={PAID_COLOR}
                                        />
                                        <Bar
                                            dataKey="free"
                                            name={t('chart.free')}
                                            stackId="c"
                                            fill={FREE_COLOR}
                                            radius={[4, 4, 0, 0]}
                                        />
                                    </BarChart>
                                )}
                            </DashboardChartShell>
                        )}
                    </DashboardChartCard>

                    <DashboardChartCard
                        title={t('sections.estados')}
                        icon={MapPin}
                        accent="slate"
                    >
                        {estadosData.length === 0 ? (
                            <DashboardChartEmpty message={t('chart.empty')} />
                        ) : (
                            <DashboardChartShell>
                                {({ width, height }) => (
                                    <PieChart width={width} height={height}>
                                        <Pie
                                            data={estadosData}
                                            dataKey="count"
                                            nameKey="name"
                                            cx="50%"
                                            cy="45%"
                                            innerRadius={48}
                                            outerRadius={72}
                                            paddingAngle={2}
                                        >
                                            {estadosData.map((_, i) => (
                                                <Cell
                                                    key={`est-${i}`}
                                                    fill={
                                                        CHART_COLORS[
                                                            i % CHART_COLORS.length
                                                        ]
                                                    }
                                                />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                        <Legend verticalAlign="bottom" height={36} />
                                    </PieChart>
                                )}
                            </DashboardChartShell>
                        )}
                    </DashboardChartCard>
                </section>
            </div>
        </>
    );
}

function Insight({
    label,
    value,
    icon: Icon,
    emphasize = false,
}: {
    label: string;
    value: string;
    icon: LucideIcon;
    emphasize?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-lg border border-border/50 bg-card/70 p-3',
                emphasize && 'ring-1 ring-amber-400/40',
            )}
        >
            <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                <Icon className="size-3.5" aria-hidden />
                {label}
            </div>
            <p className="mt-1.5 truncate text-sm font-semibold text-foreground">
                {value}
            </p>
        </div>
    );
}

Index.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Reportes', href: '/plataforma/reportes' },
        ]}
    >
        {page}
    </AppLayout>
);
