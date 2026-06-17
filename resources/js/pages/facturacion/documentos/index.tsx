import { Head, Link } from '@inertiajs/react';
import { Eye, FileText } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo } from 'react';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import caja from '@/routes/caja';
import type { Paginated } from '@/types';
import { DocumentoDownloadMenu, type DocumentoDownloadRow } from './components/documento-download-menu';

type DocumentoEstadoFiltro = 'todos' | 'emitido' | 'anulado' | 'rechazado' | 'pendiente';

type DocumentoRow = DocumentoDownloadRow & {
    tipo_comprobante: number;
    tipo_label: string;
    estado: string;
    receptor_nombre: string;
    receptor_num_doc: string;
    total: string;
    moneda: string;
    emitido_at: string | null;
    venta_numero: string | null;
    venta_estado: string | null;
    sede: string;
};

type Props = {
    documentos: Paginated<DocumentoRow>;
    filters: {
        search: string;
        per_page: number;
        sort: string | null;
        direction: string | null;
        estado: DocumentoEstadoFiltro;
    };
    stats: {
        total: number;
        emitidos: number;
        coincidencias: number;
    };
};

type TableExtraFilters = {
    estado: DocumentoEstadoFiltro;
};

const ROUTE_URL = '/facturacion/documentos';
const DEFAULT_PER_PAGE = 15;
const DEFAULT_ESTADO: DocumentoEstadoFiltro = 'todos';

const ESTADO_BADGE: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    emitido: 'default',
    anulado: 'secondary',
    rechazado: 'destructive',
    pendiente: 'outline',
};

const ESTADO_LABEL: Record<string, string> = {
    emitido: 'Emitido',
    anulado: 'Anulado',
    rechazado: 'Rechazado',
    pendiente: 'Pendiente',
};

function formatMonto(amount: string, moneda: string, locale: string): string {
    const n = Number(amount);

    if (Number.isNaN(n)) {
        return amount;
    }

    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return new Intl.NumberFormat(locale, { style: 'currency', currency: cur }).format(n);
}

export default function Index({ documentos: paginated, filters, stats }: Props) {
    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<TableExtraFilters>({
            routeUrl: ROUTE_URL,
            initialFilters: filters,
            only: ['documentos', 'filters', 'stats'],
            errorMessage: 'No se pudo cargar el historial de comprobantes.',
            storageKey: 'vetsaas.facturacion.documentos.prefs',
            defaults: {
                per_page: DEFAULT_PER_PAGE,
                sort: null,
                direction: null,
            },
        });

    const estado = (filters.estado ?? DEFAULT_ESTADO) as DocumentoEstadoFiltro;

    const activeFiltersCount = useMemo(() => {
        let n = 0;

        if (filters.search) {
            n += 1;
        }

        if (estado !== DEFAULT_ESTADO) {
            n += 1;
        }

        return n;
    }, [estado, filters.search]);

    const estadoOptions: readonly FilterChip<DocumentoEstadoFiltro>[] = useMemo(
        () => [
            { value: 'todos', label: 'Todos' },
            { value: 'emitido', label: 'Emitidos' },
            { value: 'anulado', label: 'Anulados' },
            { value: 'rechazado', label: 'Rechazados' },
            { value: 'pendiente', label: 'Pendientes' },
        ],
        [],
    );

    const columns: DataTableColumn<DocumentoRow>[] = useMemo(
        () => [
            {
                key: 'numero',
                header: 'Número',
                cell: (row) => (
                    <div className="flex flex-col gap-0.5">
                        <span className="font-mono text-sm tabular-nums font-medium text-primary">
                            {row.numero_completo}
                        </span>
                        {row.venta_numero && row.venta_numero !== row.numero_completo ? (
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
                key: 'tipo',
                header: 'Tipo',
                cell: (row) => (
                    <Badge variant="outline" className="font-normal">
                        {row.tipo_label}
                    </Badge>
                ),
            },
            {
                key: 'fecha',
                header: 'Emisión',
                cell: (row) =>
                    row.emitido_at
                        ? new Intl.DateTimeFormat('es-PE', {
                              dateStyle: 'short',
                              timeStyle: 'short',
                          }).format(new Date(row.emitido_at))
                        : '—',
                sortable: true,
                sortKey: 'emitido_at',
            },
            {
                key: 'cliente',
                header: 'Cliente',
                cell: (row) => (
                    <div className="flex flex-col gap-0.5">
                        <span className="font-medium">{row.receptor_nombre}</span>
                        {row.receptor_num_doc ? (
                            <span className="font-mono text-xs text-muted-foreground">
                                {row.receptor_num_doc}
                            </span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'sede',
                header: 'Sede',
                cell: (row) => row.sede,
            },
            {
                key: 'total',
                header: 'Total',
                cell: (row) => formatMonto(row.total, row.moneda, 'es-PE'),
                sortable: true,
                sortKey: 'total',
            },
            {
                key: 'estado',
                header: 'Estado',
                cell: (row) => (
                    <Badge variant={ESTADO_BADGE[row.estado] ?? 'outline'}>
                        {ESTADO_LABEL[row.estado] ?? row.estado}
                    </Badge>
                ),
                sortable: true,
                sortKey: 'estado',
            },
            {
                key: 'acciones',
                header: 'Acciones',
                cell: (row) => (
                    <div className="flex flex-wrap items-center gap-1.5">
                        <DocumentoDownloadMenu documento={row} />
                        <Button variant="ghost" size="icon" className="size-8" asChild>
                            <Link
                                href={caja.ventas.show.url(row.venta_id)}
                                aria-label={`Ver venta ${row.venta_numero ?? row.numero_completo}`}
                            >
                                <Eye className="size-4" aria-hidden />
                            </Link>
                        </Button>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Comprobantes emitidos" />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title="Comprobantes emitidos"
                    description="Historial de boletas y facturas electrónicas enviadas a SUNAT vía APISUNAT."
                    stats={[
                        { label: 'Total registrados', value: stats.total, variant: 'muted' },
                        { label: 'Emitidos', value: stats.emitidos, variant: 'primary' },
                        {
                            label: 'Coincidencias',
                            value: stats.coincidencias,
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
                    ariaLiveMessage={`${stats.coincidencias} comprobantes`}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder="Buscar por número CPE, cliente o venta…"
                            filtersClassName="items-end"
                        >
                            <FilterChips
                                ariaLabel="Filtrar por estado"
                                value={estado}
                                onChange={(v) => applyFilter({ estado: v })}
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
                            icon={FileText}
                            title={
                                activeFiltersCount > 0
                                    ? 'Sin resultados'
                                    : 'Aún no hay comprobantes'
                            }
                            description={
                                activeFiltersCount > 0
                                    ? 'Prueba con otros filtros o términos de búsqueda.'
                                    : 'Los comprobantes emitidos desde caja aparecerán aquí.'
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
            { title: 'Facturación' },
            { title: 'Comprobantes emitidos', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
