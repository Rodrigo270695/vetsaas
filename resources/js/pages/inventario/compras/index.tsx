import { Head } from '@inertiajs/react';
import { Download, FileDown, ListOrdered, Package, Plus, ScreenShare, SlidersHorizontal, Store, Trash2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { DataPagination, DataTable, DataToolbar, EmptyState, PageHeader } from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import inventario from '@/routes/inventario';
import { exportMethod as comprasExportExcel } from '@/routes/inventario/compras';
import type { Paginated } from '@/types';
import { CompraAnularDialog } from './components/compra-anular-dialog';
import { CompraFormModal } from './components/compra-form-modal';
import { CompraLineasDialog } from './components/compra-lineas-dialog';
import type {
    CompraFila,
    CompraFilters,
    CompraFiltroUi,
    CompraStats,
    ProductoOptionCompra,
    ProveedorOptionCompra,
    SedeOptionCompra,
} from './types';

type Props = {
    compras: Paginated<CompraFila>;
    filters: CompraFilters;
    stats: CompraStats;
    sedeOptions: SedeOptionCompra[];
    proveedorOptions: ProveedorOptionCompra[];
    productoOptions: ProductoOptionCompra[];
    sinSedes: boolean;
    compra_filtro_ui: CompraFiltroUi;
};

type TableExtraFilters = {
    sede_id: string;
    proveedor_id: string | null;
    fecha_desde: string;
    fecha_hasta: string;
};

const DEFAULT_PER_PAGE = 10;

const PROVEEDOR_FILTRO_TODOS = '__all__';

export default function Index({
    compras: paginated,
    filters,
    stats,
    sedeOptions,
    proveedorOptions,
    productoOptions,
    sinSedes,
    compra_filtro_ui,
}: Props) {
    const { t, i18n } = useTranslation(['compras-inventario', 'common']);
    const { can } = usePermission();
    const canCreate = can('compras.create');
    const canView = can('compras.view');
    const canDelete = can('compras.delete');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } = useDataTablePage<TableExtraFilters>({
        routeUrl: inventario.compras.index.url(),
        initialFilters: filters,
        only: ['compras', 'filters', 'stats', 'sedeOptions', 'proveedorOptions', 'productoOptions', 'sinSedes', 'compra_filtro_ui'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.inventario.compras.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [modalOpen, setModalOpen] = useState(false);
    const [lineasOpen, setLineasOpen] = useState(false);
    const [anularOpen, setAnularOpen] = useState(false);
    const [selectedCompra, setSelectedCompra] = useState<CompraFila | null>(null);

    const defaultSedeId = sedeOptions[0]?.id ?? '';
    const sedeFilterActive =
        !sinSedes && sedeOptions.length > 1 && filters.sede_id !== '' && filters.sede_id !== defaultSedeId;
    const proveedorFilterActive = Boolean(filters.proveedor_id);

    const activeFiltersCount = useMemo(() => {
        let count = 0;

        if (filters.search) {
            count += 1;
        }

        if (filters.sort) {
            count += 1;
        }

        if (filters.per_page !== DEFAULT_PER_PAGE) {
            count += 1;
        }

        if (sedeFilterActive) {
            count += 1;
        }

        if (proveedorFilterActive) {
            count += 1;
        }

        if (compra_filtro_ui.fuera_del_mes_actual) {
            count += 1;
        }

        return count;
    }, [
        filters.search,
        filters.sort,
        filters.per_page,
        sedeFilterActive,
        proveedorFilterActive,
        compra_filtro_ui.fuera_del_mes_actual,
    ]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();

        if (filters.search) {
            params.set('search', filters.search);
        }

        if (filters.sort) {
            params.set('sort', filters.sort);
        }

        if (filters.direction) {
            params.set('direction', filters.direction);
        }

        const sedeParaExport = filters.sede_id && filters.sede_id !== '' ? filters.sede_id : defaultSedeId;

        if (sedeParaExport) {
            params.set('sede_id', sedeParaExport);
        }

        if (filters.proveedor_id) {
            params.set('proveedor_id', filters.proveedor_id);
        }

        params.set('fecha_desde', filters.fecha_desde);
        params.set('fecha_hasta', filters.fecha_hasta);

        const qs = params.toString();

        return qs.length > 0 ? `${comprasExportExcel.url()}?${qs}` : comprasExportExcel.url();
    }, [
        filters.search,
        filters.sort,
        filters.direction,
        filters.sede_id,
        filters.proveedor_id,
        filters.fecha_desde,
        filters.fecha_hasta,
        defaultSedeId,
    ]);

    const proveedorComboboxOptions = useMemo<readonly ComboboxOption[]>(() => {
        const base: ComboboxOption[] = [{ value: PROVEEDOR_FILTRO_TODOS, label: t('filters.proveedor_all') }];

        for (const p of proveedorOptions) {
            base.push({
                value: p.id,
                label: `${p.razon_social} (${p.ruc})`,
            });
        }

        return base;
    }, [proveedorOptions, t]);

    const openLineas = useCallback((row: CompraFila) => {
        setSelectedCompra(row);
        setLineasOpen(true);
    }, []);

    const openAnular = useCallback((row: CompraFila) => {
        setSelectedCompra(row);
        setAnularOpen(true);
    }, []);

    const columns = useMemo<DataTableColumn<CompraFila>[]>(() => {
        return [
            {
                key: 'fecha_documento',
                header: t('columns.fecha'),
                sortable: true,
                cell: (row) => (
                    <span className="text-sm text-foreground">
                        {new Date(row.fecha_documento).toLocaleDateString(i18n.language, {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                        })}
                    </span>
                ),
                className: 'w-32',
            },
            {
                key: 'documento',
                header: t('columns.documento'),
                sortable: false,
                cell: (row) => {
                    const parts = [row.serie, row.numero_documento].filter(Boolean);
                    const doc = parts.length > 0 ? parts.join('-') : '—';

                    return <span className="font-mono text-xs text-foreground">{doc}</span>;
                },
            },
            {
                key: 'proveedor',
                header: t('columns.proveedor'),
                cell: (row) =>
                    row.proveedor ? (
                        <div className="flex min-w-0 flex-col">
                            <span className="truncate text-sm font-medium text-foreground">{row.proveedor.razon_social}</span>
                            <span className="font-mono text-xs text-muted-foreground">{row.proveedor.ruc}</span>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'lineas_count',
                header: t('columns.lineas'),
                cell: (row) => <span className="text-sm tabular-nums text-muted-foreground">{row.lineas_count ?? 0}</span>,
                className: 'w-20',
            },
            {
                key: 'costos',
                header: t('columns.costos'),
                cell: (row) => (
                    <Button type="button" variant="outline" size="sm" className="h-8 gap-1 px-2" onClick={() => openLineas(row)}>
                        <ListOrdered className="size-3.5 text-primary" strokeWidth={2.25} />
                        <span className="hidden sm:inline">{t('actions.lineas_costos')}</span>
                    </Button>
                ),
                className: 'w-36',
            },
            {
                key: 'total',
                header: t('columns.total'),
                cell: (row) =>
                    row.total != null ? (
                        <span className="text-sm tabular-nums text-foreground">
                            {row.moneda}{' '}
                            {Number(row.total).toLocaleString(i18n.language, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
                className: 'w-36',
            },
            {
                key: 'factura',
                header: t('columns.factura'),
                cell: (row) =>
                    row.factura_path && canView ? (
                        <Button variant="outline" size="sm" className="h-8 gap-1 border-emerald-200 px-2 hover:bg-emerald-50 dark:border-emerald-900 dark:hover:bg-emerald-950/40" asChild>
                            <a href={inventario.compras.factura.url(row.id)} target="_blank" rel="noreferrer" className="text-emerald-700 dark:text-emerald-400">
                                <FileDown className="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" strokeWidth={2.25} />
                                <span className="hidden sm:inline">{t('actions.download_factura')}</span>
                            </a>
                        </Button>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
                className: 'w-36',
            },
            {
                key: 'acciones',
                header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                align: 'right',
                cell: (row) =>
                    canDelete ? (
                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                onClick={() => openAnular(row)}
                                aria-label={t('anular.confirm')}
                            >
                                <Trash2 className="size-4" strokeWidth={2.25} />
                            </Button>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground md:sr-only">—</span>
                    ),
                className: 'w-14',
            },
        ];
    }, [t, i18n.language, canView, canDelete, openLineas, openAnular]);

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        { label: t('stats.total_sede'), value: stats.total, variant: 'info', icon: Package },
                        { label: t('stats.coincidencias'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                        { label: t('stats.filtros'), value: activeFiltersCount, variant: 'warning', icon: SlidersHorizontal },
                    ]}
                    action={
                        <div className="flex flex-row flex-wrap items-center justify-end gap-2">
                            {canView && !sinSedes ? (
                                <Button asChild variant="outline" className="h-10 shrink-0 cursor-pointer gap-2 px-3 font-normal">
                                    <a href={exportUrl} download>
                                        <Download className="size-4 shrink-0 opacity-70" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">{t('common:actions.export_xlsx')}</span>
                                    </a>
                                </Button>
                            ) : null}
                            <Can permission="compras.create">
                                <Button
                                    type="button"
                                    onClick={() => setModalOpen(true)}
                                    className="cursor-pointer gap-2"
                                    disabled={sinSedes || productoOptions.length === 0}
                                >
                                    <Plus className="size-4" strokeWidth={2.5} />
                                    <span className="hidden sm:inline">{t('actions.new')}</span>
                                    <span className="sm:hidden">{t('actions.new_short')}</span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                {sinSedes ? (
                    <EmptyState
                        icon={Store}
                        title={t('empty.sin_sedes_title')}
                        description={t('empty.sin_sedes_description')}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={paginated.data}
                        rowKey={(row) => row.id}
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
                                filtersClassName="sm:flex-1 sm:min-w-0"
                            >
                                <div
                                    className={cn(
                                        'flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:gap-3 sm:justify-between',
                                    )}
                                >
                                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                                        <div className="min-w-0 w-full sm:w-auto sm:max-w-56">
                                            <Select
                                                value={filters.sede_id || defaultSedeId}
                                                onValueChange={(sede_id) => applyFilter({ sede_id })}
                                                disabled={isLoading || sedeOptions.length <= 1}
                                            >
                                                <SelectTrigger className="h-10 w-full min-w-0 cursor-pointer" aria-label={t('filters.sede')}>
                                                    <SelectValue placeholder={t('filters.sede')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {sedeOptions.map((s) => (
                                                        <SelectItem key={s.id} value={s.id}>
                                                            {s.nombre}{' '}
                                                            <span className="font-mono text-xs text-muted-foreground">{s.codigo}</span>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="min-w-0 w-full sm:w-auto sm:min-w-[12rem] sm:max-w-[20rem]">
                                            <Combobox
                                                id="filtro-proveedor-compra"
                                                options={proveedorComboboxOptions}
                                                value={filters.proveedor_id ?? PROVEEDOR_FILTRO_TODOS}
                                                onChange={(v) => {
                                                    if (v === null || v === PROVEEDOR_FILTRO_TODOS) {
                                                        applyFilter({ proveedor_id: null });

                                                        return;
                                                    }

                                                    applyFilter({ proveedor_id: v });
                                                }}
                                                placeholder={t('filters.proveedor')}
                                                searchPlaceholder={t('filters.proveedor_search')}
                                                emptyMessage={t('filters.proveedor_empty')}
                                                disabled={isLoading}
                                                className="h-10"
                                            />
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 justify-start sm:justify-end">
                                        <AtencionDateRangeFilter
                                            desde={filters.fecha_desde}
                                            hasta={filters.fecha_hasta}
                                            defaultDesde={compra_filtro_ui.default_desde}
                                            defaultHasta={compra_filtro_ui.default_hasta}
                                            disabled={isLoading}
                                            translationNs="compras-inventario"
                                            triggerClassName="h-10 min-w-[12rem]"
                                            onApply={(desde, hasta) => applyFilter({ fecha_desde: desde, fecha_hasta: hasta })}
                                        />
                                    </div>
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
                                    sede_id: filters.sede_id && filters.sede_id !== '' ? filters.sede_id : undefined,
                                    proveedor_id: filters.proveedor_id ?? undefined,
                                    fecha_desde: filters.fecha_desde,
                                    fecha_hasta: filters.fecha_hasta,
                                }}
                            />
                        }
                        emptyState={
                            <EmptyState
                                icon={Package}
                                title={activeFiltersCount > 0 ? t('empty.no_results_title') : t('empty.no_records_title')}
                                description={
                                    activeFiltersCount > 0 ? t('empty.no_results_description') : t('empty.no_records_description')
                                }
                                action={
                                    activeFiltersCount === 0 && canCreate && productoOptions.length > 0 ? (
                                        <Button type="button" onClick={() => setModalOpen(true)} className="cursor-pointer gap-2">
                                            <Plus className="size-4" strokeWidth={2.5} />
                                            {t('actions.create_first')}
                                        </Button>
                                    ) : undefined
                                }
                            />
                        }
                    />
                )}
            </div>

            <CompraFormModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                sedeOptions={sedeOptions}
                proveedorOptions={proveedorOptions}
                productoOptions={productoOptions}
                defaultSedeId={filters.sede_id || defaultSedeId}
            />

            <CompraLineasDialog open={lineasOpen} onOpenChange={setLineasOpen} compra={selectedCompra} />

            <Can permission="compras.delete">
                <CompraAnularDialog open={anularOpen} onOpenChange={setAnularOpen} compra={selectedCompra} />
            </Can>
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Inventario' }, { title: 'Compras', href: '/inventario/compras' }]}>{page}</AppLayout>
);
