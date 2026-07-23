import { Head, Link } from '@inertiajs/react';
import { Eye, FileMinus2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo } from 'react';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    PageHeader,
} from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import caja from '@/routes/caja';
import type { Paginated } from '@/types';

export type AnulacionRow = {
    id: string;
    numero_completo: string;
    tipo_label: string;
    receptor_nombre: string;
    receptor_num_doc: string;
    total: string;
    moneda: string;
    emitido_at: string | null;
    anulado_at: string | null;
    motivo_anulacion: string | null;
    anulado_por: string | null;
    venta_id: string | null;
    venta_numero: string | null;
    sede: string;
};

export type FelAnulacionHistorialProps = {
    page_title: string;
    route_url: string;
    empty_title: string;
    empty_description: string;
    hint: string;
    documentos: Paginated<AnulacionRow>;
    filters: {
        search: string;
        per_page: number;
        sort: string | null;
        direction: string | null;
        fecha_desde: string;
        fecha_hasta: string;
    };
    filtro_ui: {
        default_desde: string;
        default_hasta: string;
        fuera_del_rango_default: boolean;
    };
    stats: {
        total_anulados: number;
        coincidencias: number;
    };
};

type TableExtraFilters = {
    fecha_desde: string;
    fecha_hasta: string;
};

function formatMonto(amount: string, moneda: string): string {
    const n = Number(amount);

    if (Number.isNaN(n)) {
        return amount;
    }

    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: cur }).format(n);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function FelAnulacionHistorialPage({
    page_title,
    route_url,
    empty_title,
    empty_description,
    hint,
    documentos: paginated,
    filters,
    filtro_ui,
    stats,
}: FelAnulacionHistorialProps) {
    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<TableExtraFilters>({
            routeUrl: route_url,
            initialFilters: filters,
            only: ['documentos', 'filters', 'stats', 'filtro_ui'],
            errorMessage: 'No se pudo cargar el historial.',
            storageKey: `vetsaas.${route_url.replace(/\//g, '.')}.prefs`,
            defaults: {
                per_page: 15,
                sort: null,
                direction: null,
            },
        });

    const activeFiltersCount = useMemo(() => {
        let n = 0;
        if (filters.search) {
            n += 1;
        }
        if (filtro_ui.fuera_del_rango_default) {
            n += 1;
        }
        return n;
    }, [filtro_ui.fuera_del_rango_default, filters.search]);

    const columns: DataTableColumn<AnulacionRow>[] = useMemo(
        () => [
            {
                key: 'numero',
                header: 'Comprobante',
                cell: (row) => (
                    <div className="flex flex-col gap-0.5">
                        <span className="font-mono text-sm font-medium tabular-nums text-primary">
                            {row.numero_completo}
                        </span>
                        <span className="text-[11px] text-muted-foreground">{row.tipo_label}</span>
                        {row.venta_numero ? (
                            <span className="font-mono text-[11px] text-muted-foreground">
                                Venta {row.venta_numero}
                            </span>
                        ) : null}
                    </div>
                ),
                sortable: true,
                sortKey: 'numero_completo',
            },
            {
                key: 'receptor',
                header: 'Receptor',
                cell: (row) => (
                    <div className="flex max-w-[220px] flex-col gap-0.5">
                        <span className="truncate text-sm">{row.receptor_nombre || '—'}</span>
                        <span className="font-mono text-[11px] text-muted-foreground">
                            {row.receptor_num_doc || '—'}
                        </span>
                    </div>
                ),
            },
            {
                key: 'emitido',
                header: 'Emitido',
                cell: (row) => formatDate(row.emitido_at),
                sortable: true,
                sortKey: 'emitido_at',
            },
            {
                key: 'anulado',
                header: 'Anulado',
                cell: (row) => (
                    <div className="flex flex-col gap-0.5">
                        <span>{formatDate(row.anulado_at)}</span>
                        {row.anulado_por ? (
                            <span className="text-[11px] text-muted-foreground">{row.anulado_por}</span>
                        ) : null}
                    </div>
                ),
                sortable: true,
                sortKey: 'anulado_at',
            },
            {
                key: 'motivo',
                header: 'Motivo',
                cell: (row) => (
                    <span className="line-clamp-2 max-w-[240px] text-sm text-muted-foreground">
                        {row.motivo_anulacion?.trim() || '—'}
                    </span>
                ),
            },
            {
                key: 'total',
                header: 'Total',
                cell: (row) => (
                    <span className="font-mono text-sm tabular-nums">
                        {formatMonto(row.total, row.moneda)}
                    </span>
                ),
                sortable: true,
                sortKey: 'total',
                align: 'right',
            },
            {
                key: 'sede',
                header: 'Sede',
                cell: (row) => row.sede,
            },
            {
                key: 'estado',
                header: 'Estado',
                cell: () => (
                    <Badge variant="secondary" className="font-normal">
                        Anulado
                    </Badge>
                ),
            },
            {
                key: 'acciones',
                header: '',
                cell: (row) =>
                    row.venta_id ? (
                        <Button variant="ghost" size="icon" className="size-8" asChild>
                            <Link
                                href={caja.ventas.show.url(row.venta_id)}
                                title="Ver venta"
                                aria-label={`Ver venta ${row.venta_numero ?? row.numero_completo}`}
                            >
                                <Eye className="size-4" />
                            </Link>
                        </Button>
                    ) : null,
                align: 'right',
            },
        ],
        [],
    );

    return (
        <>
            <Head title={page_title} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={page_title}
                    description={hint}
                    stats={[
                        {
                            label: 'En filtro',
                            value: stats.coincidencias,
                            variant: 'muted',
                        },
                        {
                            label: 'Anulados (total)',
                            value: stats.total_anulados,
                            variant: 'default',
                        },
                    ]}
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(row) => row.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    ariaLiveMessage={`${stats.coincidencias} comprobantes anulados`}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder="Buscar por CPE, receptor o venta…"
                        >
                            <div className="flex w-full min-w-0 flex-wrap items-center gap-2">
                                <AtencionDateRangeFilter
                                    desde={filters.fecha_desde}
                                    hasta={filters.fecha_hasta}
                                    defaultDesde={filtro_ui.default_desde}
                                    defaultHasta={filtro_ui.default_hasta}
                                    disabled={isLoading}
                                    translationNs="facturacion-documentos"
                                    triggerClassName="h-10 min-w-[12rem]"
                                    onApply={(desde, hasta) =>
                                        applyFilter({ fecha_desde: desde, fecha_hasta: hasta })
                                    }
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
                                fecha_desde: filters.fecha_desde,
                                fecha_hasta: filters.fecha_hasta,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={FileMinus2}
                            title={activeFiltersCount > 0 ? 'Sin resultados' : empty_title}
                            description={
                                activeFiltersCount > 0
                                    ? 'Prueba con otro rango o término de búsqueda.'
                                    : empty_description
                            }
                        />
                    }
                />
            </div>
        </>
    );
}

export function felAnulacionLayout(breadcrumb: string, routeUrl: string) {
    return (page: ReactNode) => (
        <AppLayout
            breadcrumbs={[
                { title: 'Facturación' },
                { title: breadcrumb, href: routeUrl },
            ]}
        >
            {page}
        </AppLayout>
    );
}
