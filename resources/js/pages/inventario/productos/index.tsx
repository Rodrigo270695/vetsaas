import { Head } from '@inertiajs/react';
import { Download, Filter, Package, Pill, Plus, PowerOff, ScreenShare, Upload, UserCircle } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
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
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePlanLimitReached } from '@/hooks/use-plan-limits';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';
import { ProductoBulkImportModal } from './components/producto-bulk-import-modal';
import { ProductoDeleteDialog } from './components/producto-delete-dialog';
import { ProductoFormModal } from './components/producto-form-modal';
import { ProductoRowActions } from './components/producto-row-actions';
import type {
    Producto,
    ProductoCategoriaOption,
    ProductoEstadoFilter,
    ProductoFilters,
    ProductoSedeOption,
    ProductoStats,
    ProductoUnidadOption,
} from './types';

type Props = {
    productos: Paginated<Producto>;
    filters: ProductoFilters;
    stats: ProductoStats;
    categoriaOptions: ProductoCategoriaOption[];
    unidadOptions: ProductoUnidadOption[];
    sedeOptions: ProductoSedeOption[];
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'bulk' }
    | { type: 'edit'; producto: Producto }
    | { type: 'delete'; producto: Producto };

type TableExtraFilters = {
    estado: ProductoEstadoFilter;
    categoria_id: string;
};

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: ProductoEstadoFilter = 'todas';

