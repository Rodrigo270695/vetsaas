import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    Building2,
    CalendarDays,
    Filter,
    KeyRound,
    LayoutGrid,
    LogIn,
    Radio,
    RefreshCw,
    Store,
    UserCircle,
    Users,
    Wallet,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import { SectionCard } from '@/pages/configuracion/general/components/section-card';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import operaciones from '@/routes/plataforma/operaciones';
import sesionesLogin from '@/routes/plataforma/sesiones-login';
import type { Paginated } from '@/types';

type SessionTab = 'en_vivo' | 'flujo' | 'historial';

type SessionLogRow = {
    id: string;
    user_name: string;
    user_email: string;
    tenant_slug: string;
    tenant_label: string;
    plan_codigo: string;
    is_free: boolean;
    ip_address: string | null;
    logged_in_at: string | null;
    logged_out_at: string | null;
    logout_reason: string | null;
    is_open: boolean;
};

type OnlineUserRow = {
    user_id: string;
    user_name: string;
    user_email: string;
    tenant_id: string | null;
    tenant_slug: string | null;
    tenant_label: string | null;
    plan_codigo: string | null;
    is_free: boolean | null;
    last_path: string | null;
    last_module: string | null;
    last_seen_at: string | null;
    last_path_at: string | null;
};

type PlanGrupoFilter = 'free' | 'paid' | 'todos';
type EstadoFilter = 'todos' | 'abiertas' | 'cerradas';

type SessionFilters = {
    search: string;
    plan_grupo: PlanGrupoFilter;
    estado: EstadoFilter;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    fecha_desde: string;
    fecha_hasta: string;
};

type SessionStats = {
    total: number;
    abiertas: number;
    hoy: number;
    clinicas: number;
    free: number;
    paid: number;
    coincidencias: number;
};

type PresencePayload = {
    online_window_minutes: number;
    online: OnlineUserRow[];
    modules_now: Array<{ module: string; users: number }>;
    modules_range: Array<{ module: string; hits: number }>;
    tenants_range: Array<{
        tenant_id: string;
        tenant_slug: string;
        tenant_label: string;
        hits: number;
        users: number;
    }>;
};

type Props = {
    logs: Paginated<SessionLogRow>;
    filters: SessionFilters;
    stats: SessionStats;
    presence: PresencePayload;
    fecha_filtro_ui: {
        default_desde: string;
        default_hasta: string;
    };
    perPageOptions: number[];
    plan_free_codigo: string;
};

const DEFAULT_PER_PAGE = 15;
const DEFAULT_PLAN: PlanGrupoFilter = 'todos';
const DEFAULT_ESTADO: EstadoFilter = 'todos';

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

