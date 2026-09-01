import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    Clock,
    PauseCircle,
    RefreshCw,
    Repeat,
    Sparkles,
    Timer,
    TrendingDown,
} from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { formatMoney } from '@/pages/reportes/components/reporte-format';
import type { Paginated } from '@/types';

const ROUTE_URL = '/plataforma/embudo';
const DEFAULT_PER_PAGE = 15;

type FunnelRow = {
    id: string;
    tenant: {
        id: string;
        slug: string;
        nombre: string;
        estado: string | null;
    };
    plan: string;
    plan_codigo: string | null;
    estado: string;
    ciclo: string;
    precio_pactado: number;
    mrr: number;
    trial_ends_at: string | null;
    grace_ends_at: string | null;
    proximo_cobro_at: string | null;
    cancelled_at: string | null;
};

type Scope =
    | 'atencion'
    | 'trials'
    | 'vence_7d'
    | 'activos'
    | 'grace'
    | 'suspended'
    | 'cancelados_30d'
    | 'cobro_7d';

type PageFilters = {
    search: string;
    scope: Scope;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};

type Props = {
    items?: Paginated<FunnelRow>;
    filters?: {
        search: string;
        scope: Scope;
        per_page: number;
    };
    stats?: {
        trials: number;
        vence_7d: number;
        activos: number;
        grace: number;
        suspended: number;
        cancelados_30d: number;
        cobro_7d: number;
        mrr: number;
        cash_month: number;
        conversion_cohort: number;
        conversion_converted: number;
        conversion_pct: number | null;
        fallidos_7d: number;
        pendientes: number;
        currency: string;
    };
};

const EMPTY_PAGINATED: Paginated<FunnelRow> = {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_PER_PAGE,
    from: null,
    to: null,
    total: 0,
    path: ROUTE_URL,
    first_page_url: null,
    last_page_url: null,
    next_page_url: null,
    prev_page_url: null,
    links: [],
};

const SCOPES: Scope[] = [
    'atencion',
    'trials',
    'vence_7d',
    'activos',
    'grace',
    'suspended',
    'cancelados_30d',
    'cobro_7d',
];

