import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CircleDot,
    Eye,
    FileText,
    Lock,
    Plus,
    ScreenShare,
    SlidersHorizontal,
    Store,
    UserCircle,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
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
import caja from '@/routes/caja';
import { arqueoPdf } from '@/routes/caja/sesiones';
import type { QueryParams } from '@/wayfinder';
import { normalizeTicketAncho } from '@/lib/ticket-ancho';
import { ArqueoPrintDialog } from './components/arqueo-print-dialog';
import { SesionAbrirModal } from './components/sesion-abrir-modal';
import { SesionArqueoDetalleModal } from './components/sesion-arqueo-detalle-modal';
import { SesionCerrarModal } from './components/sesion-cerrar-modal';
import type { CajaSesionEstadoFiltro, CajaSesionFilters, CajaSesionRow, CajaSesionesIndexProps } from './types';

type TableExtraFilters = {
    estado: CajaSesionEstadoFiltro;
    sede_id: string;
};

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: CajaSesionEstadoFiltro = 'todas';

function formatMonto(amount: string | null, moneda: string, locale: string): string {
    if (amount === null || amount === '') {
        return '—';
    }

    const n = Number(amount);

    if (Number.isNaN(n)) {
        return amount;
    }

    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return new Intl.NumberFormat(locale, { style: 'currency', currency: cur }).format(n);
}

function filtersToListQuery(filters: CajaSesionFilters): QueryParams {
    const q: QueryParams = {};

    if (filters.search) {
        q.search = filters.search;
    }

    if (filters.per_page !== DEFAULT_PER_PAGE) {
        q.per_page = filters.per_page;
    }

    if (filters.sort) {
        q.sort = filters.sort;
    }

    if (filters.direction) {
        q.direction = filters.direction;
    }

    if (filters.estado !== DEFAULT_ESTADO) {
        q.estado = filters.estado;
    }

    if (filters.sede_id) {
        q.sede_id = filters.sede_id;
    }

    return q;
}