export default function PlataformaSesionesLoginIndex({
    logs,
    filters,
    stats,
    presence,
    fecha_filtro_ui,
}: Props) {
    const { t } = useTranslation(['plataforma-sesiones-login', 'common']);
    const [tab, setTab] = useState<SessionTab>('en_vivo');

    const planOptions: readonly FilterChip<PlanGrupoFilter>[] = useMemo(
        () => [
            { value: 'free', label: t('filters.plan.free') },
            { value: 'paid', label: t('filters.plan.paid') },
            { value: 'todos', label: t('filters.plan.todos') },
        ],
        [t],
    );

    const estadoOptions: readonly FilterChip<EstadoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('filters.estado.todos') },
            { value: 'abiertas', label: t('filters.estado.abiertas') },
            { value: 'cerradas', label: t('filters.estado.cerradas') },
        ],
        [t],
    );

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{
        plan_grupo: PlanGrupoFilter;
        estado: EstadoFilter;
        fecha_desde: string;
        fecha_hasta: string;
    }>({
        routeUrl: sesionesLogin.index().url,
        initialFilters: filters,
        only: ['logs', 'filters', 'stats', 'presence', 'fecha_filtro_ui'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.plataforma.sesiones-login.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const { secondsSince, isRefreshing, refresh } = useAutoRefresh({
        only: ['logs', 'filters', 'stats', 'presence', 'fecha_filtro_ui'],
        enabled: true,
        busy: isLoading,
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;

        if (filters.search) {
            count += 1;
        }

        if (filters.sort && filters.sort !== 'logged_in_at') {
            count += 1;
        }

        if (filters.plan_grupo !== DEFAULT_PLAN) {
            count += 1;
        }

        if (filters.estado !== DEFAULT_ESTADO) {
            count += 1;
        }

        if (
            filters.fecha_desde !== fecha_filtro_ui.default_desde
            || filters.fecha_hasta !== fecha_filtro_ui.default_hasta
        ) {
            count += 1;
        }

        if (filters.per_page !== DEFAULT_PER_PAGE) {
            count += 1;
        }

        return count;
    }, [filters, fecha_filtro_ui]);

    const reasonLabel = (reason: string | null): string => {
        if (reason === 'logout') {
            return t('reason.logout');
        }

        if (reason === 'expired') {
            return t('reason.expired');
        }

        return t('reason.unknown');
    };

    const onlineColumns: DataTableColumn<OnlineUserRow>[] = useMemo(
        () => [
            {
                key: 'user',
                header: t('presence.columns.user'),
                cell: (row) => (
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-semibold">{row.user_name}</span>
                        <span className="truncate text-xs text-muted-foreground">{row.user_email}</span>
                    </div>
                ),
            },
            {
                key: 'tenant',
                header: t('presence.columns.tenant'),
                cell: (row) => (
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-medium">{row.tenant_label ?? '—'}</span>
                        <span className="truncate font-mono text-xs text-muted-foreground">
                            {row.tenant_slug ?? '—'}
                        </span>
                    </div>
                ),
            },
            {
                key: 'module',
                header: t('presence.columns.module'),
                cell: (row) => (
                    <StatBadge label={row.last_module ?? '—'} value="" variant="info" />
                ),
            },
            {
                key: 'path',
                header: t('presence.columns.path'),
                cell: (row) => (
                    <span className="block max-w-72 truncate font-mono text-xs text-muted-foreground" title={row.last_path ?? undefined}>
                        {row.last_path ?? '—'}
                    </span>
                ),
            },
            {
                key: 'seen',
                header: t('presence.columns.seen'),
                cell: (row) => (
                    <span className="text-xs text-muted-foreground">{formatWhen(row.last_seen_at)}</span>
                ),
            },
        ],
        [t],
    );

    const columns: DataTableColumn<SessionLogRow>[] = useMemo(
        () => [
            {
                key: 'logged_in_at',
                header: t('columns.logged_in_at'),
                sortable: true,
                cell: (row) => (
                    <span className="text-xs text-muted-foreground">
                        {formatWhen(row.logged_in_at)}
                    </span>
                ),
            },
            {
                key: 'logged_out_at',
                header: t('columns.logged_out_at'),
                sortable: true,
                cell: (row) =>
                    row.is_open ? (
                        <StatBadge label={t('status.open')} value="" variant="warning" />
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            {formatWhen(row.logged_out_at)}
                        </span>
                    ),
            },
            {
                key: 'user',
                header: t('columns.user'),
                cell: (row) => (
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <UserCircle className="size-4" strokeWidth={2.25} />
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                            <span className="truncate text-sm font-semibold text-foreground">
                                {row.user_name}
                            </span>
                            <span className="truncate text-xs text-muted-foreground">
                                {row.user_email}
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'tenant_slug',
                header: t('columns.tenant'),
                sortable: true,
                cell: (row) => (
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <Store className="size-4" strokeWidth={2.25} />
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                            <span className="truncate text-sm font-semibold text-foreground">
                                {row.tenant_label}
                            </span>
                            <span className="truncate font-mono text-xs text-muted-foreground">
                                {row.tenant_slug}
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'plan_codigo',
                header: t('columns.plan'),
                sortable: true,
                cell: (row) => (
                    <StatBadge
                        label={
                            row.is_free
                                ? t('plan.free')
                                : row.plan_codigo === 'unknown'
                                  ? t('plan.unknown')
                                  : t('plan.paid')
                        }
                        value={row.plan_codigo}
                        variant={row.is_free ? 'muted' : 'info'}
                    />
                ),
            },
            {
                key: 'reason',
                header: t('columns.reason'),
                cell: (row) => (
                    <span className="text-xs text-muted-foreground">
                        {row.is_open ? '—' : reasonLabel(row.logout_reason)}
                    </span>
                ),
            },
            {
                key: 'ip_address',
                header: t('columns.ip'),
                cell: (row) => (
                    <span className="font-mono text-xs text-muted-foreground">
                        {row.ip_address ?? '—'}
                    </span>
                ),
            },
        ],
        [t],
    );

    const preservedQuery = {
        search: filters.search || undefined,
        per_page: filters.per_page,
        sort: filters.sort ?? undefined,
        direction: filters.direction ?? undefined,
        plan_grupo: filters.plan_grupo !== DEFAULT_PLAN ? filters.plan_grupo : undefined,
        estado: filters.estado !== DEFAULT_ESTADO ? filters.estado : undefined,
        fecha_desde: filters.fecha_desde,
        fecha_hasta: filters.fecha_hasta,
    };

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    action={
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span
                                className={`inline-block size-2 rounded-full ${
                                    isRefreshing ? 'animate-ping bg-amber-400' : 'bg-emerald-400'
                                }`}
                            />
                            <span>
                                {isRefreshing
                                    ? t('sync.updating')
                                    : t('sync.updated_ago', { seconds: secondsSince })}
                            </span>
                            <span className="hidden sm:inline">· {t('sync.interval_hint')}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 cursor-pointer gap-1 px-2"
                                onClick={refresh}
                                disabled={isRefreshing}
                            >
                                <RefreshCw className={`size-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
                                {t('sync.refresh_now')}
                            </Button>
                        </div>
                    }
                    stats={[
                        {
                            label: t('stats.online'),
                            value: presence.online.length,
                            variant: presence.online.length > 0 ? 'success' : 'muted',
                            icon: Users,
                        },
                        {
                            label: t('stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: LogIn,
                        },
                        {
                            label: t('stats.abiertas'),
                            value: stats.abiertas,
                            variant: 'warning',
                            icon: Radio,
                        },
                        {
                            label: t('stats.hoy'),
                            value: stats.hoy,
                            variant: 'primary',
                            icon: CalendarDays,
                        },
                        {
                            label: t('stats.clinicas'),
                            value: stats.clinicas,
                            variant: 'success',
                            icon: Building2,
                        },
                        {
                            label: t('stats.free'),
                            value: stats.free,
                            variant: 'muted',
                            icon: KeyRound,
                        },
                        {
                            label: t('stats.paid'),
                            value: stats.paid,
                            variant: 'info',
                            icon: Wallet,
                        },
                        {
                            label: 'Filtros activos',
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('stats.matches'),
                            value: stats.coincidencias,
                            variant: 'primary',
                            icon: Activity,
                        },
                    ]}
                />

                <Tabs
                    value={tab}
                    onValueChange={(value) => setTab(value as SessionTab)}
                    className="flex flex-col gap-4"
                >
                    <TabsList className="grid h-auto w-full max-w-xl grid-cols-3 gap-1 p-1">
                        <TabsTrigger value="en_vivo" className="cursor-pointer gap-1.5 text-xs sm:text-sm">
                            <Users className="size-3.5 shrink-0" />
                            <span className="truncate">{t('tabs.en_vivo')}</span>
                            {presence.online.length > 0 ? (
                                <span className="rounded-full bg-emerald-500/15 px-1.5 text-[10px] font-semibold text-emerald-700 tabular-nums dark:text-emerald-300">
                                    {presence.online.length}
                                </span>
                            ) : null}
                        </TabsTrigger>
                        <TabsTrigger value="flujo" className="cursor-pointer gap-1.5 text-xs sm:text-sm">
                            <Activity className="size-3.5 shrink-0" />
                            <span className="truncate">{t('tabs.flujo')}</span>
                        </TabsTrigger>
                        <TabsTrigger value="historial" className="cursor-pointer gap-1.5 text-xs sm:text-sm">
                            <LogIn className="size-3.5 shrink-0" />
                            <span className="truncate">{t('tabs.historial')}</span>
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="en_vivo" className="mt-0 flex flex-col gap-4">
                        <div className="grid gap-4 xl:grid-cols-3">
                            <SectionCard
                                title={t('presence.title')}
                                description={t('presence.description', {
                                    minutes: presence.online_window_minutes,
                                })}
                                icon={Users}
                                className="xl:col-span-2"
                            >
                                {presence.online.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('presence.empty', {
                                            minutes: presence.online_window_minutes,
                                        })}
                                    </p>
                                ) : (
                                    <DataTable
                                        columns={onlineColumns}
                                        data={presence.online}
                                        rowKey={(row) => row.user_id}
                                        isLoading={isRefreshing}
                                    />
                                )}
                            </SectionCard>

                            <SectionCard title={t('presence.modules_now_title')} icon={LayoutGrid}>
                                {presence.modules_now.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('presence.empty', {
                                            minutes: presence.online_window_minutes,
                                        })}
                                    </p>
                                ) : (
                                    <ul className="flex flex-col gap-1.5">
                                        {presence.modules_now.map((row) => (
                                            <li
                                                key={row.module}
                                                className="flex items-center justify-between gap-2 text-sm"
                                            >
                                                <span className="truncate font-medium">{row.module}</span>
                                                <StatBadge label={t('presence.users')} value={row.users} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </SectionCard>
                        </div>
                    </TabsContent>

                    <TabsContent value="flujo" className="mt-0 flex flex-col gap-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <AtencionDateRangeFilter
                                desde={filters.fecha_desde}
                                hasta={filters.fecha_hasta}
                                defaultDesde={fecha_filtro_ui.default_desde}
                                defaultHasta={fecha_filtro_ui.default_hasta}
                                translationNs="plataforma-sesiones-login"
                                triggerClassName="h-9"
                                onApply={(desde, hasta) =>
                                    applyFilter({ fecha_desde: desde, fecha_hasta: hasta })
                                }
                            />
                            <p className="text-xs text-muted-foreground">{t('tabs.flujo_hint')}</p>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <SectionCard title={t('presence.modules_range_title')} icon={Activity}>
                                {presence.modules_range.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('presence.empty_flow')}
                                    </p>
                                ) : (
                                    <ul className="flex max-h-[28rem] flex-col gap-1.5 overflow-y-auto pr-1">
                                        {presence.modules_range.map((row) => (
                                            <li
                                                key={row.module}
                                                className="flex items-center justify-between gap-2 text-sm"
                                            >
                                                <span className="truncate font-medium">{row.module}</span>
                                                <StatBadge
                                                    label={t('presence.hits')}
                                                    value={row.hits}
                                                    variant="info"
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </SectionCard>

                            <SectionCard title={t('presence.tenants_range_title')} icon={Building2}>
                                {presence.tenants_range.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('presence.empty_flow')}
                                    </p>
                                ) : (
                                    <div className="flex max-h-[28rem] flex-col gap-2 overflow-y-auto pr-1">
                                        {presence.tenants_range.map((row) => (
                                            <div
                                                key={row.tenant_id}
                                                className="flex flex-col gap-1 rounded-lg border border-border/50 bg-muted/20 px-3 py-2"
                                            >
                                                <span className="truncate text-sm font-semibold">
                                                    {row.tenant_label}
                                                </span>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {row.tenant_slug}
                                                </span>
                                                <div className="mt-1 flex flex-wrap gap-1.5">
                                                    <StatBadge
                                                        label={t('presence.hits')}
                                                        value={row.hits}
                                                        variant="info"
                                                    />
                                                    <StatBadge
                                                        label={t('presence.users')}
                                                        value={row.users}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </SectionCard>
                        </div>
                    </TabsContent>

                    <TabsContent value="historial" className="mt-0">
                        <DataTable
                            columns={columns}
                            data={logs.data}
                            rowKey={(row) => row.id}
                            sort={sort}
                            onSortChange={setSort}
                            isLoading={isLoading || isRefreshing}
                            ariaLiveMessage={t('aria.results_count', {
                                count: stats.coincidencias,
                            })}
                            toolbar={
                                <div className="flex w-full min-w-0 flex-col gap-2">
                                    <p className="text-xs text-muted-foreground">{t('history_hint')}</p>
                                    <DataToolbar
                                        search={search}
                                        onSearchChange={setSearch}
                                        isSearching={isLoading}
                                        placeholder={t('search_placeholder')}
                                    >
                                        <AtencionDateRangeFilter
                                            desde={filters.fecha_desde}
                                            hasta={filters.fecha_hasta}
                                            defaultDesde={fecha_filtro_ui.default_desde}
                                            defaultHasta={fecha_filtro_ui.default_hasta}
                                            translationNs="plataforma-sesiones-login"
                                            triggerClassName="h-9"
                                            onApply={(desde, hasta) =>
                                                applyFilter({
                                                    fecha_desde: desde,
                                                    fecha_hasta: hasta,
                                                })
                                            }
                                        />
                                        <FilterChips
                                            ariaLabel={t('filter_plan_label')}
                                            value={filters.plan_grupo}
                                            onChange={(plan_grupo) => applyFilter({ plan_grupo })}
                                            options={planOptions}
                                        />
                                        <FilterChips
                                            ariaLabel={t('filter_estado_label')}
                                            value={filters.estado}
                                            onChange={(estado) => applyFilter({ estado })}
                                            options={estadoOptions}
                                        />
                                    </DataToolbar>
                                </div>
                            }
                            footer={
                                <DataPagination
                                    meta={logs}
                                    onPerPageChange={setPerPage}
                                    preservedQuery={preservedQuery}
                                />
                            }
                            emptyState={
                                <EmptyState
                                    icon={activeFiltersCount > 0 ? Activity : LogIn}
                                    title={t('empty.title')}
                                    description={t('empty.description')}
                                    action={
                                        activeFiltersCount === 0 ? (
                                            <Button asChild>
                                                <Link href={operaciones.index().url}>
                                                    {t('empty.cta_operaciones')}
                                                </Link>
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

PlataformaSesionesLoginIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            {
                title: 'Sesiones de login',
                href: '/plataforma/sesiones-login',
            },
        ]}
    >
        {page}
    </AppLayout>
);
