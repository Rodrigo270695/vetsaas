import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    FileText,
    Mail,
    MessageCircle,
    Pencil,
    Plus,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import type { Paginated } from '@/types';
import {
    CotizacionCorreoDialog,
    CotizacionDeleteDialog,
    CotizacionWhatsAppDialog,
} from './components/cotizacion-envio-dialogs';
import type {
    CotizacionEstadoFilter,
    CotizacionFilters,
    CotizacionRow,
    CotizacionStats,
} from './types';
import { formatDate, formatMoney } from './types';

type Props = {
    cotizaciones: Paginated<CotizacionRow>;
    filters: CotizacionFilters;
    stats: CotizacionStats;
    cotizacion_creada: CotizacionRow | null;
};

type ModalState =
    | { type: 'idle' }
    | { type: 'whatsapp'; row: CotizacionRow }
    | { type: 'correo'; row: CotizacionRow }
    | { type: 'delete'; row: CotizacionRow };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: CotizacionEstadoFilter = 'todas';

function abrirPdf(row: CotizacionRow) {
    window.open(`/caja/cotizaciones/${row.id}/pdf`, '_blank', 'noopener');
}

function EstadoBadge({ row }: { row: CotizacionRow }) {
    if (row.estado === 'aceptada') {
        return <StatBadge label="Aceptada" value="" variant="success" />;
    }
    if (row.estado === 'rechazada') {
        return <StatBadge label="Rechazada" value="" variant="muted" />;
    }
    if (row.vencida) {
        return <StatBadge label="Vencida" value="" variant="warning" />;
    }

    return <StatBadge label="Vigente" value="" variant="info" />;
}

