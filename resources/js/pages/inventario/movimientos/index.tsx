import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ClipboardList, Download, Filter, ListOrdered } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { cn } from '@/lib/utils';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import inventario from '@/routes/inventario';
import { exportMethod as movimientosExport } from '@/routes/inventario/movimientos';
import type { Paginated } from '@/types';
import { MovimientoFormModal } from './components/movimiento-form-modal';
import type {
    MovimientoFila,
    MovimientoFiltroUi,
    MovimientoFilters,
    MovimientoStats,
    MovimientoTipoFiltro,
    ProductoOptionMovimiento,
    SedeOptionMovimiento,
} from './types';

type Props = {
    movimientos: Paginated<MovimientoFila>;
    filters: MovimientoFilters;
    stats: MovimientoStats;
    sedeOptions: SedeOptionMovimiento[];
    productoOptions: ProductoOptionMovimiento[];
    sinSedes: boolean;
    movimiento_filtro_ui: MovimientoFiltroUi;
};

type TableExtraFilters = Pick<MovimientoFilters, 'sede_id' | 'tipo' | 'creado_desde' | 'creado_hasta'>;

const DEFAULT_PER_PAGE = 10;
const DEFAULT_TIPO: MovimientoTipoFiltro = 'todos';

function tipoStatVariant(tipo: string): StatBadgeVariant {
    switch (tipo) {
        case 'entrada':
            return 'success';
        case 'salida':
            return 'warning';
        case 'merma':
            return 'danger';
        case 'ajuste':
            return 'primary';
        default:
            return 'muted';
    }
}

