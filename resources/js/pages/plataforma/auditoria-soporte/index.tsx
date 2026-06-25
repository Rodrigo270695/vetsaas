import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    Building2,
    CalendarDays,
    Filter,
    Headset,
    MonitorPlay,
    Radio,
    Store,
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
import auditoriaSoporte from '@/routes/plataforma/auditoria-soporte';
import type { Paginated } from '@/types';

type AuditLogRow = {
    id: string;
    superadmin_name: string;
    superadmin_email: string;
    tenant_slug: string;
    tenant_label: string;
    ip_address: string | null;
    central_origin: string | null;
    started_at: string | null;
    ended_at: string | null;
    is_active: boolean;
};

type EstadoFilter = 'todos' | 'activas' | 'finalizadas';

type AuditFilters = {
    search: string;
    estado: EstadoFilter;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
};

type AuditStats = {
    total: number;
    activas: number;
    hoy: number;
    clinicas: number;
    coincidencias: number;
};

type Props = {
    logs: Paginated<AuditLogRow>;
    filters: AuditFilters;
    stats: AuditStats;
    perPageOptions: number[];
};

const DEFAULT_PER_PAGE = 15;
const DEFAULT_ESTADO: EstadoFilter = 'todos';

const formatWhen = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-PE', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

export default function PlataformaAuditoriaSoporteIndex({
    logs,
    filters,
    stats,
}: Props) {
    const { t } = useTranslation('plataforma-auditoria-soporte');

    const estadoOptions: readonly FilterChip<EstadoFilter>[] = useMemo(
        () => [
            { value: 'todos',       label: t('filters.all') },
            { value: 'activas',     label: t('filters.activas') },
            { value: 'finalizadas', label: t('filters.finalizadas') },
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
    } = useDataTablePage<{ estado: EstadoFilter }>({
        routeUrl: auditoriaSoporte.index().url,
        initialFilters: filters,
        only: ['logs', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.plataforma.auditoria-soporte.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort && filters.sort !== 'started_at') count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.estado, filters.per_page]);

    const columns: DataTableColumn<AuditLogRow>[] = useMemo(
        () => [
            {
                key: 'started_at',
                header: t('columns.started_at'),
                sortable: true,
                cell: (row) => (
                    <span className="text-xs text-muted-foreground">
                        {formatWhen(row.started_at)}
                    </span>
                ),
            },
            {
                key: 'ended_at',
                header: t('columns.ended_at'),
                sortable: true,
                cell: (row) =>
                    row.is_active ? (
                        <StatBadge
                            label={t('status.active')}
                            value=""
                            variant="warning"
                        />
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            {formatWhen(row.ended_at)}
                        </span>
                    ),
            },
            {
                key: 'superadmin',
                header: t('columns.superadmin'),
                cell: (row) => (
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Headset className="size-4" strokeWidth={2.25} />
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                            <span className="truncate text-sm font-semibold text-foreground">
                                {row.superadmin_name}
                            </span>
                            <span className="truncate text-xs text-muted-foreground">
                                {row.superadmin_email}
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'tenant',
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
                            icon: MonitorPlay,
                        },
                        {
                            label: t('stats.activas'),
                            value: stats.activas,
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
                                ariaLabel={t('filter_label')}
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
                                estado:
                                    filters.estado !== DEFAULT_ESTADO
                                        ? filters.estado
                                        : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={activeFiltersCount > 0 ? Activity : Headset}
                            title={t('empty.title')}
                            description={t('empty.description')}
                            action={
                                activeFiltersCount === 0 ? (
                                    <Button asChild>
                                        <Link href="/plataforma/tenants">
                                            {t('empty.cta_tenants')}
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

PlataformaAuditoriaSoporteIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            {
                title: 'Auditoría soporte',
                href: '/plataforma/auditoria-soporte',
            },
        ]}
    >
        {page}
    </AppLayout>
);
