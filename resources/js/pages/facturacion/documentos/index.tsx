import { Head, Link } from '@inertiajs/react';
import { Eye, FileText, MessageCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { Can } from '@/components/can';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import caja from '@/routes/caja';
import type { Paginated } from '@/types';
import { DocumentoDownloadMenu, type DocumentoDownloadRow } from './components/documento-download-menu';
import {
    DocumentoWhatsAppModal,
    type DocumentoWhatsAppRow,
} from './components/documento-whatsapp-modal';

type DocumentoEstadoFiltro = 'todos' | 'emitido' | 'anulado' | 'rechazado' | 'pendiente';

type DocumentoRow = DocumentoDownloadRow & {
    tipo_comprobante: number;
    tipo_label: string;
    estado: string;
    apisunat_mode: 'sandbox' | 'produccion' | null;
    receptor_nombre: string;
    receptor_num_doc: string;
    cliente_telefono: string | null;
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
        fecha_desde: string;
        fecha_hasta: string;
    };
    documento_filtro_ui: {
        default_desde: string;
        default_hasta: string;
        fuera_del_mes_actual: boolean;
    };
    stats: {
        total: number;
        emitidos: number;
        coincidencias: number;
    };
};

type TableExtraFilters = {
    estado: DocumentoEstadoFiltro;
    fecha_desde: string;
    fecha_hasta: string;
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

function modoBadge(mode: DocumentoRow['apisunat_mode']): {
    label: string;
    variant: 'default' | 'secondary' | 'outline';
} {
    if (mode === 'produccion') {
        return { label: 'Producción', variant: 'default' };
    }

    if (mode === 'sandbox') {
        return { label: 'Prueba', variant: 'secondary' };
    }

    return { label: 'Sin dato', variant: 'outline' };
}

function formatMonto(amount: string, moneda: string, locale: string): string {
    const n = Number(amount);

    if (Number.isNaN(n)) {
        return amount;
    }

    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return new Intl.NumberFormat(locale, { style: 'currency', currency: cur }).format(n);
}

export default function Index({ documentos: paginated, filters, documento_filtro_ui, stats }: Props) {
    const { t } = useTranslation('facturacion-documentos');
    const [whatsappDocumento, setWhatsappDocumento] = useState<DocumentoWhatsAppRow | null>(null);

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

        if (documento_filtro_ui.fuera_del_mes_actual) {
            n += 1;
        }

        return n;
    }, [documento_filtro_ui.fuera_del_mes_actual, estado, filters.search]);

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
                key: 'modo',
                header: 'Modo',
                cell: (row) => {
                    const badge = modoBadge(row.apisunat_mode);

                    return (
                        <Badge variant={badge.variant} className="font-normal">
                            {badge.label}
                        </Badge>
                    );
                },
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
                align: 'right',
                cell: (row) => (
                    <div className="flex items-center justify-end gap-0.5">
                        {row.estado === 'emitido' ? (
                            <Can permission="documentos.send">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 shrink-0 border-0 bg-transparent text-emerald-600 shadow-none hover:bg-emerald-500/10 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                                    aria-label={t('whatsapp.enviar', { numero: row.numero_completo })}
                                    onClick={() => setWhatsappDocumento(row)}
                                >
                                    <MessageCircle className="size-4" strokeWidth={2.25} aria-hidden />
                                </Button>
                            </Can>
                        ) : null}
                        <DocumentoDownloadMenu documento={row} />
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 shrink-0 border-0 bg-transparent text-violet-600 shadow-none hover:bg-violet-500/10 hover:text-violet-700 dark:text-violet-400 dark:hover:text-violet-300"
                            asChild
                        >
                            <Link
                                href={caja.ventas.show.url(row.venta_id)}
                                aria-label={`Ver venta ${row.venta_numero ?? row.numero_completo}`}
                            >
                                <Eye className="size-4" strokeWidth={2.25} aria-hidden />
                            </Link>
                        </Button>
                    </div>
                ),
            },
        ],
        [t],
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
                            filtersClassName="sm:flex-1 sm:justify-end"
                        >
                            <div className="flex w-full flex-col gap-2 lg:flex-row lg:items-center lg:justify-end">
                                <FilterChips
                                    ariaLabel="Filtrar por estado"
                                    value={estado}
                                    onChange={(v) => applyFilter({ estado: v })}
                                    options={estadoOptions}
                                />
                                <AtencionDateRangeFilter
                                    desde={filters.fecha_desde}
                                    hasta={filters.fecha_hasta}
                                    defaultDesde={documento_filtro_ui.default_desde}
                                    defaultHasta={documento_filtro_ui.default_hasta}
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
                                estado: filters.estado !== DEFAULT_ESTADO ? filters.estado : undefined,
                                fecha_desde: filters.fecha_desde,
                                fecha_hasta: filters.fecha_hasta,
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

            <DocumentoWhatsAppModal
                open={whatsappDocumento !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setWhatsappDocumento(null);
                    }
                }}
                documento={whatsappDocumento}
            />
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