export default function Index({ cotizaciones: paginated, filters, stats, cotizacion_creada: creada }: Props) {
    const { can } = usePermission();
    const canCreate = can(['cotizaciones.create', 'ventas.create']);
    const canUpdate = can(['cotizaciones.update', 'ventas.create']);
    const canDelete = can(['cotizaciones.delete', 'ventas.delete']);

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<{ estado: CotizacionEstadoFilter }>({
            routeUrl: '/caja/cotizaciones',
            initialFilters: filters,
            only: ['cotizaciones', 'filters', 'stats'],
            errorMessage: 'No se pudieron cargar las cotizaciones.',
            storageKey: 'vetsaas.caja.cotizaciones.prefs',
            defaults: { per_page: DEFAULT_PER_PAGE, sort: null, direction: null },
        });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const [creadaOpen, setCreadaOpen] = useState(creada !== null);

    const cambiarEstado = useCallback((row: CotizacionRow, estado: 'emitida' | 'aceptada' | 'rechazada') => {
        router.post(`/caja/cotizaciones/${row.id}/estado`, { estado }, { preserveScroll: true });
    }, []);

    const estadoOptions: readonly FilterChip<CotizacionEstadoFilter>[] = useMemo(
        () => [
            { value: 'todas', label: 'Todas' },
            { value: 'emitida', label: 'Vigentes' },
            { value: 'vencida', label: 'Vencidas' },
            { value: 'aceptada', label: 'Aceptadas' },
            { value: 'rechazada', label: 'Rechazadas' },
        ],
        [],
    );

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;

        return count;
    }, [filters]);

    const columns = useMemo<DataTableColumn<CotizacionRow>[]>(
        () => [
            {
                key: 'numero',
                header: 'N°',
                sortable: true,
                cell: (r) => (
                    <div className="flex flex-col">
                        <span className="font-mono text-xs font-semibold">{r.numero}</span>
                        <span className="text-[0.7rem] text-muted-foreground">{formatDate(r.fecha)}</span>
                    </div>
                ),
            },
            {
                key: 'cliente_nombre',
                header: 'Cliente',
                sortable: true,
                cell: (r) => (
                    <div className="flex min-w-0 flex-col">
                        <span className="truncate font-medium">{r.cliente_nombre}</span>
                        <span className="text-[0.7rem] text-muted-foreground">
                            {[r.cliente_documento, r.cliente_telefono].filter(Boolean).join(' · ') || '—'}
                        </span>
                    </div>
                ),
            },
            {
                key: 'lineas_count',
                header: 'Ítems',
                cell: (r) => <span className="tabular-nums text-sm">{r.lineas_count}</span>,
                className: 'w-16',
            },
            {
                key: 'total',
                header: 'Total',
                sortable: true,
                align: 'right',
                cell: (r) => <span className="font-medium tabular-nums">{formatMoney(r.total, r.moneda)}</span>,
            },
            {
                key: 'vigencia_hasta',
                header: 'Válida hasta',
                sortable: true,
                cell: (r) => <span className="text-sm tabular-nums">{formatDate(r.vigencia_hasta)}</span>,
            },
            {
                key: 'estado',
                header: 'Estado',
                cell: (r) => (
                    <div className="flex flex-col items-start gap-1">
                        <EstadoBadge row={r} />
                        <span className="flex gap-1 text-muted-foreground">
                            {r.enviada_whatsapp_at ? (
                                <MessageCircle className="size-3.5 text-emerald-600" aria-label="Enviada por WhatsApp" />
                            ) : null}
                            {r.enviada_email_at ? <Mail className="size-3.5 text-primary" aria-label="Enviada por correo" /> : null}
                        </span>
                    </div>
                ),
            },
            {
                key: 'acciones',
                header: <span className="md:sr-only">Acciones</span>,
                align: 'right',
                className: 'w-12',
                cell: (r) => (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon" className="size-8" aria-label="Acciones">
                                    <span className="text-lg leading-none">⋯</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                                <DropdownMenuItem onClick={() => abrirPdf(r)}>
                                    <FileText className="size-4" />
                                    Ver PDF
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => setModal({ type: 'whatsapp', row: r })}>
                                    <MessageCircle className="size-4" />
                                    Enviar por WhatsApp
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => setModal({ type: 'correo', row: r })}>
                                    <Mail className="size-4" />
                                    Enviar por correo
                                </DropdownMenuItem>
                                {canUpdate ? (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link href={`/caja/cotizaciones/${r.id}/editar`}>
                                                <Pencil className="size-4" />
                                                Editar
                                            </Link>
                                        </DropdownMenuItem>
                                        {r.estado !== 'aceptada' ? (
                                            <DropdownMenuItem onClick={() => cambiarEstado(r, 'aceptada')}>
                                                <CheckCircle2 className="size-4" />
                                                Marcar aceptada
                                            </DropdownMenuItem>
                                        ) : null}
                                        {r.estado !== 'rechazada' ? (
                                            <DropdownMenuItem onClick={() => cambiarEstado(r, 'rechazada')}>
                                                <XCircle className="size-4" />
                                                Marcar rechazada
                                            </DropdownMenuItem>
                                        ) : null}
                                        {r.estado !== 'emitida' ? (
                                            <DropdownMenuItem onClick={() => cambiarEstado(r, 'emitida')}>
                                                <FileText className="size-4" />
                                                Volver a vigente
                                            </DropdownMenuItem>
                                        ) : null}
                                    </>
                                ) : null}
                                {canDelete ? (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            className="text-destructive focus:text-destructive"
                                            onClick={() => setModal({ type: 'delete', row: r })}
                                        >
                                            <Trash2 className="size-4" />
                                            Eliminar
                                        </DropdownMenuItem>
                                    </>
                                ) : null}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                ),
            },
        ],
        [canUpdate, canDelete, cambiarEstado],
    );

    return (
        <>
            <Head title="Cotizaciones" />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title="Cotizaciones"
                    description="Presupuestos de productos y servicios en PDF con el logo y los datos de tu sede, listos para enviar por WhatsApp o correo."
                    stats={[
                        { label: 'Total', value: stats.total, variant: 'info', icon: FileText },
                        { label: 'Vigentes', value: stats.vigentes, variant: 'primary', icon: FileText },
                        { label: 'Aceptadas', value: stats.aceptadas, variant: 'success', icon: CheckCircle2 },
                        { label: 'Coincidencias', value: stats.coincidencias, variant: 'default', icon: FileText },
                    ]}
                    action={
                        <Can permission={['cotizaciones.create', 'ventas.create']}>
                            <Button asChild className="cursor-pointer gap-2">
                                <Link href="/caja/cotizaciones/nueva">
                                    <Plus className="size-4" aria-hidden />
                                    <span className="hidden sm:inline">Nueva cotización</span>
                                    <span className="sm:hidden">Nueva</span>
                                </Link>
                            </Button>
                        </Can>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(r) => r.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    ariaLiveMessage={`${stats.coincidencias} cotizaciones`}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder="Buscar por número, cliente, DNI o teléfono"
                        >
                            <FilterChips
                                ariaLabel="Filtrar por estado"
                                value={filters.estado}
                                onChange={(estado) => applyFilter({ estado })}
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
                            title={activeFiltersCount > 0 ? 'Sin resultados' : 'Aún no hay cotizaciones'}
                            description={
                                activeFiltersCount > 0
                                    ? 'Prueba con otra búsqueda o cambia el filtro.'
                                    : 'Crea una cotización con productos y servicios y envíala al cliente en PDF.'
                            }
                            action={
                                activeFiltersCount === 0 && canCreate ? (
                                    <Button asChild className="cursor-pointer gap-2">
                                        <Link href="/caja/cotizaciones/nueva">
                                            <Plus className="size-4" strokeWidth={2.5} />
                                            Crear la primera
                                        </Link>
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <Dialog open={creadaOpen && creada !== null} onOpenChange={setCreadaOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <div className="flex size-11 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
                            <CheckCircle2 className="size-5" />
                        </div>
                        <DialogTitle className="pt-2">Cotización {creada?.numero} lista</DialogTitle>
                        <DialogDescription>
                            {creada ? `${creada.cliente_nombre} · ${formatMoney(creada.total, creada.moneda)}` : null}. ¿Cómo
                            quieres enviarla?
                        </DialogDescription>
                    </DialogHeader>
                    {creada ? (
                        <div className="grid gap-2 sm:grid-cols-3">
                            <Button type="button" variant="outline" className="gap-2" onClick={() => abrirPdf(creada)}>
                                <FileText className="size-4" />
                                Ver PDF
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="gap-2"
                                onClick={() => {
                                    setCreadaOpen(false);
                                    setModal({ type: 'whatsapp', row: creada });
                                }}
                            >
                                <MessageCircle className="size-4 text-emerald-600" />
                                WhatsApp
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="gap-2"
                                onClick={() => {
                                    setCreadaOpen(false);
                                    setModal({ type: 'correo', row: creada });
                                }}
                            >
                                <Mail className="size-4" />
                                Correo
                            </Button>
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>

            <CotizacionWhatsAppDialog
                open={modal.type === 'whatsapp'}
                onOpenChange={(open) => !open && closeModal()}
                cotizacion={modal.type === 'whatsapp' ? modal.row : null}
            />
            <CotizacionCorreoDialog
                open={modal.type === 'correo'}
                onOpenChange={(open) => !open && closeModal()}
                cotizacion={modal.type === 'correo' ? modal.row : null}
            />
            <CotizacionDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => !open && closeModal()}
                cotizacion={modal.type === 'delete' ? modal.row : null}
            />
        </>
    );
}
