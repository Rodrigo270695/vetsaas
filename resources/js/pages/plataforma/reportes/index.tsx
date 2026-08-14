import { Head, Link } from '@inertiajs/react';
import {
    BarChart3,
    Flame,
    Gift,
    MapPin,
    Megaphone,
    TrendingDown,
    TrendingUp,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { type ReactNode, useMemo, useState } from 'react';
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
    Treemap,
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
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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
        churned: number;
        churned_one_payment: number;
        ever_paid: number;
        pct_churned: number;
    };
    insights: {
        top_paid_departamento: string | null;
        top_free_departamento: string | null;
        oportunidad_ads: string | null;
        cobertura_geo_pct: number;
        churned: number;
        churned_one_payment: number;
    };
    paid: SegmentBlock;
    free: SegmentBlock;
    churn: {
        total: number;
        one_payment: number;
        ever_paid: number;
        pct: number;
        rows: Array<{
            tenant_id: string;
            slug: string;
            label: string;
            pagos_count: number;
            last_paid_at: string | null;
            reason: string;
            sub_estado: string | null;
            plan_nombre: string | null;
        }>;
    };
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
    const alpha = 0.18 + intensity * 0.72;
    return `color-mix(in oklab, var(--chart-1) ${Math.round(alpha * 100)}%, transparent)`;
}

type TreemapNodeProps = {
    x?: number;
    y?: number;
    width?: number;
    height?: number;
    name?: string;
    value?: number;
    intensity?: number;
};

function HeatTreemapCell(props: TreemapNodeProps) {
    const { x = 0, y = 0, width = 0, height = 0, name = '', value = 0, intensity = 0.3 } =
        props;

    if (width < 2 || height < 2) {
        return null;
    }

    const showLabel = width > 48 && height > 28;

    return (
        <g>
            <rect
                x={x}
                y={y}
                width={width}
                height={height}
                rx={6}
                style={{
                    fill: heatBg(intensity),
                    stroke: 'var(--border)',
                    strokeWidth: 1.5,
                }}
            />
            {showLabel ? (
                <>
                    <text
                        x={x + 8}
                        y={y + 18}
                        className="fill-foreground"
                        style={{ fontSize: 11, fontWeight: 600 }}
                    >
                        {name.length > 14 ? `${name.slice(0, 12)}…` : name}
                    </text>
                    <text
                        x={x + 8}
                        y={y + 36}
                        className="fill-muted-foreground"
                        style={{ fontSize: 13, fontWeight: 700 }}
                    >
                        {value}
                    </text>
                </>
            ) : null}
        </g>
    );
}

