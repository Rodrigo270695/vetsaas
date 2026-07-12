import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    CalendarClock,
    Filter,
    Package,
    PackageX,
    ScreenShare,
    SlidersHorizontal,
    Store,
} from 'lucide-react';
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
import type { DataTableColumn, FilterChip, StatBadgeVariant } from '@/components/data-page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { alertas as inventarioAlertas } from '@/routes/inventario';
import type { Paginated } from '@/types';
import { StockAdjustDialog } from '../stock/components/stock-adjust-dialog';
import type { StockProductoFila } from '../stock/types';
import type {
    AlertaLoteFila,
    AlertaModoListado,
    AlertaProductoFila,
    AlertaStockFilters,
    AlertaStockStats,
    AlertaTipoFiltro,
    SedeOptionAlerta,
} from './types';

type Props = {
    modo: AlertaModoListado;
    productos: Paginated<AlertaProductoFila>;
    lotes: Paginated<AlertaLoteFila>;
    filters: AlertaStockFilters;
    stats: AlertaStockStats;
    sedeOptions: SedeOptionAlerta[];
    sinSedes: boolean;
    dias_alerta_vencimiento: number;
};

type TableExtraFilters = Pick<AlertaStockFilters, 'sede_id' | 'tipo_alerta'>;

const DEFAULT_PER_PAGE = 10;
const DEFAULT_TIPO_ALERTA: AlertaTipoFiltro = 'todos';