export default function Index({
    sesiones: paginated,
    filters,
    stats,
    sedes_opciones: sedesOpciones,
    mi_sesion_abierta: miSesionAbierta,
    sin_sedes: sinSedes,
    ticket_ancho_mm: ticketAnchoMm,
}: CajaSesionesIndexProps) {
    const { t, i18n } = useTranslation(['caja', 'common']);
    const { props } = usePage();
    const authUserId = (props.auth?.user as { id?: string } | undefined)?.id;
    const { can } = usePermission();
    const canOpen = can('caja-sesiones.open');
    const canClose = can('caja-sesiones.close');
    const canView = can('caja-sesiones.view');
    const bloqueadoAbrirPorMiSesion = Boolean(miSesionAbierta);
    const configTicketAncho = normalizeTicketAncho(ticketAnchoMm);

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } = useDataTablePage<TableExtraFilters>({
        routeUrl: caja.sesiones.index.url(),
        initialFilters: filters,
        only: ['sesiones', 'filters', 'stats', 'sedes_opciones', 'mi_sesion_abierta', 'sin_sedes', 'ticket_ancho_mm'],
        errorMessage: t('caja:sesiones.toast_load_error'),
        storageKey: 'vetsaas.caja.sesiones.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const listQuery = useMemo(() => filtersToListQuery(filters), [filters]);

    const [abrirOpen, setAbrirOpen] = useState(false);
    const [cerrarSesion, setCerrarSesion] = useState<CajaSesionRow | null>(null);
    const [detalleSesion, setDetalleSesion] = useState<CajaSesionRow | null>(null);
    const [imprimirSesionId, setImprimirSesionId] = useState<string | null>(null);
    const closeCerrar = useCallback(() => setCerrarSesion(null), []);

    const imprimirPdfUrl = useMemo(
        () => (imprimirSesionId ? arqueoPdf.url({ caja_sesion: imprimirSesionId }) : ''),
        [imprimirSesionId],
    );

    const estadoOptions: readonly FilterChip<CajaSesionEstadoFiltro>[] = useMemo(
        () => [
            { value: 'todas', label: t('caja:sesiones.estado.todas') },
            { value: 'abierta', label: t('caja:sesiones.estado.abierta') },
            { value: 'cerrada', label: t('caja:sesiones.estado.cerrada') },
        ],
        [t],
    );

    const sedeFilterOptions: readonly FilterChip<string>[] = useMemo(
        () => [
            { value: 'all', label: t('caja:sesiones.filter_sede_todas') },
            ...sedesOpciones.map((s) => ({
                value: s.id,
                label: `${s.nombre} · ${s.codigo}`,
                icon: <Store className="size-3.5" strokeWidth={2.25} />,
            })),
        ],
        [sedesOpciones, t],
    );

    const activeFiltersCount = useMemo(() => {
        let count = 0;

        if (filters.search) {
            count += 1;
        }

        if (filters.sort) {
            count += 1;
        }

        if (filters.estado !== DEFAULT_ESTADO) {
            count += 1;
        }

        if (filters.sede_id) {
            count += 1;
        }

        if (filters.per_page !== DEFAULT_PER_PAGE) {
            count += 1;
        }

        return count;
    }, [filters.search, filters.sort, filters.estado, filters.sede_id, filters.per_page]);

    const sedeCodigoById = useMemo(
        () => Object.fromEntries(sedesOpciones.map((s) => [s.id, s.codigo])),
        [sedesOpciones],
    );

    const columns = useMemo<DataTableColumn<CajaSesionRow>[]>(() => {
        const base: DataTableColumn<CajaSesionRow>[] = [
            {
                key: 'sede',
                header: t('caja:sesiones.columns.sede'),
                cell: (row) => {
                    const codigo = sedeCodigoById[row.sede_id];

                    return (
                        <div className="flex flex-col">
                            <span className="font-medium text-foreground">{row.sede_nombre ?? '—'}</span>
                            {codigo ? (
                                <span className="font-mono text-[0.65rem] text-muted-foreground">{codigo}</span>
                            ) : null}
                        </div>
                    );
                },
            },
            {
                key: 'estado',
                header: t('caja:sesiones.columns.estado'),
                sortable: true,
                cell: (row) =>
                    row.estado === 'abierta' ? (
                        <StatBadge label={t('caja:sesiones.estado.abierta')} value="" variant="success" />
                    ) : (
                        <StatBadge label={t('caja:sesiones.estado.cerrada')} value="" variant="muted" />
                    ),
            },
            {
                key: 'moneda',
                header: t('caja:sesiones.columns.moneda'),
                cell: (row) => <span className="font-mono text-sm">{row.moneda}</span>,
                className: 'w-24',
            },
            {
                key: 'opened_at',
                header: t('caja:sesiones.columns.apertura'),
                sortable: true,
                cell: (row) => (
                    <span className="text-sm text-muted-foreground">
                        {new Date(row.opened_at).toLocaleString(i18n.language, {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                        })}
                    </span>
                ),
            },
            {
                key: 'closed_at',
                header: t('caja:sesiones.columns.cierre'),
                sortable: true,
                cell: (row) =>
                    row.closed_at ? (
                        <span className="text-sm text-muted-foreground">
                            {new Date(row.closed_at).toLocaleString(i18n.language, {
                                day: '2-digit',
                                month: 'short',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'saldo_apertura',
                header: t('caja:sesiones.columns.saldo_apertura'),
                sortable: true,
                cell: (row) => (
                    <span className="tabular-nums text-sm font-medium">{formatMonto(row.saldo_apertura, row.moneda, i18n.language)}</span>
                ),
                className: 'w-36',
            },
            {
                key: 'saldo_cierre_efectivo',
                header: t('caja:sesiones.columns.saldo_cierre'),
                cell: (row) => (
                    <span className="tabular-nums text-sm">{formatMonto(row.saldo_cierre_efectivo, row.moneda, i18n.language)}</span>
                ),
                className: 'w-36',
            },
            {
                key: 'abierta_por',
                header: t('caja:sesiones.columns.cajero'),
                cell: (row) =>
                    row.abierta_por ? (
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCircle className="size-4" strokeWidth={2.25} />
                            </span>
                            <span className="text-xs font-medium text-foreground">{row.abierta_por.name}</span>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
            },
        ];

        if (canClose || canView) {
            base.push({
                key: 'acciones',
                header: <span className="md:sr-only">{t('caja:sesiones.columns.acciones')}</span>,
                align: 'right',
                cell: (row) => {
                    if (row.estado === 'cerrada' && canView) {
                        return (
                            <div className="flex justify-end gap-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-7 min-h-7 cursor-pointer gap-1 rounded-md px-2 text-xs font-medium"
                                    onClick={() => setDetalleSesion(row)}
                                >
                                    <Eye className="size-3 shrink-0 opacity-90" strokeWidth={2.5} aria-hidden />
                                    <span className="hidden sm:inline">{t('caja:sesiones.actions.arqueo_ver')}</span>
                                    <span className="sm:hidden">{t('caja:sesiones.actions.arqueo_ver_short')}</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-7 min-h-7 cursor-pointer gap-1 rounded-md px-2 text-xs font-medium"
                                    onClick={() => setImprimirSesionId(row.id)}
                                >
                                    <FileText className="size-3 shrink-0 opacity-90" strokeWidth={2.5} aria-hidden />
                                    <span className="hidden sm:inline">{t('caja:sesiones.actions.arqueo_pdf')}</span>
                                    <span className="sm:hidden">{t('caja:sesiones.actions.arqueo_pdf_short')}</span>
                                </Button>
                            </div>
                        );
                    }

                    if (row.estado !== 'abierta') {
                        return <span className="text-xs text-muted-foreground"> </span>;
                    }

                    const soyQuienAbrio =
                        authUserId !== undefined && String(row.opened_by_id) === String(authUserId);

                    if (soyQuienAbrio && canClose) {
                        return (
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="h-7 min-h-7 cursor-pointer gap-1 rounded-md px-2 text-xs font-medium shadow-sm ring-1 ring-border/60 hover:ring-border"
                                    onClick={() => setCerrarSesion(row)}
                                >
                                    <Lock className="size-3 shrink-0 opacity-90" strokeWidth={2.5} aria-hidden />
                                    <span className="hidden sm:inline">{t('caja:sesiones.actions.cerrar')}</span>
                                    <span className="sm:hidden">{t('caja:sesiones.actions.cerrar_short')}</span>
                                </Button>
                            </div>
                        );
                    }

                    if (row.estado === 'abierta' && canClose) {
                        return (
                            <div className="flex justify-end">
                                <span className="max-w-[10rem] text-right text-xs leading-snug text-muted-foreground sm:max-w-none">
                                    {t('caja:sesiones.row.cerrar_solo_si_abriste')}
                                </span>
                            </div>
                        );
                    }

                    return <span className="text-xs text-muted-foreground"> </span>;
                },
                className: 'w-44 sm:w-56',
            });
        }

        return base;
    }, [t, i18n.language, canClose, canView, authUserId, sedeCodigoById]);

    return (
        <>
            <Head title={t('caja:sesiones.title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('caja:sesiones.title')}
                    description={t('caja:sesiones.description')}
                    stats={[
                        { label: t('caja:sesiones.stats.total'), value: stats.total, variant: 'info', icon: Wallet },
                        { label: t('caja:sesiones.stats.abiertas'), value: stats.abiertas, variant: 'success', icon: CircleDot },
                        { label: t('caja:sesiones.stats.cerradas'), value: stats.cerradas, variant: 'muted', icon: Lock as LucideIcon },
                        { label: t('caja:sesiones.stats.filters'), value: activeFiltersCount, variant: 'warning', icon: SlidersHorizontal },
                        { label: t('caja:sesiones.stats.matches'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                    ]}
                    action={
                        <Can permission="caja-sesiones.open">
                            <Button
                                type="button"
                                onClick={() => setAbrirOpen(true)}
                                disabled={sinSedes || bloqueadoAbrirPorMiSesion}
                                title={bloqueadoAbrirPorMiSesion ? t('caja:sesiones.abrir_bloqueado_tooltip') : undefined}
                                className="cursor-pointer gap-2"
                            >
                                <Plus className="size-4" strokeWidth={2.5} />
                                <span className="hidden sm:inline">{t('caja:sesiones.actions.abrir')}</span>
                                <span className="sm:hidden">{t('caja:sesiones.actions.abrir_short')}</span>
                            </Button>
                        </Can>
                    }
                />

                {sinSedes ? (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>{t('caja:sesiones.sin_sedes.title')}</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>{t('caja:sesiones.sin_sedes.description')}</span>
                            <Can permission="sedes.view">
                                <Button type="button" variant="secondary" size="sm" className="shrink-0 cursor-pointer" asChild>
                                    <Link href="/configuracion/sedes">{t('caja:sesiones.sin_sedes.link')}</Link>
                                </Button>
                            </Can>
                        </AlertDescription>
                    </Alert>
                ) : null}

                {miSesionAbierta && !sinSedes ? (
                    <Alert>
                        <Wallet className="size-4" />
                        <AlertTitle>{t('caja:sesiones.mi_sesion.title')}</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>{t('caja:sesiones.mi_sesion.description', { sede: miSesionAbierta.sede_nombre ?? '—' })}</span>
                            {canClose ? (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="shrink-0 cursor-pointer"
                                    onClick={() => setCerrarSesion(miSesionAbierta)}
                                >
                                    {t('caja:sesiones.mi_sesion.cerrar_cta')}
                                </Button>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}

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
                            placeholder={t('caja:sesiones.search_placeholder')}
                            filtersClassName="sm:flex-1 sm:min-w-0"
                        >
                            <div className="flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-end sm:gap-3">
                                <FilterChips
                                    ariaLabel={t('caja:sesiones.filter_estado_label')}
                                    value={filters.estado}
                                    onChange={(estado) => applyFilter({ estado })}
                                    options={estadoOptions}
                                />
                                {!sinSedes && sedesOpciones.length > 0 ? (
                                    <FilterChips
                                        ariaLabel={t('caja:sesiones.filter_sede_label')}
                                        value={filters.sede_id ? filters.sede_id : 'all'}
                                        onChange={(v) => applyFilter({ sede_id: v === 'all' ? '' : v })}
                                        options={sedeFilterOptions}
                                        className="sm:min-w-56"
                                    />
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
                                estado: filters.estado !== DEFAULT_ESTADO ? filters.estado : undefined,
                                sede_id: filters.sede_id || undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Wallet}
                            title={activeFiltersCount > 0 ? t('caja:sesiones.empty.no_results_title') : t('caja:sesiones.empty.no_records_title')}
                            description={
                                activeFiltersCount > 0
                                    ? t('caja:sesiones.empty.no_results_description')
                                    : t('caja:sesiones.empty.no_records_description')
                            }
                            action={
                                activeFiltersCount === 0 && canOpen && !sinSedes && !bloqueadoAbrirPorMiSesion ? (
                                    <Button type="button" onClick={() => setAbrirOpen(true)} className="cursor-pointer gap-2">
                                        <Plus className="size-4" strokeWidth={2.5} />
                                        {t('caja:sesiones.actions.abrir')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <SesionAbrirModal
                open={abrirOpen}
                onOpenChange={setAbrirOpen}
                sedes={sedesOpciones}
                listQuery={listQuery}
                preferredSedeId={filters.sede_id || undefined}
            />

            <SesionCerrarModal
                open={cerrarSesion !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeCerrar();
                    }
                }}
                sesion={cerrarSesion}
                listQuery={listQuery}
            />

            <SesionArqueoDetalleModal
                open={detalleSesion !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetalleSesion(null);
                    }
                }}
                sesion={detalleSesion}
            />

            <ArqueoPrintDialog
                open={imprimirSesionId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setImprimirSesionId(null);
                    }
                }}
                pdfBaseUrl={imprimirPdfUrl}
                configAncho={configTicketAncho}
            />
        </>
    );
}

Index.layout = (page: ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Caja' }, { title: 'Sesiones', href: caja.sesiones.index.url() }]}>{page}</AppLayout>
);
