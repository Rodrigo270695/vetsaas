import { Head } from '@inertiajs/react';
import {
    Activity,
    Bot,
    CalendarDays,
    Download,
    FileDown,
    Filter,
    Pencil,
    Plus,
    ScrollText,
    Trash2,
    User,
} from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

const ROUTE_URL = '/auditoria/logs';
const EXPORT_URL = '/auditoria/logs/export';
const DEFAULT_PER_PAGE = 15;

type AccionFilter =
    | 'todos'
    | 'created'
    | 'updated'
    | 'deleted'
    | 'exported'
    | 'downloaded';

type AuditLogRow = {
    id: string;
    usuario_nombre: string;
    usuario_email: string | null;
    usuario_es_bot_ia?: boolean;
    accion: string;
    modulo: string;
    registro_label: string | null;
    registro_id: string | null;
    cambios: Record<string, { before: unknown; after: unknown }> | null;
    ip_address: string | null;
    created_at: string | null;
};

type AuditFilters = {
    search: string;
    accion: AccionFilter;
    modulo: string;
    desde: string;
    hasta: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
};

type AuditStats = {
    total: number;
    hoy: number;
    creaciones: number;
    ediciones: number;
    eliminaciones: number;
    exportaciones: number;
    coincidencias: number;
};

type Props = {
    logs: Paginated<AuditLogRow>;
    filters: AuditFilters;
    stats: AuditStats;
    perPageOptions: number[];
    accionOptions: string[];
    moduloOptions: string[];
    canExport: boolean;
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

const accionVariant = (
    accion: string,
): 'success' | 'info' | 'warning' | 'danger' | 'primary' => {
    switch (accion) {
        case 'created':
            return 'success';
        case 'updated':
            return 'info';
        case 'deleted':
            return 'danger';
        case 'exported':
        case 'downloaded':
            return 'warning';
        default:
            return 'primary';
    }
};

const accionIcon = (accion: string) => {
    switch (accion) {
        case 'created':
            return Plus;
        case 'updated':
            return Pencil;
        case 'deleted':
            return Trash2;
        case 'exported':
            return Download;
        case 'downloaded':
            return FileDown;
        default:
            return Activity;
    }
};

export default function AuditoriaLogsIndex({
    logs,
    filters,
    stats,
    perPageOptions,
    accionOptions,
    moduloOptions,
    canExport,
}: Props) {
    const { t } = useTranslation('auditoria-logs');

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{
        accion: AccionFilter;
        modulo: string;
        desde: string;
        hasta: string;
    }>({
        routeUrl: ROUTE_URL,
        initialFilters: filters,
        only: ['logs', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.auditoria.logs.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: 'created_at',
            direction: 'desc',
        },
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.accion !== 'todos') count += 1;
        if (filters.modulo !== 'todos') count += 1;
        if (filters.desde) count += 1;
        if (filters.hasta) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [
        filters.search,
        filters.accion,
        filters.modulo,
        filters.desde,
        filters.hasta,
        filters.per_page,
    ]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.accion !== 'todos') params.set('accion', filters.accion);
        if (filters.modulo !== 'todos') params.set('modulo', filters.modulo);
        if (filters.desde) params.set('desde', filters.desde);
        if (filters.hasta) params.set('hasta', filters.hasta);
        const qs = params.toString();
        return qs.length > 0 ? `${EXPORT_URL}?${qs}` : EXPORT_URL;
    }, [
        filters.search,
        filters.accion,
        filters.modulo,
        filters.desde,
        filters.hasta,
    ]);

    const accionFilterOptions: readonly FilterChip<AccionFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('acciones.todos') },
            ...accionOptions.map((accion) => ({
                value: accion as AccionFilter,
                label: t(`acciones.${accion}`, { defaultValue: accion }),
            })),
        ],
        [accionOptions, t],
    );

    const moduloFilterOptions: readonly FilterChip<string>[] = useMemo(
        () => [
            { value: 'todos', label: t('modulos.todos') },
            ...moduloOptions.map((modulo) => ({
                value: modulo,
                label: t(`modulos.${modulo}`, { defaultValue: modulo }),
            })),
        ],
        [moduloOptions, t],
    );

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
                key: 'usuario_nombre',
                header: t('columns.usuario'),
                sortable: true,
                cell: (row) => {
                    const isBotIa = row.usuario_es_bot_ia === true;
                    const Icon = isBotIa ? Bot : User;

                    return (
                        <div className="flex items-center gap-2">
                            <span
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-full',
                                    isBotIa
                                        ? 'bg-violet-500/10 text-violet-600'
                                        : 'bg-primary/10 text-primary',
                                )}
                            >
                                <Icon className="size-4" strokeWidth={2.25} />
                            </span>
                            <div className="flex min-w-0 flex-col leading-tight">
                                <span className="truncate text-sm font-semibold text-foreground">
                                    {row.usuario_nombre}
                                </span>
                                {row.usuario_email ? (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {row.usuario_email}
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    );
                },
            },
            {
                key: 'accion',
                header: t('columns.accion'),
                sortable: true,
                cell: (row) => {
                    const Icon = accionIcon(row.accion);
                    return (
                        <StatBadge
                            label={t(`acciones.${row.accion}`, {
                                defaultValue: row.accion,
                            })}
                            value=""
                            variant={accionVariant(row.accion)}
                            icon={Icon}
                        />
                    );
                },
            },
            {
                key: 'modulo',
                header: t('columns.modulo'),
                sortable: true,
                cell: (row) => (
                    <span className="text-sm text-foreground">
                        {t(`modulos.${row.modulo}`, {
                            defaultValue: row.modulo,
                        })}
                    </span>
                ),
            },
            {
                key: 'registro',
                header: t('columns.registro'),
                cell: (row) => (
                    <div className="flex min-w-0 flex-col">
                        {row.registro_label && (
                            <span className="truncate text-sm font-medium text-foreground">
                                {row.registro_label}
                            </span>
                        )}
                        {row.registro_id && (
                            <span className="truncate font-mono text-xs text-muted-foreground">
                                {row.registro_id}
                            </span>
                        )}
                        {!row.registro_label && !row.registro_id && (
                            <span className="text-muted-foreground">—</span>
                        )}
                    </div>
                ),
            },
            {
                key: 'cambios',
                header: t('columns.cambios'),
                cell: (row) => {
                    const count = row.cambios
                        ? Object.keys(row.cambios).length
                        : 0;
                    if (count === 0) {
                        return <span className="text-muted-foreground">—</span>;
                    }
                    return (
                        <span className="text-xs text-muted-foreground">
                            {t('cambios_count', { count })}
                        </span>
                    );
                },
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

  const headerAction = canExport ? (
      <Button variant="outline" size="sm" asChild>
          <a href={exportUrl} download>
              <Download className="size-4" />
              <span className="hidden sm:inline">
                  {t('common:actions.export_xlsx', { defaultValue: 'Exportar XLSX' })}
              </span>
          </a>
      </Button>
  ) : undefined;

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    action={headerAction}
                    stats={[
                        {
                            label: t('stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: ScrollText,
                        },
                        {
                            label: t('stats.hoy'),
                            value: stats.hoy,
                            variant: 'primary',
                            icon: CalendarDays,
                        },
                        {
                            label: t('stats.creaciones'),
                            value: stats.creaciones,
                            variant: 'success',
                            icon: Plus,
                        },
                        {
                            label: t('stats.ediciones'),
                            value: stats.ediciones,
                            variant: 'info',
                            icon: Pencil,
                        },
                        {
                            label: t('stats.eliminaciones'),
                            value: stats.eliminaciones,
                            variant: 'danger',
                            icon: Trash2,
                        },
                        {
                            label: t('stats.exportaciones'),
                            value: stats.exportaciones,
                            variant: 'warning',
                            icon: Download,
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
                            searchPlaceholder={t('search_placeholder')}
                            perPage={filters.per_page}
                            perPageOptions={perPageOptions}
                            onPerPageChange={setPerPage}
                            filtersSlot={
                                <div className="flex flex-wrap items-end gap-3">
                                    <FilterChips
                                        ariaLabel={t('filter_accion')}
                                        value={filters.accion}
                                        onChange={(value) =>
                                            applyFilter({
                                                accion: value,
                                            })
                                        }
                                        options={accionFilterOptions}
                                        className="sm:min-w-56"
                                    />

                                    <FilterChips
                                        ariaLabel={t('filter_modulo')}
                                        value={filters.modulo}
                                        onChange={(value) =>
                                            applyFilter({ modulo: value })
                                        }
                                        options={moduloFilterOptions}
                                        className="sm:min-w-56"
                                    />

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="filtro-desde">
                                            {t('filter_desde')}
                                        </Label>
                                        <Input
                                            id="filtro-desde"
                                            type="date"
                                            className="w-[150px]"
                                            value={filters.desde}
                                            onChange={(e) =>
                                                applyFilter({
                                                    desde: e.target.value,
                                                })
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="filtro-hasta">
                                            {t('filter_hasta')}
                                        </Label>
                                        <Input
                                            id="filtro-hasta"
                                            type="date"
                                            className="w-[150px]"
                                            value={filters.hasta}
                                            onChange={(e) =>
                                                applyFilter({
                                                    hasta: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </div>
                            }
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={ScrollText}
                            title={t('empty.title')}
                            description={t('empty.description')}
                        />
                    }
                    footer={
                        <DataPagination
                            meta={logs}
                            onPerPageChange={setPerPage}
                            perPageOptions={perPageOptions}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                sort: filters.sort ?? undefined,
                                direction: filters.direction ?? undefined,
                                accion:
                                    filters.accion !== 'todos'
                                        ? filters.accion
                                        : undefined,
                                modulo:
                                    filters.modulo !== 'todos'
                                        ? filters.modulo
                                        : undefined,
                                desde: filters.desde || undefined,
                                hasta: filters.hasta || undefined,
                            }}
                        />
                    }
                />
            </div>
        </>
    );
}

AuditoriaLogsIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Auditoría' },
            { title: 'Registro de actividad', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