function formatPrecio(value: string | null, locale: string): string {
    if (value === null || value === '') {
        return '';
    }
    const n = Number(value);
    if (Number.isNaN(n)) {
        return value;
    }
    return n.toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Index({ productos: paginated, filters, stats, categoriaOptions, unidadOptions, sedeOptions }: Props) {
    const { t, i18n } = useTranslation(['productos-inventario', 'common']);
    const { can } = usePermission();
    const canCreate = can('productos.create');
    const canView = can('productos.view');
    const productsLimitReached = usePlanLimitReached('max_productos');
    const canCreateProduct = canCreate && !productsLimitReached;
    const canUpdate = can('productos.update');
    const canDelete = can('productos.delete');
    const canSeeAudit = can('audit-trail.view');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } = useDataTablePage<TableExtraFilters>({
        routeUrl: '/inventario/productos',
        initialFilters: filters,
        only: ['productos', 'filters', 'stats', 'categoriaOptions', 'unidadOptions', 'sedeOptions'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.inventario.productos.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.sort) params.set('sort', filters.sort);
        if (filters.direction) params.set('direction', filters.direction);
        if (filters.estado && filters.estado !== DEFAULT_ESTADO) params.set('estado', filters.estado);
        if (filters.categoria_id) params.set('categoria_id', filters.categoria_id);
        const qs = params.toString();

        return qs ? `/inventario/productos/export?${qs}` : '/inventario/productos/export';
    }, [filters]);

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);

    const estadoOptions: readonly FilterChip<ProductoEstadoFilter>[] = useMemo(
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
        if (filters.categoria_id && filters.categoria_id !== '') count += 1;
        return count;
    }, [filters.search, filters.sort, filters.estado, filters.per_page, filters.categoria_id]);

    const columns = useMemo<DataTableColumn<Producto>[]>(() => {
        const base: DataTableColumn<Producto>[] = [
            {
                key: 'nombre',
                header: t('columns.nombre'),
                sortable: true,
                cell: (p) => (
                    <div className="flex flex-col">
                        <span className="font-medium text-foreground">{p.nombre}</span>
                        {p.slug ? (
                            <span className="font-mono text-[0.7rem] text-muted-foreground">{p.slug}</span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'categoria',
                header: t('columns.categoria'),
                cell: (p) =>
                    p.categoria ? (
                        <span className="text-sm text-muted-foreground">{p.categoria.nombre}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">{t('row.sin_categoria')}</span>
                    ),
            },
            {
                key: 'sku',
                header: t('columns.sku'),
                sortable: true,
                cell: (p) =>
                    p.sku ? (
                        <span className="font-mono text-xs">{p.sku}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'unidad',
                header: t('columns.unidad'),
                sortable: true,
                cell: (p) => <span className="tabular-nums text-sm">{p.unidad}</span>,
                className: 'w-24',
            },
            {
                key: 'precio_compra',
                header: t('columns.precio_compra'),
                cell: (p) => {
                    const txt = formatPrecio(p.precio_compra, i18n.language);
                    return txt ? (
                        <span className="tabular-nums text-sm">{txt}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    );
                },
                className: 'w-28',
            },
            {
                key: 'precio_venta',
                header: t('columns.precio'),
                sortable: true,
                cell: (p) => {
                    const txt = formatPrecio(p.precio_venta, i18n.language);
                    return txt ? (
                        <span className="tabular-nums text-sm font-medium">{txt}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    );
                },
                className: 'w-28',
            },
            {
                key: 'medicamento',
                header: t('columns.medicamento'),
                cell: (p) =>
                    p.medicamento ? (
                        <StatBadge label={t('row.medicamento_si')} value="" variant="primary" />
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
                className: 'w-32',
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
                cell: (p) =>
                    p.creado_por ? (
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCircle className="size-4" strokeWidth={2.25} />
                            </span>
                            <div className="flex flex-col leading-tight">
                                <span className="text-xs font-medium text-foreground">{p.creado_por.name}</span>
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
                        <span className="text-xs text-muted-foreground">{t('row.system')}</span>
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
                        <ProductoRowActions
                            producto={p}
                            onEdit={(row) => setModal({ type: 'edit', producto: row })}
                            onDelete={(row) => setModal({ type: 'delete', producto: row })}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [t, i18n.language, canSeeAudit, canUpdate, canDelete]);

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        { label: t('stats.total'), value: stats.total, variant: 'info', icon: Package },
                        { label: t('stats.active'), value: stats.activos, variant: 'success', icon: Package },
                        { label: t('stats.inactive'), value: stats.inactivos, variant: 'muted', icon: PowerOff as LucideIcon },
                        { label: t('stats.filters'), value: activeFiltersCount, variant: 'warning', icon: Filter },
                        { label: t('stats.matches'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                    ]}
                    action={
                        <div className="flex flex-row items-center gap-2">
                            {canView ? (
                                <Button asChild variant="outline" className="cursor-pointer gap-2">
                                    <a href={exportUrl} download>
                                        <Download className="size-4 shrink-0 opacity-70" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">{t('common:actions.export_xlsx')}</span>
                                    </a>
                                </Button>
                            ) : null}
                            <Can permission="productos.create">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="inline-flex">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setModal({ type: 'bulk' })}
                                                disabled={productsLimitReached}
                                                className="cursor-pointer gap-2"
                                            >
                                                <Upload className="size-4" strokeWidth={2.5} />
                                                <span className="hidden sm:inline">{t('actions.bulk_import')}</span>
                                                <span className="sm:hidden">{t('actions.bulk_import_short')}</span>
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    {productsLimitReached ? (
                                        <TooltipContent side="bottom" className="max-w-xs">
                                            {t('plan_limit.max_productos')}
                                        </TooltipContent>
                                    ) : null}
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="inline-flex">
                                            <Button
                                                type="button"
                                                onClick={() => setModal({ type: 'create' })}
                                                disabled={productsLimitReached}
                                                className="cursor-pointer gap-2"
                                            >
                                                <Plus className="size-4" strokeWidth={2.5} />
                                                <span className="hidden sm:inline">{t('actions.new')}</span>
                                                <span className="sm:hidden">{t('actions.new_short')}</span>
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    {productsLimitReached ? (
                                        <TooltipContent side="bottom" className="max-w-xs">
                                            {t('plan_limit.max_productos')}
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
                    ariaLiveMessage={t('common:aria.results_count_other', { count: stats.coincidencias })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('search_placeholder')}
                        >
                            <div className="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:gap-3">
                                <div className="min-w-0 flex-1 sm:max-w-56">
                                    <Select
                                        value={filters.categoria_id && filters.categoria_id !== '' ? filters.categoria_id : '__all__'}
                                        onValueChange={(v) => applyFilter({ categoria_id: v === '__all__' ? '' : v })}
                                    >
                                        <SelectTrigger
                                            id="filtro-categoria"
                                            className="h-9 w-full min-w-0 cursor-pointer"
                                            aria-label={t('filter_categoria')}
                                        >
                                            <SelectValue placeholder={t('filter_categoria_placeholder')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__all__">{t('filter_categoria_all')}</SelectItem>
                                            {categoriaOptions.map((c) => (
                                                <SelectItem key={c.id} value={c.id}>
                                                    {c.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <FilterChips
                                    ariaLabel={t('filter_estado')}
                                    value={filters.estado}
                                    onChange={(estado) => applyFilter({ estado })}
                                    options={estadoOptions}
                                />
                            </div>
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
                                categoria_id:
                                    filters.categoria_id && filters.categoria_id !== '' ? filters.categoria_id : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Pill}
                            title={activeFiltersCount > 0 ? t('empty.no_results_title') : t('empty.no_records_title')}
                            description={activeFiltersCount > 0 ? t('empty.no_results_description') : t('empty.no_records_description')}
                            action={
                                activeFiltersCount === 0 && canCreateProduct ? (
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

            <ProductoBulkImportModal
                open={modal.type === 'bulk'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
            />

            <ProductoFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                producto={modal.type === 'edit' ? modal.producto : null}
                categoriaOptions={categoriaOptions}
                unidadOptions={unidadOptions}
                sedeOptions={sedeOptions}
                canCreateUnidad={canCreate || canUpdate}
            />

            <ProductoDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                producto={modal.type === 'delete' ? modal.producto : null}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Inventario' }, { title: 'Productos', href: '/inventario/productos' }]}>
        {page}
    </AppLayout>
);
