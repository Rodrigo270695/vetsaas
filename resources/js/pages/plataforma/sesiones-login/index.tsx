import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    Building2,
    CalendarDays,
    Filter,
    KeyRound,
    LogIn,
    Radio,
    Store,
    UserCircle,
    Wallet,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
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
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import operaciones from '@/routes/plataforma/operaciones';
import sesionesLogin from '@/routes/plataforma/sesiones-login';
import type { Paginated } from '@/types';

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

type PlanGrupoFilter = 'free' | 'paid' | 'todos';
type EstadoFilter = 'todos' | 'abiertas' | 'cerradas';

type SessionFilters = {
    search: string;
    plan_grupo: PlanGrupoFilter;
    estado: EstadoFilter;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
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

type Props = {
    logs: Paginated<SessionLogRow>;
    filters: SessionFilters;
    stats: SessionStats;
    perPageOptions: number[];
    plan_free_codigo: string;
};

const DEFAULT_PER_PAGE = 15;
const DEFAULT_PLAN: PlanGrupoFilter = 'free';
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
}: Props) {
    const { t } = useTranslation('plataforma-sesiones-login');

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
    } = useDataTablePage<{ plan_grupo: PlanGrupoFilter; estado: EstadoFilter }>({
        routeUrl: sesionesLogin.index().url,
        initialFilters: filters,
        only: ['logs', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.plataforma.sesiones-login.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
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

        if (filters.per_page !== DEFAULT_PER_PAGE) {
            count += 1;
        }

        return count;
    }, [
        filters.search,
        filters.sort,
        filters.plan_grupo,
        filters.estado,
        filters.per_page,
    ]);

    const reasonLabel = (reason: string | null): string => {
        if (reason === 'logout') {
            return t('reason.logout');
        }

        if (reason === 'expired') {
            return t('reason.expired');
        }

        return t('reason.unknown');
    };

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
                        <StatBadge
                            label={t('status.open')}
                            value=""
                            variant="warning"
                        />
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

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
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

                <DataTable
                    columns={columns}
                    data={logs.data}
                    rowKey={(row) => row.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    ariaLiveMessage={t('aria.results_count', {
                        count: stats.coincidencias,
                    })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('search_placeholder')}
                        >
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
                    }
                    footer={
                        <DataPagination
                            meta={logs}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                sort: filters.sort ?? undefined,
                                direction: filters.direction ?? undefined,
                                plan_grupo:
                                    filters.plan_grupo !== DEFAULT_PLAN
                                        ? filters.plan_grupo
                                        : undefined,
                                estado:
                                    filters.estado !== DEFAULT_ESTADO
                                        ? filters.estado
                                        : undefined,
                            }}
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