const formatWhen = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString('es-PE', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const keyDate = (
    row: FunnelRow,
    t: (key: string) => string,
): { label: string; value: string } => {
    if (row.estado === 'trial') {
        return { label: t('fecha.trial'), value: formatWhen(row.trial_ends_at) };
    }
    if (row.estado === 'grace') {
        return { label: t('fecha.grace'), value: formatWhen(row.grace_ends_at) };
    }
    if (row.estado === 'cancelled') {
        return { label: t('fecha.cancel'), value: formatWhen(row.cancelled_at) };
    }

    return { label: t('fecha.cobro'), value: formatWhen(row.proximo_cobro_at) };
};

export default function PlataformaEmbudoIndex({
    items: paginated = EMPTY_PAGINATED,
    filters = { search: '', scope: 'atencion', per_page: DEFAULT_PER_PAGE },
    stats = {
        trials: 0,
        vence_7d: 0,
        activos: 0,
        grace: 0,
        suspended: 0,
        cancelados_30d: 0,
        cobro_7d: 0,
        mrr: 0,
        cash_month: 0,
        conversion_cohort: 0,
        conversion_converted: 0,
        conversion_pct: null,
        fallidos_7d: 0,
        pendientes: 0,
        currency: 'PEN',
    },
}: Props) {
    const { t, i18n } = useTranslation('plataforma-embudo');
    const locale = i18n.language === 'en' ? 'en-US' : 'es-PE';

    const initialFilters: PageFilters = {
        search: filters.search,
        scope: filters.scope,
        per_page: filters.per_page,
        sort: null,
        direction: null,
    };

    const { search, setSearch, isLoading, setPerPage, applyFilter } =
        useDataTablePage<{ scope: string }>({
            routeUrl: ROUTE_URL,
            initialFilters,
            only: ['items', 'filters', 'stats'],
        });

    const { secondsSince, isRefreshing, refresh } = useAutoRefresh({
        only: ['items', 'filters', 'stats'],
        enabled: true,
        intervalMs: 60_000,
        busy: isLoading,
    });

    const columns = useMemo<DataTableColumn<FunnelRow>[]>(
        () => [
            {
                key: 'tenant',
                header: t('columns.tenant'),
                cell: (row) => (
                    <Link
                        href={`/plataforma/suscripciones?search=${encodeURIComponent(row.tenant.slug)}`}
                        className="min-w-0 hover:underline"
                    >
                        <p className="truncate font-medium text-foreground">
                            {row.tenant.nombre}
                        </p>
                        <p className="truncate font-mono text-xs text-muted-foreground">
                            {row.tenant.slug}
                        </p>
                    </Link>
                ),
            },
            {
                key: 'plan',
                header: t('columns.plan'),
                cell: (row) => (
                    <span className="text-sm">
                        {row.plan}
                        <span className="ml-1 text-xs text-muted-foreground">
                            · {row.ciclo}
                        </span>
                    </span>
                ),
            },
            {
                key: 'estado',
                header: t('columns.estado'),
                cell: (row) => {
                    const bad =
                        row.estado === 'suspended' || row.estado === 'cancelled';
                    const warn = row.estado === 'grace' || row.estado === 'trial';

                    return (
                        <Badge
                            variant="outline"
                            className={cn(
                                row.estado === 'active' &&
                                    'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300',
                                warn &&
                                    'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-300',
                                bad &&
                                    'border-destructive/40 bg-destructive/10 text-destructive',
                            )}
                        >
                            {t(`estado.${row.estado}`, { defaultValue: row.estado })}
                        </Badge>
                    );
                },
            },
            {
                key: 'mrr',
                header: t('columns.mrr'),
                align: 'right',
                className: 'tabular-nums',
                cell: (row) => formatMoney(row.mrr, stats.currency, locale),
            },
            {
                key: 'fecha',
                header: t('columns.fecha'),
                cell: (row) => {
                    const fecha = keyDate(row, t);

                    return (
                        <div className="text-xs">
                            <p className="text-muted-foreground">{fecha.label}</p>
                            <p className="tabular-nums">{fecha.value}</p>
                        </div>
                    );
                },
            },
        ],
        [locale, stats.currency, t],
    );

    const isEmpty =
        paginated.total === 0 && !filters.search && filters.scope === 'atencion';
    const isFilteredEmpty = paginated.total === 0 && !isEmpty;

    const funnelSteps = [
        { key: 'trials', value: stats.trials, icon: Timer },
        { key: 'activos', value: stats.activos, icon: Repeat },
        { key: 'grace', value: stats.grace, icon: Clock },
        { key: 'suspended', value: stats.suspended, icon: PauseCircle },
        { key: 'cancelados', value: stats.cancelados_30d, icon: TrendingDown },
    ] as const;

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    action={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="cursor-pointer gap-2"
                            disabled={isRefreshing || isLoading}
                            onClick={() => refresh()}
                        >
                            <RefreshCw
                                className={cn(
                                    'size-4',
                                    (isRefreshing || isLoading) && 'animate-spin',
                                )}
                            />
                            {t('refresh')}
                            <span className="text-xs text-muted-foreground">
                                {secondsSince}s
                            </span>
                        </Button>
                    }
                />

                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    {funnelSteps.map((step, index) => (
                        <div
                            key={step.key}
                            className="flex items-center gap-3 rounded-xl border border-border/60 bg-card px-3 py-3"
                        >
                            <step.icon className="size-4 shrink-0 text-muted-foreground" />
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">
                                    {index + 1}. {t(`funnel.${step.key}`)}
                                </p>
                                <p className="text-lg font-semibold tabular-nums">
                                    {step.value}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="flex flex-wrap gap-2">
                    <StatBadge
                        icon={Banknote}
                        label={t('stats.mrr')}
                        value={formatMoney(stats.mrr, stats.currency, locale)}
                        variant="success"
                    />
                    <StatBadge
                        icon={Sparkles}
                        label={t('stats.cash_month')}
                        value={formatMoney(stats.cash_month, stats.currency, locale)}
                        variant="info"
                    />
                    <StatBadge
                        label={t('stats.conversion')}
                        value={
                            stats.conversion_pct === null
                                ? '—'
                                : `${stats.conversion_pct}%`
                        }
                        variant={
                            stats.conversion_pct !== null && stats.conversion_pct < 40
                                ? 'warning'
                                : 'success'
                        }
                    />
                    <StatBadge
                        icon={Timer}
                        label={t('stats.vence_7d')}
                        value={stats.vence_7d}
                        variant={stats.vence_7d > 0 ? 'warning' : 'muted'}
                    />
                    <StatBadge
                        icon={Clock}
                        label={t('stats.cobro_7d')}
                        value={stats.cobro_7d}
                        variant="info"
                    />
                    <StatBadge
                        icon={AlertTriangle}
                        label={t('stats.fallidos_7d')}
                        value={stats.fallidos_7d}
                        variant={stats.fallidos_7d > 0 ? 'danger' : 'muted'}
                    />
                    <StatBadge
                        label={t('stats.pendientes')}
                        value={stats.pendientes}
                        variant={stats.pendientes > 0 ? 'warning' : 'muted'}
                    />
                </div>

                {stats.conversion_cohort > 0 ? (
                    <p className="text-xs text-muted-foreground">
                        {t('stats.conversion_hint', {
                            converted: stats.conversion_converted,
                            cohort: stats.conversion_cohort,
                        })}
                    </p>
                ) : null}

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder={t('search_placeholder')}
                    isSearching={isLoading}
                    searchWrapperClassName="sm:max-w-md"
                >
                    <div className="flex flex-wrap gap-1.5">
                        {SCOPES.map((value) => (
                            <Button
                                key={value}
                                type="button"
                                size="sm"
                                variant={filters.scope === value ? 'default' : 'outline'}
                                className="cursor-pointer"
                                onClick={() => applyFilter({ scope: value })}
                            >
                                {t(`scope.${value}`)}
                            </Button>
                        ))}
                    </div>
                </DataToolbar>

                <p className="text-xs text-muted-foreground">{t('note')}</p>

                {isEmpty ? (
                    <EmptyState
                        icon={Banknote}
                        title={t('empty.title')}
                        description={t('empty.description')}
                    />
                ) : isFilteredEmpty ? (
                    <EmptyState
                        icon={Banknote}
                        title={t('empty.filtered_title')}
                        description={t('empty.filtered_description')}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={paginated.data}
                        rowKey={(row) => row.id}
                        isLoading={isLoading}
                        footer={
                            <DataPagination
                                meta={paginated}
                                onPerPageChange={setPerPage}
                                preservedQuery={{
                                    search: filters.search || undefined,
                                    scope:
                                        filters.scope === 'atencion'
                                            ? undefined
                                            : filters.scope,
                                    per_page: filters.per_page,
                                }}
                            />
                        }
                    />
                )}
            </div>
        </>
    );
}

PlataformaEmbudoIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/operaciones' },
            { title: 'Cobros', href: '/plataforma/suscripciones' },
            { title: 'Embudo SaaS', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