function formatCantidad(value: string | number, locale: string): string {
    const n = typeof value === 'string' ? Number(value) : value;

    if (Number.isNaN(n)) {
        return String(value);
    }

    return n.toLocaleString(locale, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}

function formatDelta(value: string | number, locale: string): string {
    const n = typeof value === 'string' ? Number(value) : value;

    if (Number.isNaN(n)) {
        return String(value);
    }

    const abs = Math.abs(n);

    if (n > 0) {
        return `+${abs.toLocaleString(locale, { minimumFractionDigits: 0, maximumFractionDigits: 3 })}`;
    }

    if (n < 0) {
        return `−${abs.toLocaleString(locale, { minimumFractionDigits: 0, maximumFractionDigits: 3 })}`;
    }

    return abs.toLocaleString(locale, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}

export default function Index({
    movimientos: paginated,
    filters,
    stats,
    sedeOptions,
    productoOptions,
    sinSedes,
    movimiento_filtro_ui,
}: Props) {
    const { t, i18n } = useTranslation(['movimientos-inventario', 'common']);
    const { can } = usePermission();
    const canExport = can('movimientos-stock.export');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } = useDataTablePage<TableExtraFilters>({
        routeUrl: inventario.movimientos.url(),
        initialFilters: filters,
        only: [
            'movimientos',
            'filters',
            'stats',
            'sedeOptions',
            'productoOptions',
            'sinSedes',
            'movimiento_filtro_ui',
        ],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.inventario.movimientos.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [modalOpen, setModalOpen] = useState(false);

    const defaultSedeId = sedeOptions[0]?.id ?? '';
    const sedeFilterActive =
        !sinSedes && sedeOptions.length > 1 && filters.sede_id !== '' && filters.sede_id !== defaultSedeId;
    const tipoFilterActive = filters.tipo !== DEFAULT_TIPO;

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

        if (movimiento_filtro_ui.fuera_del_mes_actual) {
            count += 1;
        }

        return count;
    }, [
        filters.search,
        filters.sort,
        filters.per_page,
        sedeFilterActive,
        tipoFilterActive,
        movimiento_filtro_ui.fuera_del_mes_actual,
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

        const sedeParaExport =
            filters.sede_id && filters.sede_id !== '' ? filters.sede_id : defaultSedeId;

        if (sedeParaExport) {
            params.set('sede_id', sedeParaExport);
        }

        if (filters.tipo !== DEFAULT_TIPO) {
            params.set('tipo', filters.tipo);
        }

        params.set('creado_desde', filters.creado_desde);
        params.set('creado_hasta', filters.creado_hasta);

        const qs = params.toString();

        return qs.length > 0 ? `${movimientosExport.url()}?${qs}` : movimientosExport.url();
    }, [
        filters.search,
        filters.sort,
        filters.direction,
        filters.sede_id,
        filters.tipo,
        filters.creado_desde,
        filters.creado_hasta,
        defaultSedeId,
    ]);

    const columns = useMemo<DataTableColumn<MovimientoFila>[]>(() => {
        return [
            {
                key: 'created_at',
                header: t('columns.fecha'),
                sortable: true,
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm text-muted-foreground">
                        {new Date(row.created_at).toLocaleString(i18n.language, {
                            dateStyle: 'short',
                            timeStyle: 'short',
                        })}
                    </span>
                ),
                className: 'w-36',
            },
            {
                key: 'nombre',
                header: t('columns.producto'),
                sortable: true,
                cell: (row) => (
                    <div className="flex min-w-0 flex-col">
                        <span className="truncate font-medium text-foreground">{row.producto?.nombre ?? '—'}</span>
                    </div>
                ),
                className: 'min-w-0',
            },
            {
                key: 'sku',
                header: t('columns.sku'),
                cell: (row) =>
                    row.producto?.sku ? (
                        <span className="font-mono text-xs">{row.producto.sku}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
                className: 'w-28',
            },
            {
                key: 'tipo',
                header: t('columns.tipo'),
                sortable: true,
                cell: (row) => {
                    const label =
                        row.tipo === 'entrada'
                            ? t('tipos.entrada')
                            : row.tipo === 'salida'
                              ? t('tipos.salida')
                              : row.tipo === 'merma'
                                ? t('tipos.merma')
                                : row.tipo === 'ajuste'
                                  ? t('tipos.ajuste')
                                  : row.tipo;

                    return <StatBadge label={label} value="" variant={tipoStatVariant(row.tipo)} />;
                },
                className: 'w-32',
            },
            {
                key: 'delta',
                header: t('columns.delta'),
                sortable: true,
                cell: (row) => {
                    const n = typeof row.delta === 'string' ? Number(row.delta) : row.delta;
                    const cls =
                        n > 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : n < 0
                              ? 'text-red-600 dark:text-red-400'
                              : 'text-muted-foreground';

                    return <span className={`tabular-nums text-sm font-semibold ${cls}`}>{formatDelta(row.delta, i18n.language)}</span>;
                },
                className: 'w-28',
            },
            {
                key: 'stock_anterior',
                header: t('columns.anterior'),
                cell: (row) => (
                    <span className="tabular-nums text-sm text-muted-foreground">{formatCantidad(row.stock_anterior, i18n.language)}</span>
                ),
                className: 'w-24',
            },
            {
                key: 'stock_despues',
                header: t('columns.despues'),
                cell: (row) => (
                    <span className="tabular-nums text-sm font-medium">{formatCantidad(row.stock_despues, i18n.language)}</span>
                ),
                className: 'w-24',
            },
            {
                key: 'usuario',
                header: t('columns.usuario'),
                cell: (row) => (
                    <div className="min-w-0 max-w-[11rem]">
                        {row.creado_por ? (
                            <p className="truncate text-sm text-muted-foreground" title={row.creado_por.name}>
                                {row.creado_por.name}
                            </p>
                        ) : (
                            <span className="text-xs text-muted-foreground">—</span>
                        )}
                    </div>
                ),
                className: 'w-[11rem] min-w-0 max-w-[11rem]',
            },
            {
                key: 'notas',
                header: t('columns.notas'),
                cell: (row) => {
                    const texto = row.notas_vista ?? row.notas;

                    return texto ? (
                        <div className="min-w-0 max-w-full">
                            <p
                                className="line-clamp-2 break-words text-xs text-muted-foreground"
                                title={texto}
                            >
                                {texto}
                            </p>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    );
                },
                className: 'min-w-0 align-top',
            },
        ];
    }, [t, i18n.language]);

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        { label: t('stats.total_sede'), value: stats.total, variant: 'info', icon: ListOrdered },
                        { label: t('stats.coincidencias'), value: stats.coincidencias, variant: 'primary', icon: ClipboardList },
                        { label: t('stats.filtros'), value: activeFiltersCount, variant: 'warning', icon: Filter as LucideIcon },
                    ]}
                    action={
                        <div className="flex flex-row flex-wrap items-center justify-end gap-2">
                            {canExport ? (
                                <Button asChild variant="outline" className="h-9 shrink-0 cursor-pointer gap-2 px-3 font-normal">
                                    <a href={exportUrl} download>
                                        <Download className="size-4 shrink-0 opacity-70" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">{t('common:actions.export_xlsx')}</span>
                                    </a>
                                </Button>
                            ) : null}
                            <Can permission="movimientos-stock.create">
                                <Button
                                    type="button"
                                    className="cursor-pointer gap-2"
                                    onClick={() => setModalOpen(true)}
                                    disabled={sinSedes || productoOptions.length === 0}
                                >
                                    <ClipboardList className="size-4" strokeWidth={2.5} />
                                    <span className="hidden sm:inline">{t('actions.new')}</span>
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
                    tableLayoutFixed
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
                                    'flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:gap-3',
                                    sinSedes || sedeOptions.length === 0 ? 'sm:justify-end' : 'sm:justify-between',
                                )}
                            >
                                {!sinSedes && sedeOptions.length > 0 ? (
                                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                                        <div className="min-w-0 w-full sm:w-auto sm:max-w-56">
                                            <Select
                                                value={filters.sede_id && filters.sede_id !== '' ? filters.sede_id : defaultSedeId}
                                                onValueChange={(v) => applyFilter({ sede_id: v })}
                                            >
                                                <SelectTrigger
                                                    id="filtro-sede-mov"
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
                                                value={filters.tipo}
                                                onValueChange={(v) => applyFilter({ tipo: v as MovimientoTipoFiltro })}
                                            >
                                                <SelectTrigger
                                                    id="filtro-tipo-mov"
                                                    className="h-9 w-full min-w-0 cursor-pointer"
                                                    aria-label={t('filter_tipo')}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="todos">{t('filter_tipo_all')}</SelectItem>
                                                    <SelectItem value="entrada">{t('tipos.entrada')}</SelectItem>
                                                    <SelectItem value="salida">{t('tipos.salida')}</SelectItem>
                                                    <SelectItem value="merma">{t('tipos.merma')}</SelectItem>
                                                    <SelectItem value="ajuste">{t('tipos.ajuste')}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                ) : null}
                                <div className="flex shrink-0 justify-start sm:justify-end">
                                    <AtencionDateRangeFilter
                                        desde={filters.creado_desde}
                                        hasta={filters.creado_hasta}
                                        defaultDesde={movimiento_filtro_ui.default_desde}
                                        defaultHasta={movimiento_filtro_ui.default_hasta}
                                        disabled={isLoading}
                                        translationNs="movimientos-inventario"
                                        onApply={(desde, hasta) => applyFilter({ creado_desde: desde, creado_hasta: hasta })}
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
                                tipo: filters.tipo !== DEFAULT_TIPO ? filters.tipo : undefined,
                                creado_desde: filters.creado_desde,
                                creado_hasta: filters.creado_hasta,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={ClipboardList}
                            title={activeFiltersCount > 0 ? t('empty.no_results_title') : t('empty.no_records_title')}
                            description={activeFiltersCount > 0 ? t('empty.no_results_description') : t('empty.no_records_description')}
                        />
                    }
                />
            </div>

            <MovimientoFormModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                sedeOptions={sedeOptions}
                productoOptions={productoOptions}
                defaultSedeId={filters.sede_id && filters.sede_id !== '' ? filters.sede_id : defaultSedeId}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Inventario' }, { title: 'Movimientos', href: '/inventario/movimientos' }]}>
        {page}
    </AppLayout>
);