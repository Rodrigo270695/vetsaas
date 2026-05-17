import { Head, usePage } from '@inertiajs/react';
import { Activity, ClipboardList, Filter, Plus, UserCircle } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import type { Paginated } from '@/types';
import { AtencionDateRangeFilter } from '../historias-clinicas/components/atencion-date-range-filter';
import { formatAtendidoInAppTimezone } from '../historias-clinicas/format-atendido';
import { PedidoDeleteDialog } from './components/pedido-delete-dialog';
import { PedidoFormModal } from './components/pedido-form-modal';
import { PedidoRowActions } from './components/pedido-row-actions';
import type {
    ConsultaLaboratorioOpcion,
    PacienteLaboratorioOpcion,
    PedidoLaboratorioFilters,
    PedidoLaboratorioFiltroUi,
    PedidoLaboratorioRow,
    PedidoLaboratorioStats,
    SedeLaboratorioOpcion,
    UsuarioLaboratorioOpcion,
} from './types';

type Props = {
    pedidos: Paginated<PedidoLaboratorioRow>;
    pedido_abrir_editar: PedidoLaboratorioRow | null;
    pacientes_opciones: readonly PacienteLaboratorioOpcion[];
    usuarios_opciones: readonly UsuarioLaboratorioOpcion[];
    sedes_opciones: readonly SedeLaboratorioOpcion[];
    consultas_opciones: readonly ConsultaLaboratorioOpcion[];
    filters: PedidoLaboratorioFilters;
    pedido_filtro_ui: PedidoLaboratorioFiltroUi;
    stats: PedidoLaboratorioStats;
};

type LaboratorioTableExtra = Pick<PedidoLaboratorioFilters, 'pedido_desde' | 'pedido_hasta' | 'estado'>;

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; pedido: PedidoLaboratorioRow }
    | { type: 'delete'; pedido: PedidoLaboratorioRow };

const DEFAULT_PER_PAGE = 10;

const SELECT_ALL = '__all__';

const ESTADOS_PEDIDO_FILTRO = [
    'borrador',
    'solicitado',
    'en_proceso',
    'completado',
    'cancelado',
] as const;