function tipoAlertaVariant(tipo: string): StatBadgeVariant {
    if (tipo === 'agotado' || tipo === 'vencido') {
        return 'danger';
    }

    if (tipo === 'bajo_minimo' || tipo === 'por_vencer') {
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

function formatFecha(value: string, locale: string): string {
    const d = new Date(`${value}T12:00:00`);

    if (Number.isNaN(d.getTime())) {
        return value;
    }

    return d.toLocaleDateString(locale, { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export default function Index({
    modo,
    productos: paginatedProductos,
    lotes: paginatedLotes,
    filters,
    stats,
    sedeOptions,
    sinSedes,
    dias_alerta_vencimiento,
}: Props) {
    const { t, i18n } = useTranslation(['alertas-stock', 'common']);
    const { can } = usePermission();
    const canAdjust = can('stock.adjust');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } = useDataTablePage<TableExtraFilters>({
        routeUrl: inventarioAlertas.url(),
        initialFilters: filters,
        only: ['modo', 'productos', 'lotes', 'filters', 'stats', 'sedeOptions', 'sinSedes', 'dias_alerta_vencimiento'],
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

    const sedeFilterOptions: readonly FilterChip<string>[] = useMemo(
        () =>
            sedeOptions.map((s) => ({
                value: s.id,
                label: `${s.nombre} · ${s.codigo}`,
                icon: <Store className="size-3.5" strokeWidth={2.25} />,
            })),
        [sedeOptions],
    );

    const tipoAlertaOptions: readonly FilterChip<AlertaTipoFiltro>[] = useMemo(
        () => [
            { value: 'todos', label: t('filter_tipo_all') },
            {
                value: 'agotado',
                label: t('filter_tipo_agotado'),
                tone: 'danger',
                icon: <PackageX className="size-3.5" strokeWidth={2.25} />,
            },
            {
                value: 'bajo_minimo',
                label: t('filter_tipo_bajo'),
                tone: 'warning',
                icon: <Bell className="size-3.5" strokeWidth={2.25} />,
            },
            {
                value: 'por_vencer',
                label: t('filter_tipo_por_vencer', { dias: dias_alerta_vencimiento }),
                tone: 'warning',
                icon: <CalendarClock className="size-3.5" strokeWidth={2.25} />,
            },
            {
                value: 'vencido',
                label: t('filter_tipo_vencido'),
                tone: 'danger',
                icon: <AlertTriangle className="size-3.5" strokeWidth={2.25} />,
            },
        ],
        [dias_alerta_vencimiento, t],
    );
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

    const tipoLabel = useCallback(
        (tipo: string) => {
            if (tipo === 'agotado') {
                return t('tipos.agotado');
            }
            if (tipo === 'bajo_minimo') {
                return t('tipos.bajo_minimo');
            }
            if (tipo === 'por_vencer') {
                return t('tipos.por_vencer', { dias: dias_alerta_vencimiento });
            }
            if (tipo === 'vencido') {
                return t('tipos.vencido');
            }

            return tipo;
        },
        [t, dias_alerta_vencimiento],
    );

    const columnsStock = useMemo<DataTableColumn<AlertaProductoFila>[]>(() => {
        const base: DataTableColumn<AlertaProductoFila>[] = [
            {
                key: 'tipo_alerta',
                header: t('columns.tipo'),
                sortable: !sinSedes,
                cell: (p) => (
                    <StatBadge label={tipoLabel(p.tipo_alerta)} value="" variant={tipoAlertaVariant(p.tipo_alerta)} />
                ),
                className: 'w-40',
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
                        <span className="tabular-nums text-sm text-muted-foreground">
                            {formatCantidad(p.stock_minimo, i18n.language)}
                        </span>
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
    }, [t, i18n.language, sinSedes, canAdjust, tipoLabel]);

    const columnsLotes = useMemo<DataTableColumn<AlertaLoteFila>[]>(
        () => [
            {
                key: 'tipo_alerta',
                header: t('columns.tipo'),
                cell: (row) => (
                    <StatBadge label={tipoLabel(row.tipo_alerta)} value="" variant={tipoAlertaVariant(row.tipo_alerta)} />
                ),
                className: 'w-44',
            },
            {
                key: 'nombre',
                header: t('columns.producto'),
                sortable: true,
                cell: (row) => (
                    <div className="flex min-w-0 flex-col">
                        <span className="truncate font-medium text-foreground">{row.producto_nombre}</span>
                        {row.producto_slug ? (
                            <span className="font-mono text-[0.7rem] text-muted-foreground">{row.producto_slug}</span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'sku',
                header: t('columns.sku'),
                sortable: true,
                cell: (row) =>
                    row.producto_sku ? (
                        <span className="font-mono text-xs">{row.producto_sku}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
                className: 'w-28',
            },
            {
                key: 'numero_lote',
                header: t('columns.lote'),
                sortable: true,
                cell: (row) => <span className="font-mono text-xs">{row.numero_lote}</span>,
                className: 'w-32',
            },
            {
                key: 'fecha_vencimiento',
                header: t('columns.vencimiento'),
                sortable: true,
                cell: (row) => (
                    <span className="tabular-nums text-sm">{formatFecha(row.fecha_vencimiento, i18n.language)}</span>
                ),
                className: 'w-32',
            },
            {
                key: 'dias_restantes',
                header: t('columns.dias'),
                sortable: true,
                cell: (row) => {
                    const dias = row.dias_restantes;

                    if (dias < 0) {
                        return (
                            <span className="text-sm font-medium text-destructive">
                                {t('lote.dias_vencido', { dias: Math.abs(dias) })}
                            </span>
                        );
                    }

                    if (dias === 0) {
                        return <span className="text-sm font-medium text-destructive">{t('lote.vence_hoy')}</span>;
                    }

                    return (
                        <span className="tabular-nums text-sm text-muted-foreground">
                            {t('lote.dias_restantes', { dias })}
                        </span>
                    );
                },
                className: 'w-36',
            },
            {
                key: 'cantidad_lote',
                header: t('columns.cantidad_lote'),
                sortable: true,
                cell: (row) => (
                    <span className="tabular-nums text-sm font-medium">{formatCantidad(row.cantidad_lote, i18n.language)}</span>
                ),
                className: 'w-28',
            },
        ],
        [t, i18n.language, tipoLabel],
    );

    const pageDescription =
        modo === 'lotes'
            ? t('description_lotes', { dias: dias_alerta_vencimiento })
            : t('description');

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={pageDescription}
                    stats={[
                        { label: t('stats.agotados'), value: stats.agotados, variant: 'danger', icon: Package },
                        { label: t('stats.bajo_minimo'), value: stats.bajo_minimo, variant: 'warning', icon: Bell },
                        {
                            label: t('stats.por_vencer', { dias: dias_alerta_vencimiento }),
                            value: stats.por_vencer,
                            variant: 'warning',
                            icon: CalendarClock,
                        },
                        { label: t('stats.vencidos'), value: stats.vencidos, variant: 'danger', icon: AlertTriangle },
                        { label: t('stats.coincidencias'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                        { label: t('stats.filtros'), value: activeFiltersCount, variant: 'muted', icon: Filter as LucideIcon },
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
                    columns={modo === 'lotes' ? columnsLotes : columnsStock}
                    data={modo === 'lotes' ? paginatedLotes.data : paginatedProductos.data}
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
                            placeholder={modo === 'lotes' ? t('search_placeholder_lotes') : t('search_placeholder')}
                            filtersClassName="sm:flex-1 sm:min-w-0"
                        >
                            <div className="flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                                {!sinSedes && sedeOptions.length > 0 ? (
                                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                                        <FilterChips
                                            ariaLabel={t('filter_sede')}
                                            value={
                                                filters.sede_id && filters.sede_id !== ''
                                                    ? filters.sede_id
                                                    : defaultSedeId
                                            }
                                            onChange={(v) => applyFilter({ sede_id: v })}
                                            options={sedeFilterOptions}
                                            disabled={sedeOptions.length <= 1}
                                            className="sm:min-w-56"
                                        />
                                        <FilterChips
                                            ariaLabel={t('filter_tipo')}
                                            value={filters.tipo_alerta}
                                            onChange={(v) => applyFilter({ tipo_alerta: v })}
                                            options={tipoAlertaOptions}
                                            className="sm:min-w-48"
                                        />
                                    </div>
                                ) : null}
                            </div>
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={modo === 'lotes' ? paginatedLotes : paginatedProductos}
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
                            icon={modo === 'lotes' ? CalendarClock : Bell}
                            title={activeFiltersCount > 0 ? t('empty.no_results_title') : t('empty.no_records_title')}
                            description={
                                activeFiltersCount > 0
                                    ? t('empty.no_results_description')
                                    : modo === 'lotes'
                                      ? t('empty.no_records_lotes_description', { dias: dias_alerta_vencimiento })
                                      : t('empty.no_records_description')
                            }
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
