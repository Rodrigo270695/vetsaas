import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Bell, Filter, Package, ScreenShare, SlidersHorizontal } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, StatBadgeVariant } from '@/components/data-page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
import { alertas as inventarioAlertas } from '@/routes/inventario';
import type { Paginated } from '@/types';
import { StockAdjustDialog } from '../stock/components/stock-adjust-dialog';
import type { StockProductoFila } from '../stock/types';
import type {
    AlertaProductoFila,
    AlertaStockFilters,
    AlertaStockStats,
    AlertaTipoFiltro,
    SedeOptionAlerta,
} from './types';

type Props = {
    productos: Paginated<AlertaProductoFila>;
    filters: AlertaStockFilters;
    stats: AlertaStockStats;
    sedeOptions: SedeOptionAlerta[];
    sinSedes: boolean;
};

type TableExtraFilters = Pick<AlertaStockFilters, 'sede_id' | 'tipo_alerta'>;

const DEFAULT_PER_PAGE = 10;
const DEFAULT_TIPO_ALERTA: AlertaTipoFiltro = 'todos';

function tipoAlertaVariant(tipo: string): StatBadgeVariant {
    if (tipo === 'agotado') {
        return 'danger';
    }

    if (tipo === 'bajo_minimo') {
        return 'warning';
    }

    return 'muted';
}

function formatCantidad(value: string | number, locale: string): string {
    const n = typeof value === 'string' ? Number(value) : value;

    if (Number.isNaN(n)) {
        return String(value);
    }

    return n.toLocaleString(locale, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}

export default function Index({ productos: paginated, filters, stats, sedeOptions, sinSedes }: Props) {
    const { t, i18n } = useTranslation(['alertas-stock', 'common']);
    const { can } = usePermission();
    const canAdjust = can('stock.adjust');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } = useDataTablePage<TableExtraFilters>({
        routeUrl: inventarioAlertas.url(),
        initialFilters: filters,
        only: ['productos', 'filters', 'stats', 'sedeOptions', 'sinSedes'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.inventario.alertas.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [adjustProducto, setAdjustProducto] = useState<StockProductoFila | null>(null);
    const closeAdjust = useCallback(() => setAdjustProducto(null), []);

    const defaultSedeId = sedeOptions[0]?.id ?? '';
    const sedeFilterActive =
        !sinSedes && sedeOptions.length > 1 && filters.sede_id !== '' && filters.sede_id !== defaultSedeId;
    const tipoFilterActive = filters.tipo_alerta !== DEFAULT_TIPO_ALERTA;

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

        if (tipoFilterActive) {
            count += 1;
        }

        return count;
    }, [filters.search, filters.sort, filters.per_page, sedeFilterActive, tipoFilterActive]);

    const sedeIdParaAjuste =
        filters.sede_id && filters.sede_id !== '' ? filters.sede_id : defaultSedeId !== '' ? defaultSedeId : '';

    const columns = useMemo<DataTableColumn<AlertaProductoFila>[]>(() => {
        const base: DataTableColumn<AlertaProductoFila>[] = [
            {
                key: 'tipo_alerta',
                header: t('columns.tipo'),
                sortable: !sinSedes,
                cell: (p) => {
                    const label =
                        p.tipo_alerta === 'agotado' ? t('tipos.agotado') : p.tipo_alerta === 'bajo_minimo' ? t('tipos.bajo_minimo') : p.tipo_alerta;

                    return <StatBadge label={label} value="" variant={tipoAlertaVariant(p.tipo_alerta)} />;
                },
                className: 'w-36',
            },
            {
                key: 'nombre',
                header: t('columns.producto'),
                sortable: true,
                cell: (p) => (
                    <div className="flex min-w-0 flex-col">
                        <span className="truncate font-medium text-foreground">{p.nombre}</span>
                        {p.slug ? <span className="font-mono text-[0.7rem] text-muted-foreground">{p.slug}</span> : null}
                    </div>
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
                className: 'w-28',
            },
            {
                key: 'categoria',
                header: t('columns.categoria'),
                cell: (p) =>
                    p.categoria ? (
                        <span className="text-sm text-muted-foreground">{p.categoria.nombre}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
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
                key: 'stock_minimo',
                header: t('columns.minimo'),
                sortable: true,
                cell: (p) =>
                    p.stock_minimo != null && String(p.stock_minimo) !== '' ? (
                        <span className="tabular-nums text-sm text-muted-foreground">{formatCantidad(p.stock_minimo, i18n.language)}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
                className: 'w-24',
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
                            onClick={() => setAdjustProducto(p as StockProductoFila)}
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
                        { label: t('stats.agotados'), value: stats.agotados, variant: 'danger', icon: Package },
                        { label: t('stats.bajo_minimo'), value: stats.bajo_minimo, variant: 'warning', icon: Bell },
                        { label: t('stats.coincidencias'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                        { label: t('stats.filtros'), value: activeFiltersCount, variant: 'warning', icon: Filter as LucideIcon },
                    ]}
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
                            filtersClassName="sm:flex-1 sm:min-w-0"
                        >
                            <div className="flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                                {!sinSedes && sedeOptions.length > 0 ? (
                                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                                        <div className="min-w-0 w-full sm:w-auto sm:max-w-56">
                                            <Select
                                                value={filters.sede_id && filters.sede_id !== '' ? filters.sede_id : defaultSedeId}
                                                onValueChange={(v) => applyFilter({ sede_id: v })}
                                            >
                                                <SelectTrigger
                                                    id="filtro-sede-alerta"
                                                    className="h-9 w-full min-w-0 cursor-pointer"
                                                    aria-label={t('filter_sede')}
                                                >
                                                    <SelectValue placeholder={t('filter_sede_placeholder')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {sedeOptions.map((s) => (
                                                        <SelectItem key={s.id} value={s.id}>
                                                            <span>
                                                                {s.nombre}
                                                                <span className="ml-2 font-mono text-xs text-muted-foreground">{s.codigo}</span>
                                                            </span>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="min-w-0 w-full sm:w-auto sm:max-w-48">
                                            <Select
                                                value={filters.tipo_alerta}
                                                onValueChange={(v) => applyFilter({ tipo_alerta: v as AlertaTipoFiltro })}
                                            >
                                                <SelectTrigger
                                                    id="filtro-tipo-alerta"
                                                    className="h-9 w-full min-w-0 cursor-pointer"
                                                    aria-label={t('filter_tipo')}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="todos">{t('filter_tipo_all')}</SelectItem>
                                                    <SelectItem value="agotado">{t('filter_tipo_agotado')}</SelectItem>
                                                    <SelectItem value="bajo_minimo">{t('filter_tipo_bajo')}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                ) : null}
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
                                tipo_alerta: filters.tipo_alerta !== DEFAULT_TIPO_ALERTA ? filters.tipo_alerta : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Bell}
                            title={activeFiltersCount > 0 ? t('empty.no_results_title') : t('empty.no_records_title')}
                            description={activeFiltersCount > 0 ? t('empty.no_results_description') : t('empty.no_records_description')}
                        />
                    }
                />
            </div>

            <StockAdjustDialog
                open={adjustProducto !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeAdjust();
                    }
                }}
                producto={adjustProducto}
                sedeId={sedeIdParaAjuste}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Inventario' }, { title: 'Alertas', href: '/inventario/alertas' }]}>{page}</AppLayout>
);
