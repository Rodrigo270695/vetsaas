import { Head } from '@inertiajs/react';
import { Building2, Plus, PowerOff, ScreenShare, ShieldCheck, SlidersHorizontal, UserCircle } from 'lucide-react';
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
import inventario from '@/routes/inventario';
import type { Paginated } from '@/types';
import { ProveedorDeleteDialog } from './components/proveedor-delete-dialog';
import { ProveedorFormModal } from './components/proveedor-form-modal';
import { ProveedorRowActions } from './components/proveedor-row-actions';
import type { ProveedorEstadoFilter, ProveedorFila, ProveedorStats } from './types';

type Props = {
    proveedores: Paginated<ProveedorFila>;
    filters: ProveedorFilters;
    stats: ProveedorStats;
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; proveedor: ProveedorFila }
    | { type: 'delete'; proveedor: ProveedorFila };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: ProveedorEstadoFilter = 'todas';

export default function Index({ proveedores: paginated, filters, stats }: Props) {
    const { t } = useTranslation(['proveedores-inventario', 'common']);
    const { can } = usePermission();
    const canCreate = can('proveedores.create');
    const canUpdate = can('proveedores.update');
    const canDelete = can('proveedores.delete');
    const canSeeAudit = can('audit-trail.view');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } = useDataTablePage<{ estado: ProveedorEstadoFilter }>({
        routeUrl: inventario.proveedores.index.url(),
        initialFilters: filters,
        only: ['proveedores', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.inventario.proveedores.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);

    const estadoOptions: readonly FilterChip<ProveedorEstadoFilter>[] = useMemo(
        () => [
            { value: 'todas', label: t('common:filters.all') },
            { value: 'activa', label: t('common:filters.active') },
            { value: 'inactiva', label: t('common:filters.inactive') },
        ],
        [t],
    );

    const activeFiltersCount = useMemo(() => {
        let count = 0;

        if (filters.search) {
count += 1;
}

        if (filters.sort) {
count += 1;
}

        if (filters.estado !== DEFAULT_ESTADO) {
count += 1;
}

        if (filters.per_page !== DEFAULT_PER_PAGE) {
count += 1;
}

        return count;
    }, [filters.search, filters.sort, filters.estado, filters.per_page]);

    const columns = useMemo<DataTableColumn<ProveedorFila>[]>(() => {
        const base: DataTableColumn<ProveedorFila>[] = [
            {
                key: 'ruc',
                header: t('columns.ruc'),
                sortable: true,
                cell: (p) => <span className="font-mono text-xs">{p.ruc}</span>,
                className: 'w-28',
            },
            {
                key: 'razon_social',
                header: t('columns.razon_social'),
                sortable: true,
                cell: (p) => <span className="font-medium text-foreground">{p.razon_social}</span>,
            },
            {
                key: 'direccion',
                header: t('columns.direccion'),
                cell: (p) =>
                    p.direccion ? (
                        <span className="line-clamp-2 max-w-xs text-sm text-muted-foreground" title={p.direccion}>
                            {p.direccion}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'sunat',
                header: t('columns.sunat'),
                cell: (p) =>
                    p.estado_sunat || p.condicion_sunat ? (
                        <span className="text-xs text-muted-foreground">
                            {t('sunat_resumen', {
                                estado: p.estado_sunat ?? '—',
                                condicion: p.condicion_sunat ?? '—',
                            })}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
                className: 'max-w-[10rem]',
            },
            {
                key: 'contacto',
                header: t('columns.contacto'),
                cell: (p) => (
                    <div className="flex min-w-0 flex-col text-xs text-muted-foreground">
                        {p.telefono ? <span className="truncate">{p.telefono}</span> : null}
                        {p.email ? <span className="truncate">{p.email}</span> : null}
                        {!p.telefono && !p.email ? <span>—</span> : null}
                    </div>
                ),
                className: 'max-w-[9rem]',
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
                className: 'w-28',
            },
        ];

        if (canSeeAudit) {
            base.push({
                key: 'creado_por',
                header: t('columns.creado_por'),
                cell: (p) =>
                    p.creado_por ? (
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCircle className="size-4" strokeWidth={2.25} />
                            </span>
                            <div className="flex min-w-0 flex-col leading-tight">
                                <span className="truncate text-xs font-medium text-foreground">{p.creado_por.name}</span>
                                <span className="text-[0.65rem] text-muted-foreground">
                                    {new Date(p.created_at).toLocaleDateString(undefined, {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </span>
                            </div>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
            });
        }

        if (canUpdate || canDelete) {
            base.push({
                key: 'acciones',
                header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                align: 'right',
                cell: (p) => (
                    <div className="flex justify-end">
                        <ProveedorRowActions
                            proveedor={p}
                            onEdit={(row) => setModal({ type: 'edit', proveedor: row })}
                            onDelete={(row) => setModal({ type: 'delete', proveedor: row })}
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
                        { label: t('stats.total'), value: stats.total, variant: 'info', icon: Building2 },
                        { label: t('stats.activos'), value: stats.activos, variant: 'success', icon: ShieldCheck },
                        { label: t('stats.inactivos'), value: stats.inactivos, variant: 'muted', icon: PowerOff as LucideIcon },
                        { label: t('stats.filtros'), value: activeFiltersCount, variant: 'warning', icon: SlidersHorizontal },
                        { label: t('stats.coincidencias'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                    ]}
                    action={
                        <Can permission="proveedores.create">
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
                    rowKey={(p) => p.id}
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
                            icon={Building2}
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

            <ProveedorFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
closeModal();
}
                }}
                proveedor={modal.type === 'edit' ? modal.proveedor : null}
            />

            <ProveedorDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
closeModal();
}
                }}
                proveedor={modal.type === 'delete' ? modal.proveedor : null}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Inventario' }, { title: 'Proveedores', href: '/inventario/proveedores' }]}>
        {page}
    </AppLayout>
);
