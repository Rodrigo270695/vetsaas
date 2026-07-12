import { Head, Link } from '@inertiajs/react';
import {
    Download,
    Filter,
    Plus,
    PowerOff,
    ScreenShare,
    Trash2,
    UserCircle,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import {
    BulkAction,
    BulkActionBar,
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePlanLimitReached } from '@/hooks/use-plan-limits';
import { usePermission } from '@/hooks/use-permission';
import { useRowSelection } from '@/hooks/use-row-selection';
import propietarios from '@/routes/clinica/propietarios';
import { dashboard } from '@/routes';
import type { Paginated } from '@/types';
import { PropietarioBulkDeleteDialog } from './components/propietario-bulk-delete-dialog';
import { PropietarioDeleteDialog } from './components/propietario-delete-dialog';
import { PropietarioFormModal } from './components/propietario-form-modal';
import { PropietarioRowActions } from './components/propietario-row-actions';
import type {
    GeoOption,
    Propietario,
    PropietarioEstadoFilter,
    PropietarioFilters,
    PropietarioStats,
} from './types';

function displayNombre(p: Propietario): string {
    if (p.razon_social) {
        return p.razon_social;
    }
    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

type Props = {
    propietarios: Paginated<Propietario>;
    filters: PropietarioFilters;
    stats: PropietarioStats;
    departamentos: readonly GeoOption[];
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; propietario: Propietario }
    | { type: 'delete'; propietario: Propietario }
    | { type: 'bulk-delete' };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: PropietarioEstadoFilter = 'todos';

export default function Index({ propietarios: paginated, filters, stats, departamentos }: Props) {
    const { t } = useTranslation(['propietarios', 'common']);
    const { can } = usePermission();
    const canCreate = can('propietarios.create');
    const ownersLimitReached = usePlanLimitReached('max_propietarios');
    const canCreateOwner = canCreate && !ownersLimitReached;
    const canUpdate = can('propietarios.update');
    const canDelete = can('propietarios.delete');
    const canExport = can('propietarios.export');
    const canBulkDelete = can('propietarios.bulk-delete');
    const canSeeAudit = can('audit-trail.view');
    const showRowActions = canUpdate || canDelete;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{ estado: PropietarioEstadoFilter }>({
        routeUrl: propietarios.index().url,
        initialFilters: filters,
        only: ['propietarios', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.propietarios.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estadoOptions: readonly FilterChip<PropietarioEstadoFilter>[] = useMemo(
        () => [
            {
                value: 'todos',
                label: t('common:filters.all_states'),
                description: t('common:filters.all_states_description'),
            },
            { value: 'activo', label: t('common:filters.active') },
            { value: 'inactivo', label: t('common:filters.inactive') },
        ],
        [t],
    );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((p: Propietario) => setModal({ type: 'edit', propietario: p }), []);
    const openDelete = useCallback((p: Propietario) => setModal({ type: 'delete', propietario: p }), []);
    const openBulkDelete = useCallback(() => setModal({ type: 'bulk-delete' }), []);

    const selection = useRowSelection({
        rows: paginated.data,
        rowKey: (p) => p.id,
    });

    const activeFiltersCount = useMemo(() => {
        let c = 0;
        if (filters.search) c += 1;
        if (filters.sort) c += 1;
        if (filters.estado !== DEFAULT_ESTADO) c += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) c += 1;
        return c;
    }, [filters.search, filters.sort, filters.estado, filters.per_page]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.sort) params.set('sort', filters.sort);
        if (filters.direction) params.set('direction', filters.direction);
        if (filters.estado !== DEFAULT_ESTADO) params.set('estado', filters.estado);
        const qs = params.toString();
        return qs.length > 0
            ? `${propietarios.export().url}?${qs}`
            : propietarios.export().url;
    }, [filters.search, filters.sort, filters.direction, filters.estado]);

    const columns = useMemo<DataTableColumn<Propietario>[]>(() => {
        const base: DataTableColumn<Propietario>[] = [
            {
                key: 'nombres',
                header: t('columns.nombre'),
                sortable: true,
                cell: (p) => (
                    <div className="flex flex-col">
                        <Link
                            href={propietarios.show(p.id).url}
                            className="font-medium text-primary hover:underline"
                        >
                            {displayNombre(p)}
                        </Link>
                        {p.direccion && (
                            <span className="text-xs text-muted-foreground line-clamp-1">
                                {p.direccion}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                key: 'documento',
                header: t('columns.documento'),
                cell: (p) =>
                    p.numero_documento ? (
                        <span className="font-mono text-xs">
                            {p.tipo_documento ? `${p.tipo_documento} ` : ''}
                            {p.numero_documento}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            {t('row.no_doc')}
                        </span>
                    ),
            },
            {
                key: 'contacto',
                header: t('columns.contacto'),
                cell: (p) => (
                    <div className="flex flex-col text-xs text-muted-foreground">
                        {p.telefono && <span className="tabular-nums">{p.telefono}</span>}
                        {p.email && <span>{p.email}</span>}
                        {!p.telefono && !p.email && <span>—</span>}
                    </div>
                ),
            },
            {
                key: 'pacientes_count',
                header: t('columns.mascotas'),
                cell: (p) => (
                    <span className="tabular-nums text-sm font-medium">
                        {p.pacientes_count ?? 0}
                    </span>
                ),
            },
            {
                key: 'activo',
                header: t('columns.estado'),
                sortable: true,
                cell: (p) =>
                    p.activo ? (
                        <StatBadge label={t('common:filters.active')} value="" variant="success" />
                    ) : (
                        <StatBadge label={t('common:filters.inactive')} value="" variant="muted" />
                    ),
            },
        ];

        if (canSeeAudit) {
            base.push({
                key: 'creado_por',
                header: t('columns.creado_por'),
                cell: (p) => {
                    if (!p.creado_por) {
                        return (
                            <span className="text-xs text-muted-foreground">
                                {t('row.system')}
                            </span>
                        );
                    }
                    return (
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCircle className="size-4" strokeWidth={2.25} />
                            </span>
                            <div className="flex flex-col leading-tight">
                                <span className="text-xs font-medium text-foreground">
                                    {p.creado_por.name}
                                </span>
                                <span className="text-[0.65rem] text-date">
                                    {new Date(p.created_at).toLocaleDateString(undefined, {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </span>
                            </div>
                        </div>
                    );
                },
            });
        }

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                align: 'right',
                cell: (p) => (
                    <div className="flex justify-end">
                        <PropietarioRowActions
                            propietario={p}
                            onEdit={openEdit}
                            onDelete={openDelete}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [t, canSeeAudit, showRowActions, canUpdate, canDelete, openEdit, openDelete]);

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        { label: t('stats.total'), value: stats.total, variant: 'info', icon: Users },
                        { label: t('stats.active'), value: stats.activos, variant: 'success', icon: Users },
                        { label: t('stats.inactive'), value: stats.inactivos, variant: 'muted', icon: PowerOff as LucideIcon },
                        { label: t('stats.filters'), value: activeFiltersCount, variant: 'warning', icon: Filter },
                        { label: t('stats.matches'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                    ]}
                    action={
                        <div className="flex flex-row items-center gap-2">
                            {canExport && (
                                <Button asChild variant="outline" className="cursor-pointer gap-2">
                                    <a href={exportUrl} download>
                                        <Download className="size-4" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">{t('common:actions.export_xlsx')}</span>
                                    </a>
                                </Button>
                            )}
                            <Can permission="propietarios.create">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="inline-flex">
                                            <Button
                                                type="button"
                                                onClick={openCreate}
                                                disabled={ownersLimitReached}
                                                className="cursor-pointer gap-2"
                                            >
                                                <Plus className="size-4" strokeWidth={2.5} />
                                                <span className="hidden sm:inline">{t('actions.new')}</span>
                                                <span className="sm:hidden">{t('actions.new_short')}</span>
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    {ownersLimitReached ? (
                                        <TooltipContent side="bottom" className="max-w-xs">
                                            {t('plan_limit.max_propietarios')}
                                        </TooltipContent>
                                    ) : null}
                                </Tooltip>
                            </Can>
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(p) => p.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    selection={canBulkDelete ? selection : undefined}
                    ariaLiveMessage={t('common:aria.results_count_other', { count: stats.coincidencias })}
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
                            meta={paginated}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                sort: filters.sort ?? undefined,
                                direction: filters.direction ?? undefined,
                                estado: filters.estado !== DEFAULT_ESTADO ? filters.estado : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Users}
                            title={activeFiltersCount > 0 ? t('empty.no_results_title') : t('empty.no_records_title')}
                            description={activeFiltersCount > 0 ? t('empty.no_results_description') : t('empty.no_records_description')}
                            action={
                                activeFiltersCount === 0 && canCreateOwner ? (
                                    <Button type="button" onClick={openCreate} className="cursor-pointer gap-2">
                                        <Plus className="size-4" strokeWidth={2.5} />
                                        {t('actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <PropietarioFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                propietario={modal.type === 'edit' ? modal.propietario : null}
                departamentos={departamentos}
            />

            <PropietarioDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                propietario={modal.type === 'delete' ? modal.propietario : null}
            />

            <PropietarioBulkDeleteDialog
                open={modal.type === 'bulk-delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                ids={Array.from(selection.selectedIds)}
                onCompleted={() => selection.clear()}
            />

            {canBulkDelete && (
                <BulkActionBar
                    count={selection.count}
                    labels={{
                        singular: t('bulk.selected_singular'),
                        plural: t('bulk.selected_plural'),
                    }}
                    onClear={selection.clear}
                >
                    <BulkAction
                        type="button"
                        variant="destructive"
                        size="sm"
                        onClick={openBulkDelete}
                        className="cursor-pointer gap-1.5"
                    >
                        <Trash2 className="size-4" strokeWidth={2.5} />
                        <span className="hidden sm:inline">{t('actions.delete_selected')}</span>
                    </BulkAction>
                </BulkActionBar>
            )}
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Propietarios', href: propietarios.index().url },
    ],
};