function displayPropietario(p: PedidoLaboratorioRow['paciente']['propietario']): string {
    if (!p) {
        return '—';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
}

function estadoBadgeVariant(estado: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (estado === 'completado') {
        return 'default';
    }

    if (estado === 'cancelado') {
        return 'secondary';
    }

    if (estado === 'borrador') {
        return 'outline';
    }

    return 'outline';
}

export default function Index({
    pedidos: paginated,
    pedido_abrir_editar,
    pacientes_opciones,
    usuarios_opciones,
    sedes_opciones,
    consultas_opciones,
    filters,
    pedido_filtro_ui,
    stats,
}: Props) {
    const { t } = useTranslation(['laboratorio', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canCreate = can('laboratorio.create');
    const canUpdate = can('laboratorio.update');
    const canDelete = can('laboratorio.delete');
    const canSeeAudit = can('audit-trail.view');
    const showRowActions = canUpdate || canDelete;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<LaboratorioTableExtra>({
        routeUrl: clinica.laboratorio.index().url,
        initialFilters: filters,
        only: [
            'pedidos',
            'pacientes_opciones',
            'usuarios_opciones',
            'sedes_opciones',
            'consultas_opciones',
            'filters',
            'pedido_filtro_ui',
            'stats',
            'pedido_abrir_editar',
        ],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.laboratorio.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((p: PedidoLaboratorioRow) => setModal({ type: 'edit', pedido: p }), []);
    const openDelete = useCallback((p: PedidoLaboratorioRow) => setModal({ type: 'delete', pedido: p }), []);

    const openedPedidoEditarRef = useRef<string | null>(null);
    useEffect(() => {
        if (!pedido_abrir_editar || !canUpdate) {
            return;
        }

        if (openedPedidoEditarRef.current === pedido_abrir_editar.id) {
            return;
        }

        openedPedidoEditarRef.current = pedido_abrir_editar.id;
        openEdit(pedido_abrir_editar);

        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (url.searchParams.has('editar_pedido_laboratorio')) {
            url.searchParams.delete('editar_pedido_laboratorio');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    }, [pedido_abrir_editar, canUpdate, openEdit]);

    const activeFiltersCount = useMemo(() => {
        let c = 0;

        if (filters.search) {
            c += 1;
        }

        if (filters.sort) {
            c += 1;
        }

        if (filters.per_page !== DEFAULT_PER_PAGE) {
            c += 1;
        }

        if (pedido_filtro_ui.fuera_del_mes_actual) {
            c += 1;
        }

        if (filters.estado !== '') {
            c += 1;
        }

        return c;
    }, [filters.search, filters.sort, filters.per_page, filters.estado, pedido_filtro_ui.fuera_del_mes_actual]);

    const columns = useMemo<DataTableColumn<PedidoLaboratorioRow>[]>(() => {
        const base: DataTableColumn<PedidoLaboratorioRow>[] = [
            {
                key: 'solicitado_at',
                header: t('columns.solicitado_at'),
                sortable: true,
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm">
                        {formatAtendidoInAppTimezone(row.solicitado_at, appLocale, appTz)}
                    </span>
                ),
            },
            {
                key: 'lineas',
                header: t('columns.examenes'),
                sortable: true,
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm tabular-nums text-muted-foreground">
                        {row.lineas_count}
                    </span>
                ),
            },
            {
                key: 'paciente',
                header: t('columns.paciente'),
                sortable: true,
                cell: (row) => (
                    <div className="flex min-w-0 flex-col gap-0.5">
                        <span className="truncate text-sm font-medium">{row.paciente.nombre}</span>
                        <span className="truncate text-xs text-muted-foreground">
                            {displayPropietario(row.paciente.propietario)}
                        </span>
                    </div>
                ),
            },
            {
                key: 'estado',
                header: t('columns.estado'),
                sortable: true,
                cell: (row) => (
                    <Badge
                        variant={estadoBadgeVariant(row.estado)}
                        className="whitespace-nowrap text-[0.65rem] font-normal"
                    >
                        {t(`estado.${row.estado}`, { defaultValue: row.estado })}
                    </Badge>
                ),
            },
            {
                key: 'laboratorio_destino',
                header: t('columns.destino'),
                cell: (row) => (
                    <span className="max-w-32 truncate text-xs text-muted-foreground">
                        {row.laboratorio_destino?.trim() ? row.laboratorio_destino : '—'}
                    </span>
                ),
            },
            {
                key: 'consulta',
                header: t('columns.consulta'),
                cell: (row) => (
                    <span className="max-w-36 truncate text-xs text-muted-foreground">
                        {row.consulta?.atendido_at
                            ? formatAtendidoInAppTimezone(row.consulta.atendido_at, appLocale, appTz)
                            : '—'}
                    </span>
                ),
            },
            {
                key: 'veterinario',
                header: t('columns.veterinario'),
                cell: (row) => (
                    <span className="text-sm">{row.veterinario?.name ?? '—'}</span>
                ),
            },
            {
                key: 'sede',
                header: t('columns.sede'),
                cell: (row) => (
                    <span className="max-w-40 truncate text-sm text-muted-foreground">
                        {row.sede?.nombre ?? '—'}
                    </span>
                ),
            },
        ];

        if (canSeeAudit) {
            base.push({
                key: 'creado_por',
                header: t('columns.creado_por'),
                cell: (row) => {
                    if (!row.creado_por) {
                        return <span className="text-xs text-muted-foreground">—</span>;
                    }

                    return (
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCircle className="size-4" strokeWidth={2.25} />
                            </span>
                            <div className="flex min-w-0 flex-col leading-tight">
                                <span className="truncate text-xs font-medium text-foreground">
                                    {row.creado_por.name}
                                </span>
                                <span className="text-[0.65rem] text-muted-foreground">
                                    {new Date(row.created_at).toLocaleDateString(undefined, {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </span>
                            </div>
                        </div>
                    );
                },
            });
        }

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                align: 'right',
                cell: (row) => (
                    <div className="flex justify-end">
                        <PedidoRowActions
                            pedido={row}
                            onEdit={openEdit}
                            onDelete={openDelete}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [
        t,
        appLocale,
        appTz,
        canSeeAudit,
        showRowActions,
        canUpdate,
        canDelete,
        openEdit,
        openDelete,
    ]);

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        {
                            label: t('stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: ClipboardList,
                        },
                        {
                            label: t('stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('stats.matches'),
                            value: stats.coincidencias,
                            variant: 'primary',
                            icon: Activity,
                        },
                    ]}
                    action={
                        <Can permission="laboratorio.create">
                            <Button type="button" onClick={openCreate} className="cursor-pointer gap-2">
                                <Plus className="size-4" strokeWidth={2.5} />
                                <span className="hidden sm:inline">{t('actions.new')}</span>
                                <span className="sm:hidden">{t('actions.new_short')}</span>
                            </Button>
                        </Can>
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
                            placeholder={t('search_placeholder')}
                            filtersClassName="sm:flex-1 sm:justify-end"
                        >
                            <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                                <Select
                                    value={filters.estado === '' ? SELECT_ALL : filters.estado}
                                    onValueChange={(v) =>
                                        applyFilter({ estado: v === SELECT_ALL ? '' : v })
                                    }
                                    disabled={isLoading}
                                >
                                    <SelectTrigger
                                        className="h-10 w-full min-w-0 cursor-pointer sm:w-44"
                                        aria-label={t('filters.estado')}
                                    >
                                        <SelectValue placeholder={t('filters.estado_all')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={SELECT_ALL}>{t('filters.estado_all')}</SelectItem>
                                        {ESTADOS_PEDIDO_FILTRO.map((st) => (
                                            <SelectItem key={st} value={st}>
                                                {t(`estado.${st}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <AtencionDateRangeFilter
                                    desde={filters.pedido_desde}
                                    hasta={filters.pedido_hasta}
                                    defaultDesde={pedido_filtro_ui.default_desde}
                                    defaultHasta={pedido_filtro_ui.default_hasta}
                                    disabled={isLoading}
                                    translationNs="laboratorio"
                                    triggerClassName="h-10"
                                    onApply={(desde, hasta) =>
                                        applyFilter({ pedido_desde: desde, pedido_hasta: hasta })
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
                                pedido_desde: filters.pedido_desde,
                                pedido_hasta: filters.pedido_hasta,
                                estado: filters.estado || undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={ClipboardList}
                            title={
                                activeFiltersCount > 0
                                    ? t('empty.no_results_title')
                                    : t('empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('empty.no_results_description')
                                    : t('empty.no_records_description')
                            }
                            action={
                                activeFiltersCount === 0 && canCreate ? (
                                    <Button type="button" onClick={openCreate} className="cursor-pointer gap-2">
                                        <Plus className="size-4" strokeWidth={2.5} />
                                        {t('actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <PedidoFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                pedido={modal.type === 'edit' ? modal.pedido : null}
                pacientesOpciones={pacientes_opciones}
                usuariosOpciones={usuarios_opciones}
                sedesOpciones={sedes_opciones}
                consultasOpciones={consultas_opciones}
            />

            <PedidoDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                pedido={modal.type === 'delete' ? modal.pedido : null}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Laboratorio', href: clinica.laboratorio.index().url },
    ],
};
