import { Head, Link } from '@inertiajs/react';
import { Download, Eye, Plus, ReceiptText } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import caja from '@/routes/caja';
import { exportMethod as ventasExportExcel } from '@/routes/caja/ventas';
import type { VentaEstadoFiltro, VentasIndexProps, VentaRow } from './types';

type TableExtraFilters = {
    estado: VentaEstadoFiltro;
    fecha_desde: string;
    fecha_hasta: string;
};

const DEFAULT_PER_PAGE = 15;
const DEFAULT_ESTADO: VentaEstadoFiltro = 'todas';

function formatMonto(amount: string, moneda: string, locale: string): string {
    const n = Number(amount);

    if (Number.isNaN(n)) {
        return amount;
    }

    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return new Intl.NumberFormat(locale, { style: 'currency', currency: cur }).format(n);
}

export default function Index({ ventas: paginated, filters, stats, venta_filtro_ui }: VentasIndexProps) {
    const { t, i18n } = useTranslation(['caja', 'common']);
    const { can } = usePermission();
    const canView = can('ventas.view');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } = useDataTablePage<TableExtraFilters>({
        routeUrl: caja.ventas.index.url(),
        initialFilters: filters,
        only: ['ventas', 'filters', 'stats', 'venta_filtro_ui'],
        errorMessage: t('caja:ventas.toast_load_error'),
        storageKey: 'vetsaas.caja.ventas.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estado = (filters.estado ?? DEFAULT_ESTADO) as VentaEstadoFiltro;

    const activeFiltersCount = useMemo(() => {
        let n = 0;

        if (filters.search) {
            n += 1;
        }

        if (estado !== DEFAULT_ESTADO) {
            n += 1;
        }

        if (venta_filtro_ui.fuera_del_mes_actual) {
            n += 1;
        }

        return n;
    }, [estado, filters.search, venta_filtro_ui.fuera_del_mes_actual]);

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

        if (filters.estado !== DEFAULT_ESTADO) {
            params.set('estado', filters.estado);
        }

        params.set('fecha_desde', filters.fecha_desde);
        params.set('fecha_hasta', filters.fecha_hasta);

        const qs = params.toString();

        return qs.length > 0 ? `${ventasExportExcel.url()}?${qs}` : ventasExportExcel.url();
    }, [
        filters.search,
        filters.sort,
        filters.direction,
        filters.estado,
        filters.fecha_desde,
        filters.fecha_hasta,
    ]);

    const estadoOptions: readonly FilterChip<VentaEstadoFiltro>[] = useMemo(
        () => [
            { value: 'todas', label: t('caja:ventas.estado.todas') },
            { value: 'pagado', label: t('caja:ventas.estado.pagado') },
            { value: 'pendiente', label: t('caja:ventas.estado.pendiente') },
            { value: 'parcial', label: t('caja:ventas.estado.parcial') },
            { value: 'anulado', label: t('caja:ventas.estado.anulado') },
        ],
        [t],
    );

    const columns: DataTableColumn<VentaRow>[] = useMemo(
        () => [
            {
                key: 'numero',
                header: t('caja:ventas.columns.numero'),
                cell: (row) => (
                    <Link
                        href={caja.ventas.show.url(row.id)}
                        className="font-mono text-sm tabular-nums text-primary hover:underline"
                        title={row.numero_display !== row.numero ? row.numero : undefined}
                    >
                        {row.numero_display}
                    </Link>
                ),
                sortable: true,
                sortKey: 'numero',
            },
            {
                key: 'fecha',
                header: t('caja:ventas.columns.fecha'),
                cell: (row) =>
                    row.created_at
                        ? new Intl.DateTimeFormat(i18n.language, {
                              dateStyle: 'short',
                              timeStyle: 'short',
                          }).format(new Date(row.created_at))
                        : '—',
                sortable: true,
                sortKey: 'created_at',
            },
            {
                key: 'cliente',
                header: t('caja:ventas.columns.cliente'),
                cell: (row) => (
                    <div className="flex flex-col gap-0.5">
                        <span className="font-medium">{row.cliente}</span>
                        {row.paciente ? (
                            <span className="text-xs text-muted-foreground">{row.paciente}</span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'sede',
                header: t('caja:ventas.columns.sede'),
                cell: (row) => row.sede,
            },
            {
                key: 'total',
                header: t('caja:ventas.columns.total'),
                cell: (row) => formatMonto(row.total, row.moneda, i18n.language),
                sortable: true,
                sortKey: 'total',
            },
            {
                key: 'estado',
                header: t('caja:ventas.columns.estado'),
                cell: (row) => t(`caja:ventas.estado_valor.${row.estado}`, { defaultValue: row.estado }),
                sortable: true,
                sortKey: 'estado',
            },
            {
                key: 'fel',
                header: t('caja:ventas.columns.fel'),
                cell: (row) =>
                    t(`caja:ventas.fel.${row.fel_estado}`, { defaultValue: row.fel_estado }),
            },
            {
                key: 'acciones',
                header: t('caja:ventas.columns.acciones'),
                cell: (row) => (
                    <Button variant="ghost" size="icon" className="size-8" asChild>
                        <Link
                            href={caja.ventas.show.url(row.id)}
                            aria-label={t('caja:ventas.actions.ver_detalle', { numero: row.numero_display })}
                        >
                            <Eye className="size-4" aria-hidden />
                        </Link>
                    </Button>
                ),
            },
        ],
        [i18n.language, t],
    );

    return (
        <>
            <Head title={t('caja:ventas.title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('caja:ventas.title')}
                    description={t('caja:ventas.description')}
                    stats={[
                        { label: t('caja:ventas.stats.total'), value: stats.total, variant: 'muted' },
                        {
                            label: t('caja:ventas.stats.matches'),
                            value: stats.coincidencias,
                            variant: 'primary',
                        },
                    ]}
                    action={
                        <div className="flex flex-row flex-wrap items-center justify-end gap-2">
                            {canView ? (
                                <Button asChild variant="outline" className="h-10 shrink-0 cursor-pointer gap-2 px-3 font-normal">
                                    <a href={exportUrl} download>
                                        <Download className="size-4 shrink-0 opacity-70" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">{t('common:actions.export_xlsx')}</span>
                                    </a>
                                </Button>
                            ) : null}
                            <Can permission="ventas.create">
                                <Button asChild size="sm" className="gap-1">
                                    <Link href={caja.ventas.create.url()}>
                                        <Plus className="size-4" />
                                        {t('caja:ventas.actions.nueva')}
                                    </Link>
                                </Button>
                            </Can>
                        </div>
                    }
                />

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
                            placeholder={t('caja:ventas.search_placeholder')}
                            filtersClassName="sm:flex-1 sm:min-w-0"
                        >
                            <div
                                className={cn(
                                    'flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3',
                                )}
                            >
                                <FilterChips
                                    ariaLabel={t('caja:ventas.filter_estado_label')}
                                    value={estado}
                                    onChange={(v) => applyFilter({ estado: v })}
                                    options={estadoOptions}
                                />
                                <div className="flex shrink-0 justify-start sm:justify-end">
                                    <AtencionDateRangeFilter
                                        desde={filters.fecha_desde}
                                        hasta={filters.fecha_hasta}
                                        defaultDesde={venta_filtro_ui.default_desde}
                                        defaultHasta={venta_filtro_ui.default_hasta}
                                        disabled={isLoading}
                                        translationNs="caja"
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
                                estado: filters.estado !== DEFAULT_ESTADO ? filters.estado : undefined,
                                fecha_desde: filters.fecha_desde,
                                fecha_hasta: filters.fecha_hasta,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={ReceiptText}
                            title={
                                activeFiltersCount > 0
                                    ? t('caja:ventas.empty.no_results_title')
                                    : t('caja:ventas.empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('caja:ventas.empty.no_results_description')
                                    : t('caja:ventas.empty.no_records_description')
                            }
                        />
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Caja' },
            { title: 'Ventas', href: caja.ventas.index.url() },
        ]}
    >
        {page}
    </AppLayout>
);
