import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Download, Filter, Package, ScreenShare, SlidersHorizontal, Store, Upload } from 'lucide-react';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import inventario from '@/routes/inventario';
import type { Paginated } from '@/types';
import { StockAdjustDialog } from './components/stock-adjust-dialog';
import { StockBulkImportModal } from './components/stock-bulk-import-modal';
import type { SedeOption, StockFilters, StockProductoFila, StockStats } from './types';

type Props = {
    productos: Paginated<StockProductoFila>;
    filters: StockFilters;
    stats: StockStats;
    sedeOptions: SedeOption[];
    sinSedes: boolean;
};

type TableExtraFilters = {
    sede_id: string;
};

const DEFAULT_PER_PAGE = 10;

function formatCantidad(value: string | number, locale: string): string {
    const n = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(n)) {
        return String(value);
    }
    return n.toLocaleString(locale, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}

export default function Index({ productos: paginated, filters, stats, sedeOptions, sinSedes }: Props) {
    const { t, i18n } = useTranslation(['stock-inventario', 'common']);
    const { can } = usePermission();
    const canAdjust = can('stock.adjust');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } = useDataTablePage<TableExtraFilters>({
        routeUrl: inventario.stock.url(),
        initialFilters: filters,
        only: ['productos', 'filters', 'stats', 'sedeOptions', 'sinSedes'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.inventario.stock.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [adjustProducto, setAdjustProducto] = useState<StockProductoFila | null>(null);
    const [bulkOpen, setBulkOpen] = useState(false);
    const closeAdjust = useCallback(() => setAdjustProducto(null), []);
    const canView = can('stock.view');

    const defaultSedeId = sedeOptions[0]?.id ?? '';
    const sedeFilterActive =
        !sinSedes && sedeOptions.length > 1 && filters.sede_id !== '' && filters.sede_id !== defaultSedeId;

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.sede_id) {
            params.set('sede_id', filters.sede_id);
        }
        if (filters.search) {
            params.set('search', filters.search);
        }
        if (filters.sort) {
            params.set('sort', filters.sort);
        }
        if (filters.direction) {
            params.set('direction', filters.direction);
        }
        const qs = params.toString();
        return `/inventario/stock/export${qs ? `?${qs}` : ''}`;
    }, [filters.sede_id, filters.search, filters.sort, filters.direction]);

    const sedeFilterOptions: readonly FilterChip<string>[] = useMemo(
        () =>
            sedeOptions.map((s) => ({
                value: s.id,
                label: `${s.nombre} · ${s.codigo}`,
                icon: <Store className="size-3.5" strokeWidth={2.25} />,
            })),
        [sedeOptions],
    );

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        if (sedeFilterActive) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.per_page, sedeFilterActive]);

    const columns = useMemo<DataTableColumn<StockProductoFila>[]>(() => {
        const base: DataTableColumn<StockProductoFila>[] = [
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
                key: 'cantidad_stock',
                header: t('columns.cantidad'),
                sortable: !sinSedes,
                cell: (p) => (
                    <span className="tabular-nums text-sm font-medium">
                        {sinSedes ? '—' : formatCantidad(p.cantidad_stock, i18n.language)}
                    </span>
                ),
                className: 'w-28',
            },
            {
                key: 'medicamento',
                header: t('columns.medicamento'),
                sortable: true,
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

        if (canAdjust && !sinSedes) {
            base.push({
                key: 'acciones',
                header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                align: 'right',
                cell: (p) => (
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            variant="default"
                            size="sm"
                            className="h-7 min-h-7 cursor-pointer gap-1 rounded-md px-2 text-xs font-medium shadow-sm ring-1 ring-primary/15 hover:ring-primary/30 has-[>svg]:px-2"
                            onClick={() => setAdjustProducto(p)}
                        >
                            <SlidersHorizontal className="size-3 shrink-0 opacity-90" strokeWidth={2.5} aria-hidden />
                            {t('row.ajustar')}
                        </Button>
                    </div>
                ),
                className: 'w-28',
            });
        }

        return base;
    }, [t, i18n.language, sinSedes, canAdjust]);

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        { label: t('stats.total_catalogo'), value: stats.total, variant: 'info', icon: Package },
                        { label: t('stats.coincidencias'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                        { label: t('stats.filtros'), value: activeFiltersCount, variant: 'warning', icon: Filter as LucideIcon },
                    ]}
                    action={
                        <div className="flex flex-row items-center gap-2">
                            {canView && !sinSedes ? (
                                <Button asChild variant="outline" className="cursor-pointer gap-2">
                                    <a href={exportUrl} download>
                                        <Download className="size-4 shrink-0 opacity-70" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">{t('common:actions.export_xlsx')}</span>
                                    </a>
                                </Button>
                            ) : null}
                            <Can permission="stock.adjust">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setBulkOpen(true)}
                                    disabled={sinSedes}
                                    className="cursor-pointer gap-2"
                                >
                                    <Upload className="size-4" strokeWidth={2.5} />
                                    <span className="hidden sm:inline">{t('actions.bulk_import')}</span>
                                    <span className="sm:hidden">{t('actions.bulk_import_short')}</span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                {sinSedes ? (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>{t('sin_sedes.title')}</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>{t('sin_sedes.description')}</span>
                            <Can permission="sedes.view">
                                <Button type="button" variant="secondary" size="sm" className="shrink-0 cursor-pointer" asChild>
                                    <Link href="/configuracion/sedes">{t('sin_sedes.link')}</Link>
                                </Button>
                            </Can>
                        </AlertDescription>
                    </Alert>
                ) : null}

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
                            {!sinSedes && sedeOptions.length > 0 ? (
                                <FilterChips
                                    ariaLabel={t('filter_sede')}
                                    value={filters.sede_id && filters.sede_id !== '' ? filters.sede_id : defaultSedeId}
                                    onChange={(v) => applyFilter({ sede_id: v })}
                                    options={sedeFilterOptions}
                                    disabled={sedeOptions.length <= 1}
                                    className="sm:min-w-56"
                                />
                            ) : null}
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
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Package}
                            title={activeFiltersCount > 0 ? t('empty.no_results_title') : t('empty.no_records_title')}
                            description={activeFiltersCount > 0 ? t('empty.no_results_description') : t('empty.no_records_description')}
                        />
                    }
                />
            </div>

            <StockAdjustDialog
                open={adjustProducto !== null}
                onOpenChange={(open) => {
                    if (!open) closeAdjust();
                }}
                producto={adjustProducto}
                sedeId={filters.sede_id}
            />

            <StockBulkImportModal open={bulkOpen} onOpenChange={setBulkOpen} />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Inventario' }, { title: 'Stock', href: '/inventario/stock' }]}>{page}</AppLayout>
);
