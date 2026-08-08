import { Head, router } from '@inertiajs/react';
import {
    Boxes,
    Building2,
    ChevronDown,
    ChevronRight,
    Gauge,
    PawPrint,
    ReceiptText,
    UserRound,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DataPagination,
    DataToolbar,
    EmptyState,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { PlanLimitEntry, PlanLimitFeature } from '@/types/plan-limits';

const ROUTE_URL = '/plataforma/uso-planes';
const DEFAULT_PER_PAGE = 15;

const FEATURES: PlanLimitFeature[] = [
    'max_pacientes',
    'max_propietarios',
    'max_usuarios',
    'max_productos',
    'max_sedes',
];

const FEATURE_ICONS: Record<
    PlanLimitFeature,
    React.ComponentType<{ className?: string }>
> = {
    max_sedes: Building2,
    max_usuarios: Users,
    max_pacientes: PawPrint,
    max_propietarios: UserRound,
    max_productos: Boxes,
};

type Semaphore = 'unlimited' | 'ok' | 'caution' | 'warning' | 'over';

type ComprobantesQuota = {
    enabled: boolean;
    unlimited: boolean;
    used: number;
    included: number | null;
    remaining: number | null;
    usage_pct: number | null;
    semaphore: Semaphore;
    overage_blocks?: number;
    overage_cost?: string;
};

type UsageRow = {
    tenant: {
        id: string;
        slug: string;
        nombre: string;
        ruc: string | null;
        estado: string | null;
    };
    subscription: {
        id: string;
        estado: string;
        ciclo: string | null;
        plan: {
            nombre: string;
            codigo: string;
            color_hex: string | null;
        };
    };
    limits: Partial<Record<PlanLimitFeature, PlanLimitEntry>> | null;
    comprobantes: ComprobantesQuota | null;
    worst_semaphore: Semaphore;
    features_over: number;
    features_warning: number;
    error: string | null;
};

type PageFilters = {
    search: string;
    semaphore: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};

type PageProps = {
    items: Paginated<UsageRow>;
    filters: {
        search: string;
        semaphore: string;
        per_page: number;
    };
    stats: {
        total: number;
        over: number;
        warning: number;
        caution: number;
        ok: number;
    };
};

const EMPTY_PAGINATED: PageProps['items'] = {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_PER_PAGE,
    total: 0,
    from: null,
    to: null,
    path: ROUTE_URL,
    links: [],
};

const semaphoreStyles: Record<Semaphore, { bar: string; badge: string }> = {
    unlimited: {
        bar: 'bg-sky-500',
        badge: 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
    },
    ok: {
        bar: 'bg-emerald-500',
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    },
    caution: {
        bar: 'bg-yellow-500',
        badge: 'bg-yellow-100 text-yellow-900 dark:bg-yellow-950/40 dark:text-yellow-200',
    },
    warning: {
        bar: 'bg-amber-500',
        badge: 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
    },
    over: {
        bar: 'bg-red-500',
        badge: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-200',
    },
};

function asSemaphore(value: string | undefined): Semaphore {
    if (
        value === 'unlimited' ||
        value === 'ok' ||
        value === 'caution' ||
        value === 'warning' ||
        value === 'over'
    ) {
        return value;
    }

    return 'ok';
}

function UsageMeterCard({
    title,
    icon: Icon,
    entry,
    usageKey,
}: {
    title: string;
    icon: React.ComponentType<{ className?: string }>;
    entry: {
        used: number;
        limit: number | null;
        remaining: number | null;
        unlimited: boolean;
        usage_pct?: number | null;
        semaphore?: string;
        extra?: number;
        is_paid_extra?: boolean;
        precio_mensual?: number;
    };
    usageKey: 'usage' | 'comprobantes';
}) {
    const { t } = useTranslation('plataforma-uso-planes');
    const semaphore = asSemaphore(entry.semaphore);
    const styles = semaphoreStyles[semaphore];
    const progressPct = entry.unlimited
        ? 100
        : entry.limit && entry.limit > 0
          ? Math.min(100, (entry.used / entry.limit) * 100)
          : 0;

    const usageLabel = entry.unlimited
        ? t(`${usageKey}.usage_unlimited` as 'usage.usage_unlimited', {
              used: entry.used,
          })
        : t(`${usageKey}.usage` as 'usage.usage', {
              used: entry.used,
              limit: entry.limit ?? 0,
          });

    return (
        <div className="rounded-xl border border-border/60 bg-card/80 p-4 ring-1 ring-border/20">
            <div className="flex items-start justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted/50">
                        <Icon className="size-4 text-primary" />
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-foreground">
                            {title}
                        </p>
                        <p className="text-xs tabular-nums text-muted-foreground">
                            {usageLabel}
                        </p>
                    </div>
                </div>
                <span
                    className={cn(
                        'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                        styles.badge,
                    )}
                >
                    {t(`usage.semaphore.${semaphore}`)}
                </span>
            </div>

            {!entry.unlimited && entry.limit !== null && entry.limit > 0 ? (
                <div className="mt-3 space-y-1">
                    <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                        <span>
                            {entry.usage_pct !== null && entry.usage_pct !== undefined
                                ? `${entry.usage_pct}%`
                                : `${Math.round(progressPct)}%`}
                        </span>
                        {entry.remaining !== null ? (
                            <span className="tabular-nums">
                                {t(
                                    `${usageKey}.remaining` as 'usage.remaining',
                                    { count: entry.remaining },
                                )}
                            </span>
                        ) : null}
                    </div>
                    <div
                        className="h-1.5 overflow-hidden rounded-full bg-muted/60"
                        role="progressbar"
                        aria-valuenow={entry.used}
                        aria-valuemin={0}
                        aria-valuemax={entry.limit}
                        aria-label={usageLabel}
                    >
                        <div
                            className={cn('h-full rounded-full transition-all', styles.bar)}
                            style={{ width: `${progressPct}%` }}
                        />
                    </div>
                    {usageKey === 'usage' && entry.extra && entry.extra > 0 ? (
                        <p
                            className={cn(
                                'text-[11px]',
                                entry.is_paid_extra
                                    ? 'text-sky-700 dark:text-sky-400'
                                    : 'text-emerald-700 dark:text-emerald-400',
                            )}
                        >
                            {entry.is_paid_extra
                                ? t('usage.includes_paid', {
                                      count: entry.extra,
                                      amount: Number(entry.precio_mensual ?? 0).toFixed(2),
                                  })
                                : t('usage.includes_extra', {
                                      count: entry.extra,
                                  })}
                        </p>
                    ) : null}
                </div>
            ) : (
                <p className="mt-2 text-[11px] text-muted-foreground">
                    {t('usage.unlimited_hint')}
                </p>
            )}
        </div>
    );
}

function TenantUsageCard({ row }: { row: UsageRow }) {
    const { t } = useTranslation('plataforma-uso-planes');
    const [open, setOpen] = useState(
        row.worst_semaphore === 'over' || row.worst_semaphore === 'warning',
    );
    const worst = asSemaphore(row.worst_semaphore);
    const styles = semaphoreStyles[worst];

    const summary = useMemo(() => {
        if (row.error) {
            return t('error_row');
        }
        if (row.features_over > 0) {
            return t('summary_over', { count: row.features_over });
        }
        if (row.features_warning > 0) {
            return t('summary_warning', { count: row.features_warning });
        }
        return t('summary_ok');
    }, [row, t]);

    return (
        <div className="overflow-hidden rounded-xl border border-border/60 bg-card/80 ring-1 ring-border/20">
            <div className="flex w-full items-start gap-3 p-4">
                <button
                    type="button"
                    className="flex min-w-0 flex-1 items-start gap-3 text-left transition-colors"
                    onClick={() => setOpen((v) => !v)}
                    aria-expanded={open}
                >
                    <div className="mt-0.5 text-muted-foreground">
                        {open ? (
                            <ChevronDown className="size-4" />
                        ) : (
                            <ChevronRight className="size-4" />
                        )}
                    </div>
                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-semibold text-foreground">{row.tenant.nombre}</p>
                            <span
                                className={cn(
                                    'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                    styles.badge,
                                )}
                            >
                                {t(`usage.semaphore.${worst}`)}
                            </span>
                            <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                                {t(`estado.${row.subscription.estado}` as 'estado.active', {
                                    defaultValue: row.subscription.estado,
                                })}
                            </span>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {row.tenant.slug}
                            {row.tenant.ruc ? ` · RUC ${row.tenant.ruc}` : ''}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {row.subscription.plan.nombre}
                            {row.subscription.ciclo
                                ? ` · ${t(`ciclo.${row.subscription.ciclo}` as 'ciclo.mensual', {
                                      defaultValue: row.subscription.ciclo,
                                  })}`
                                : null}
                            {' · '}
                            {summary}
                        </p>
                    </div>
                </button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="shrink-0"
                    onClick={() =>
                        router.visit(`/plataforma/tenants/${row.tenant.id}/limites`)
                    }
                >
                    {t('limites_link')}
                </Button>
            </div>

            {open ? (
                <div className="space-y-3 border-t border-border/50 px-4 py-4">
                    {row.error ? (
                        <p className="text-sm text-amber-700 dark:text-amber-300">{row.error}</p>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {FEATURES.map((feature) => {
                                const entry = row.limits?.[feature];
                                if (!entry) return null;
                                const Icon = FEATURE_ICONS[feature];
                                return (
                                    <UsageMeterCard
                                        key={feature}
                                        title={t(`usage.features.${feature}`)}
                                        icon={Icon}
                                        entry={entry}
                                        usageKey="usage"
                                    />
                                );
                            })}
                            {row.comprobantes?.enabled ? (
                                <UsageMeterCard
                                    title={t('comprobantes.title')}
                                    icon={ReceiptText}
                                    entry={{
                                        used: row.comprobantes.used,
                                        limit: row.comprobantes.included,
                                        remaining: row.comprobantes.remaining,
                                        unlimited: row.comprobantes.unlimited,
                                        usage_pct: row.comprobantes.usage_pct,
                                        semaphore: row.comprobantes.semaphore,
                                    }}
                                    usageKey="comprobantes"
                                />
                            ) : null}
                        </div>
                    )}
                    {row.comprobantes?.enabled &&
                    !row.comprobantes.unlimited &&
                    (row.comprobantes.overage_blocks ?? 0) > 0 ? (
                        <p className="text-xs text-amber-700 dark:text-amber-300">
                            {t('comprobantes.overage', {
                                blocks: row.comprobantes.overage_blocks,
                                cost: row.comprobantes.overage_cost ?? '0.00',
                            })}
                        </p>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

export default function Index({
    items: paginated = EMPTY_PAGINATED,
    filters = { search: '', semaphore: 'todos', per_page: DEFAULT_PER_PAGE },
    stats = { total: 0, over: 0, warning: 0, caution: 0, ok: 0 },
}: PageProps) {
    const { t } = useTranslation(['plataforma-uso-planes', 'common']);

    const initialFilters: PageFilters = {
        search: filters.search,
        semaphore: filters.semaphore,
        per_page: filters.per_page,
        sort: null,
        direction: null,
    };

    const { search, setSearch, isLoading, setPerPage, applyFilter } =
        useDataTablePage<{ semaphore: string }>({
            routeUrl: ROUTE_URL,
            initialFilters,
            only: ['items', 'filters', 'stats'],
        });

    const isEmpty = paginated.total === 0 && !filters.search && filters.semaphore === 'todos';
    const isFilteredEmpty = paginated.total === 0 && !isEmpty;

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader title={t('title')} description={t('description')} />

                <div className="flex flex-wrap gap-2">
                    <StatBadge label={t('stats.total')} value={stats.total} variant="info" />
                    <StatBadge label={t('stats.over')} value={stats.over} variant="danger" />
                    <StatBadge label={t('stats.warning')} value={stats.warning} variant="warning" />
                    <StatBadge label={t('stats.caution')} value={stats.caution} variant="warning" />
                    <StatBadge label={t('stats.ok')} value={stats.ok} variant="success" />
                </div>

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder={t('search_placeholder')}
                    isSearching={isLoading}
                >
                    <div className="flex flex-wrap gap-1.5">
                        {(
                            [
                                ['todos', 'filters.all'],
                                ['over', 'filters.over'],
                                ['warning', 'filters.warning'],
                                ['caution', 'filters.caution'],
                                ['ok', 'filters.ok'],
                            ] as const
                        ).map(([value, labelKey]) => {
                            const active = filters.semaphore === value;
                            return (
                                <Button
                                    key={value}
                                    type="button"
                                    size="sm"
                                    variant={active ? 'default' : 'outline'}
                                    onClick={() => applyFilter({ semaphore: value })}
                                >
                                    {t(labelKey)}
                                </Button>
                            );
                        })}
                    </div>
                </DataToolbar>

                {isEmpty ? (
                    <EmptyState
                        icon={Gauge}
                        title={t('empty.title')}
                        description={t('empty.description')}
                    />
                ) : isFilteredEmpty ? (
                    <EmptyState
                        icon={Gauge}
                        title={t('empty_filter.title')}
                        description={t('empty_filter.description')}
                    />
                ) : (
                    <div className="space-y-3">
                        {paginated.data.map((row) => (
                            <TenantUsageCard key={row.tenant.id} row={row} />
                        ))}
                        <DataPagination
                            meta={paginated}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                semaphore:
                                    filters.semaphore === 'todos'
                                        ? undefined
                                        : filters.semaphore,
                                per_page: filters.per_page,
                            }}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/tenants' },
            { title: 'Uso de planes', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
