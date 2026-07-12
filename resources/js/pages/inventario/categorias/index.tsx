import { Head } from '@inertiajs/react';
import { FolderTree, Plus, PowerOff, ScreenShare, ShieldCheck, SlidersHorizontal, UserCircle } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { DataPagination, DataTable, DataToolbar, EmptyState, FilterChips, PageHeader, StatBadge } from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';
import { CategoriaDeleteDialog } from './components/categoria-delete-dialog';
import { CategoriaFormModal } from './components/categoria-form-modal';
import { CategoriaRowActions } from './components/categoria-row-actions';
import type {
    CategoriaEstadoFilter,
    CategoriaFilters,
    CategoriaParentOption,
    CategoriaProducto,
    CategoriaStats,
} from './types';

type Props = {
    categorias: Paginated<CategoriaProducto>;
    filters: CategoriaFilters;
    stats: CategoriaStats;
    parentOptions: CategoriaParentOption[];
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; categoria: CategoriaProducto }
    | { type: 'delete'; categoria: CategoriaProducto };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: CategoriaEstadoFilter = 'todas';

export default function Index({ categorias: paginated, filters, stats, parentOptions }: Props) {
    const { t } = useTranslation(['categorias-inventario', 'common']);
    const { can } = usePermission();
    const canCreate = can('categorias-inventario.create');
    const canUpdate = can('categorias-inventario.update');
    const canDelete = can('categorias-inventario.delete');
    const canSeeAudit = can('audit-trail.view');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<{ estado: CategoriaEstadoFilter }>({
            routeUrl: '/inventario/categorias',
            initialFilters: filters,
            only: ['categorias', 'filters', 'stats', 'parentOptions'],
            errorMessage: t('toast.load_error'),
            storageKey: 'vetsaas.inventario.categorias.prefs',
            defaults: {
                per_page: DEFAULT_PER_PAGE,
                sort: null,
                direction: null,
            },
        });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);

    const estadoOptions: readonly FilterChip<CategoriaEstadoFilter>[] = useMemo(
        () => [
            {
                value: 'todas',
                label: t('common:filters.all_states'),
                description: t('common:filters.all_states_description'),
            },
            { value: 'activa', label: t('common:filters.active') },
            { value: 'inactiva', label: t('common:filters.inactive') },
        ],
        [t],
    );

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.estado, filters.per_page]);

    const columns = useMemo<DataTableColumn<CategoriaProducto>[]>(() => {
        const base: DataTableColumn<CategoriaProducto>[] = [
            {
                key: 'nombre',
                header: t('columns.nombre'),
                sortable: true,
                cell: (categoria) => (
                    <div className="flex flex-col">
                        <span className="font-medium text-foreground">{categoria.nombre}</span>
                        {categoria.slug ? (
                            <span className="font-mono text-[0.7rem] text-muted-foreground">{categoria.slug}</span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'parent',
                header: t('columns.parent'),
                cell: (categoria) =>
                    categoria.parent ? (
                        <span className="text-sm text-muted-foreground">{categoria.parent.nombre}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">{t('row.parent_none')}</span>
                    ),
            },
            {
                key: 'orden',
                header: t('columns.orden'),
                sortable: true,
                cell: (categoria) => <span className="tabular-nums text-sm">{categoria.orden}</span>,
                className: 'w-24',
            },
            {
                key: 'activo',
                header: t('columns.estado'),
                sortable: true,
                cell: (categoria) =>
                    categoria.activo ? (
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
                cell: (categoria) => (
                    categoria.creado_por ? (
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCircle className="size-4" strokeWidth={2.25} />
                            </span>
                            <div className="flex flex-col leading-tight">
                                <span className="text-xs font-medium text-foreground">{categoria.creado_por.name}</span>
                                <span className="text-[0.65rem] text-muted-foreground">
                                    {new Date(categoria.created_at).toLocaleDateString(undefined, {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </span>
                            </div>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">{t('row.system')}</span>
                    )
                ),
            });
        }

        if (canUpdate || canDelete) {
            base.push({
                key: 'acciones',
                header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                align: 'right',
                cell: (categoria) => (
                    <div className="flex justify-end">
                        <CategoriaRowActions
                            categoria={categoria}
                            onEdit={(c) => setModal({ type: 'edit', categoria: c })}
                            onDelete={(c) => setModal({ type: 'delete', categoria: c })}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [t, canSeeAudit, canUpdate, canDelete]);

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        { label: t('stats.total'), value: stats.total, variant: 'info', icon: FolderTree },
                        { label: t('stats.active'), value: stats.activas, variant: 'success', icon: ShieldCheck },
                        { label: t('stats.inactive'), value: stats.inactivas, variant: 'muted', icon: PowerOff as LucideIcon },
                        { label: t('stats.filters'), value: activeFiltersCount, variant: 'warning', icon: SlidersHorizontal },
                        { label: t('stats.matches'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                    ]}
                    action={
                        <Can permission="categorias-inventario.create">
                            <Button type="button" onClick={() => setModal({ type: 'create' })} className="cursor-pointer gap-2">
                                <Plus className="size-4" strokeWidth={2.5} />
                                <span className="hidden sm:inline">{t('actions.new')}</span>
                                <span className="sm:hidden">{t('actions.new_short')}</span>
                            </Button>
                        </Can>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(categoria) => categoria.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
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
                            icon={FolderTree}
                            title={activeFiltersCount > 0 ? t('empty.no_results_title') : t('empty.no_records_title')}
                            description={activeFiltersCount > 0 ? t('empty.no_results_description') : t('empty.no_records_description')}
                            action={
                                activeFiltersCount === 0 && canCreate ? (
                                    <Button type="button" onClick={() => setModal({ type: 'create' })} className="cursor-pointer gap-2">
                                        <Plus className="size-4" strokeWidth={2.5} />
                                        {t('actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <CategoriaFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                categoria={modal.type === 'edit' ? modal.categoria : null}
                parentOptions={parentOptions}
            />

            <CategoriaDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                categoria={modal.type === 'delete' ? modal.categoria : null}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Inventario' }, { title: 'Categorías', href: '/inventario/categorias' }]}>
        {page}
    </AppLayout>
);