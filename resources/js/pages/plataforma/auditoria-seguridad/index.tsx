import { Head } from '@inertiajs/react';
import {
    Activity,
    CalendarDays,
    Filter,
    KeyRound,
    ShieldAlert,
    Store,
    User,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
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
import type { Paginated } from '@/types';

const ROUTE_URL = '/plataforma/auditoria-seguridad';
const DEFAULT_PER_PAGE = 15;
const DEFAULT_MODULO: ModuloFilter = 'todos';

type ModuloFilter = 'todos' | 'roles' | 'usuarios';

type AuditLogRow = {
    id: string;
    created_at: string | null;
    actor_name: string;
    actor_email: string | null;
    tenant_slug: string;
    tenant_label: string;
    action: string;
    modulo: string;
    subject_label: string | null;
    summary: string;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
};

type AuditFilters = {
    search: string;
    modulo: ModuloFilter;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
};

type AuditStats = {
    total: number;
    hoy: number;
    roles: number;
    usuarios: number;
    coincidencias: number;
};

type Props = {
    logs: Paginated<AuditLogRow>;
    filters: AuditFilters;
    stats: AuditStats;
    perPageOptions: number[];
};

const formatWhen = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-PE', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

export default function PlataformaAuditoriaSeguridadIndex({
    logs,
    filters,
    stats,
}: Props) {
    const { t } = useTranslation('plataforma-auditoria-seguridad');

    const moduloOptions: readonly FilterChip<ModuloFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('filters.all') },
            { value: 'roles', label: t('filters.roles') },
            { value: 'usuarios', label: t('filters.usuarios') },
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
    } = useDataTablePage<{ modulo: ModuloFilter }>({
        routeUrl: ROUTE_URL,
        initialFilters: filters,
        only: ['logs', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.plataforma.auditoria-seguridad.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort && filters.sort !== 'created_at') count += 1;
        if (filters.modulo !== DEFAULT_MODULO) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.modulo, filters.per_page]);

    const actionLabel = (action: string): string =>
        t(`actions.${action}`, { defaultValue: action });

    const columns: DataTableColumn<AuditLogRow>[] = useMemo(
        () => [
            {
                key: 'created_at',
                header: t('columns.created_at'),
                sortable: true,
                cell: (row) => (
                    <span className="text-xs text-muted-foreground">
                        {formatWhen(row.created_at)}
                    </span>
                ),
            },
            {
                key: 'actor_name',
                header: t('columns.actor'),
                sortable: true,
                cell: (row) => (
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <User className="size-4" strokeWidth={2.25} />
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                            <span className="truncate text-sm font-semibold text-foreground">
                                {row.actor_name}
                            </span>
                            <span className="truncate text-xs text-muted-foreground">
                                {row.actor_email ?? '—'}
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
                                {row.tenant_label || t('central')}
                            </span>
                            <span className="truncate font-mono text-xs text-muted-foreground">
                                {row.tenant_slug}
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'action',
                header: t('columns.action'),
                sortable: true,
                cell: (row) => (
                    <StatBadge
                        label={actionLabel(row.action)}
                        value=""
                        variant={
                            row.action.includes('deleted')
                                ? 'danger'
                                : row.action.includes('blocked')
                                  ? 'warning'
                                  : 'info'
                        }
                    />
                ),
            },
            {
                key: 'summary',
                header: t('columns.summary'),
                cell: (row) => (
                    <div className="max-w-md">
                        <p className="line-clamp-2 text-sm text-foreground">
                            {row.summary}
                        </p>
                        {row.subject_label ? (
                            <p className="mt-0.5 truncate font-mono text-[0.65rem] text-muted-foreground">
                                {row.subject_label}
                            </p>
                        ) : null}
                    </div>
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
                            icon: ShieldAlert,
                        },
                        {
                            label: t('stats.hoy'),
                            value: stats.hoy,
                            variant: 'primary',
                            icon: CalendarDays,
                        },
                        {
                            label: t('stats.roles'),
                            value: stats.roles,
                            variant: 'warning',
                            icon: KeyRound,
                        },
                        {
                            label: t('stats.usuarios'),
                            value: stats.usuarios,
                            variant: 'success',
                            icon: Users,
                        },
                        {
                            label: 'Filtros',
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
                                ariaLabel={t('filter_label')}
                                value={filters.modulo}
                                onChange={(modulo) => applyFilter({ modulo })}
                                options={moduloOptions}
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
                                modulo:
                                    filters.modulo !== DEFAULT_MODULO
                                        ? filters.modulo
                                        : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={ShieldAlert}
                            title={t('empty.title')}
                            description={t('empty.description')}
                        />
                    }
                />
            </div>
        </>
    );
}

PlataformaAuditoriaSeguridadIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            {
                title: 'Auditoría seguridad',
                href: ROUTE_URL,
            },
        ]}
    >
        {page}
    </AppLayout>
);