function HeatmapChart({
    cells,
    emptyLabel,
}: {
    cells: HeatCell[];
    emptyLabel: string;
}) {
    if (cells.length === 0) {
        return <DashboardChartEmpty message={emptyLabel} />;
    }

    const data = cells.map((cell) => ({
        name: cell.name,
        size: cell.count,
        intensity: cell.intensity,
    }));

    return (
        <DashboardChartShell height={320}>
            {({ width, height }) => (
                <Treemap
                    width={width}
                    height={height}
                    data={data}
                    dataKey="size"
                    aspectRatio={4 / 3}
                    stroke="var(--border)"
                    content={<HeatTreemapCell />}
                    isAnimationActive={false}
                >
                    <Tooltip
                        content={({ active, payload }) => {
                            if (!active || !payload?.length) {
                                return null;
                            }
                            const row = payload[0].payload as {
                                name?: string;
                                size?: number;
                            };
                            return (
                                <div className="rounded-lg border bg-popover px-3 py-2 text-xs shadow-lg">
                                    <p className="font-semibold">{row.name}</p>
                                    <p className="text-muted-foreground">
                                        {row.size ?? 0} clínicas
                                    </p>
                                </div>
                            );
                        }}
                    />
                </Treemap>
            )}
        </DashboardChartShell>
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
                    description="Mapa de calor por departamento (treemap)"
                    icon={Flame}
                    accent={accent === 'emerald' ? 'amber' : 'violet'}
                >
                    <HeatmapChart
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
    const [showPaid, setShowPaid] = useState(true);
    const [showFree, setShowFree] = useState(false);
    const hasSegmentFilter = showPaid || showFree;

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
                highlight: showPaid,
            },
            {
                key: 'free',
                label: t('kpis.free'),
                value: kpis.free,
                icon: Gift,
                accent: 'sky',
                highlight: showFree,
            },
            {
                key: 'pct',
                label: t('kpis.pct_paid'),
                value: `${kpis.pct_paid}%`,
                icon: TrendingUp,
                accent: 'amber',
            },
            {
                key: 'churned',
                label: t('kpis.churned'),
                value: kpis.churned ?? 0,
                hint: t('kpis.churned_hint', {
                    one: kpis.churned_one_payment ?? 0,
                    pct: kpis.pct_churned ?? 0,
                }),
                icon: TrendingDown,
                accent: 'rose',
                highlight: (kpis.churned ?? 0) > 0,
            },
        ],
        [kpis, showFree, showPaid, t],
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
                    action={
                        <div className="flex flex-wrap items-center gap-2">
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
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/plataforma/reportes/mapa">
                                    {t('mapa.open')}
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-4 rounded-xl border border-border/70 bg-card px-4 py-3 shadow-sm">
                    <p className="text-sm font-medium">{t('filters.label')}</p>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="reportes-filter-paid"
                            checked={showPaid}
                            onCheckedChange={(v) => setShowPaid(v === true)}
                        />
                        <Label
                            htmlFor="reportes-filter-paid"
                            className="cursor-pointer text-sm font-normal"
                        >
                            {t('filters.paid')}
                        </Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="reportes-filter-free"
                            checked={showFree}
                            onCheckedChange={(v) => setShowFree(v === true)}
                        />
                        <Label
                            htmlFor="reportes-filter-free"
                            className="cursor-pointer text-sm font-normal"
                        >
                            {t('filters.free')}
                        </Label>
                    </div>
                    <p className="w-full text-xs text-muted-foreground sm:ml-auto sm:w-auto">
                        {t('filters.hint')}
                    </p>
                </div>

                <DashboardKpiGrid items={kpiItems} />

                <div className="grid gap-3 rounded-xl border border-amber-200/60 bg-gradient-to-br from-amber-50/80 to-card p-4 shadow-sm dark:from-amber-950/20 md:grid-cols-4">
                    {showPaid ? (
                        <Insight
                            label={t('insights.top_paid')}
                            value={
                                insights.top_paid_departamento ??
                                t('insights.none')
                            }
                            icon={Wallet}
                        />
                    ) : null}
                    {showFree ? (
                        <Insight
                            label={t('insights.top_free')}
                            value={
                                insights.top_free_departamento ??
                                t('insights.none')
                            }
                            icon={Gift}
                        />
                    ) : null}
                    {showFree ? (
                        <Insight
                            label={t('insights.ads')}
                            value={
                                insights.oportunidad_ads ?? t('insights.none')
                            }
                            icon={Megaphone}
                            emphasize
                        />
                    ) : null}
                    <Insight
                        label={t('insights.cobertura')}
                        value={`${insights.cobertura_geo_pct}%`}
                        icon={MapPin}
                    />
                    <Insight
                        label={t('insights.churned')}
                        value={String(insights.churned ?? snapshot.churn?.total ?? 0)}
                        icon={TrendingDown}
                        emphasize={(insights.churned ?? 0) > 0}
                    />
                </div>

                {!hasSegmentFilter ? (
                    <div className="rounded-xl border border-dashed border-border/80 bg-muted/20 px-4 py-8 text-center text-sm text-muted-foreground">
                        {t('filters.empty')}
                    </div>
                ) : null}

                {(snapshot.churn?.total ?? 0) > 0 ? (
                    <section className="space-y-4">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 className="flex items-center gap-2 text-lg font-semibold tracking-tight">
                                    <TrendingDown className="size-5 text-rose-600" />
                                    {t('sections.churn')}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('sections.churn_hint')}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <StatBadge
                                    label={t('churn.total')}
                                    value={String(snapshot.churn.total)}
                                    variant="warning"
                                />
                                <StatBadge
                                    label={t('churn.one_payment')}
                                    value={String(snapshot.churn.one_payment)}
                                    variant="warning"
                                />
                                <StatBadge
                                    label={t('churn.pct')}
                                    value={`${snapshot.churn.pct}%`}
                                    variant="default"
                                />
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-xl border border-rose-200/50 bg-card shadow-sm dark:border-rose-900/40">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2.5 font-medium">
                                            {t('churn.col_clinic')}
                                        </th>
                                        <th className="px-4 py-2.5 font-medium tabular-nums">
                                            {t('churn.col_payments')}
                                        </th>
                                        <th className="px-4 py-2.5 font-medium">
                                            {t('churn.col_reason')}
                                        </th>
                                        <th className="px-4 py-2.5 font-medium">
                                            {t('churn.col_plan')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {snapshot.churn.rows.map((row) => (
                                        <tr
                                            key={row.tenant_id}
                                            className="border-b border-border/50 last:border-0"
                                        >
                                            <td className="px-4 py-2.5">
                                                <p className="font-medium">{row.label}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {row.slug}
                                                </p>
                                            </td>
                                            <td className="px-4 py-2.5 tabular-nums">
                                                {t('churn.payments_count', {
                                                    count: row.pagos_count,
                                                })}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                {t(`churn.reasons.${row.reason}`, {
                                                    defaultValue: row.reason,
                                                })}
                                            </td>
                                            <td className="px-4 py-2.5 text-muted-foreground">
                                                {row.plan_nombre ?? '—'}
                                                {row.sub_estado
                                                    ? ` · ${row.sub_estado}`
                                                    : ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ) : null}

                {showPaid ? (
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
                ) : null}

                {showFree ? (
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
                ) : null}

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
                                            {showPaid ? (
                                                <Bar
                                                    dataKey="paid"
                                                    name={t('chart.paid')}
                                                    stackId="a"
                                                    fill={PAID_COLOR}
                                                />
                                            ) : null}
                                            {showFree ? (
                                                <Bar
                                                    dataKey="free"
                                                    name={t('chart.free')}
                                                    stackId="a"
                                                    fill={FREE_COLOR}
                                                    radius={[4, 4, 0, 0]}
                                                />
                                            ) : null}
                                        </BarChart>
                                    )}
                                </DashboardChartShell>
                            )}
                        </DashboardChartCard>

                        {showPaid ? (
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
                        ) : null}
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
                                    {showPaid ? (
                                        <Area
                                            type="monotone"
                                            dataKey="paid"
                                            name={t('chart.paid')}
                                            stroke={PAID_COLOR}
                                            fill={PAID_COLOR}
                                            fillOpacity={0.25}
                                        />
                                    ) : null}
                                    {showFree ? (
                                        <Area
                                            type="monotone"
                                            dataKey="free"
                                            name={t('chart.free')}
                                            stroke={FREE_COLOR}
                                            fill={FREE_COLOR}
                                            fillOpacity={0.2}
                                        />
                                    ) : null}
                                </AreaChart>
                            )}
                        </DashboardChartShell>
                    </DashboardChartCard>

                    {showPaid ? (
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
                    ) : null}
                </section>

                <section className="grid gap-4 md:grid-cols-3">
                    {showPaid ? (
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
                    ) : null}

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
                                        {showPaid ? (
                                            <Bar
                                                dataKey="paid"
                                                name={t('chart.paid')}
                                                stackId="c"
                                                fill={PAID_COLOR}
                                            />
                                        ) : null}
                                        {showFree ? (
                                            <Bar
                                                dataKey="free"
                                                name={t('chart.free')}
                                                stackId="c"
                                                fill={FREE_COLOR}
                                                radius={[4, 4, 0, 0]}
                                            />
                                        ) : null}
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
