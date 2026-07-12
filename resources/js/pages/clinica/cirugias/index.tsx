import { Head, usePage } from '@inertiajs/react';
import { Activity, ClipboardList, Filter, Plus, Scissors, UserCircle } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import type { Paginated } from '@/types';
import { AtencionDateRangeFilter } from '../historias-clinicas/components/atencion-date-range-filter';
import { formatAtendidoInAppTimezone } from '../historias-clinicas/format-atendido';
import { CirugiaDeleteDialog } from './components/cirugia-delete-dialog';
import { CirugiaFormModal } from './components/cirugia-form-modal';
import { CirugiaRowActions } from './components/cirugia-row-actions';
import type {
    CirugiaFilters,
    CirugiaFiltroUi,
    CirugiaRow,
    CirugiaStats,
    ConsultaCirugiaOpcion,
    PacienteCirugiaOpcion,
    SedeCirugiaOpcion,
    UsuarioCirugiaOpcion,
} from './types';

type Props = {
    cirugias: Paginated<CirugiaRow>;
    cirugia_abrir_editar: CirugiaRow | null;
    pacientes_opciones: readonly PacienteCirugiaOpcion[];
    usuarios_opciones: readonly UsuarioCirugiaOpcion[];
    sedes_opciones: readonly SedeCirugiaOpcion[];
    consultas_opciones: readonly ConsultaCirugiaOpcion[];
    filters: CirugiaFilters;
    cirugia_filtro_ui: CirugiaFiltroUi;
    stats: CirugiaStats;
};

type CirugiaTableExtra = Pick<CirugiaFilters, 'programada_desde' | 'programada_hasta' | 'estado'>;

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; cirugia: CirugiaRow }
    | { type: 'delete'; cirugia: CirugiaRow };

const DEFAULT_PER_PAGE = 10;

const SELECT_ALL = '__all__';

const ESTADOS_CIRUGIA_FILTRO = [
    'borrador',
    'programada',
    'en_proceso',
    'completada',
    'cancelada',
] as const;

function displayPropietario(p: CirugiaRow['paciente']['propietario']): string {
    if (!p) {
        return '—';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
}

function estadoBadgeVariant(estado: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (estado === 'completada') {
        return 'default';
    }

    if (estado === 'cancelada') {
        return 'secondary';
    }

    if (estado === 'borrador') {
        return 'outline';
    }

    return 'outline';
}

export default function Index({
    cirugias: paginated,
    cirugia_abrir_editar,
    pacientes_opciones,
    usuarios_opciones,
    sedes_opciones,
    consultas_opciones,
    filters,
    cirugia_filtro_ui,
    stats,
}: Props) {
    const { t } = useTranslation(['cirugia', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canCreate = can('cirugias.create');
    const canUpdate = can('cirugias.update');
    const canDelete = can('cirugias.delete');
    const canSeeAudit = can('audit-trail.view');
    const showRowActions = canUpdate || canDelete;

    const estadoOptions = useMemo<readonly FilterChip<string>[]>(
        () => [
            {
                value: SELECT_ALL,
                label: t('filters.estado_all'),
                description: t('common:filters.all_states_description'),
                tone: 'default',
            },
            ...ESTADOS_CIRUGIA_FILTRO.map((st) => ({
                value: st,
                label: t(`estado.${st}`),
            })),
        ],
        [t],
    );

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<CirugiaTableExtra>({
        routeUrl: clinica.cirugias.index().url,
        initialFilters: filters,
        only: [
            'cirugias',
            'pacientes_opciones',
            'usuarios_opciones',
            'sedes_opciones',
            'consultas_opciones',
            'filters',
            'cirugia_filtro_ui',
            'stats',
            'cirugia_abrir_editar',
        ],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.cirugias.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((c: CirugiaRow) => setModal({ type: 'edit', cirugia: c }), []);
    const openDelete = useCallback((c: CirugiaRow) => setModal({ type: 'delete', cirugia: c }), []);

    const openedCirugiaEditarRef = useRef<string | null>(null);
    useEffect(() => {
        if (!cirugia_abrir_editar || !canUpdate) {
            return;
        }

        if (openedCirugiaEditarRef.current === cirugia_abrir_editar.id) {
            return;
        }

        openedCirugiaEditarRef.current = cirugia_abrir_editar.id;
        openEdit(cirugia_abrir_editar);

        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (url.searchParams.has('editar_cirugia')) {
            url.searchParams.delete('editar_cirugia');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    }, [cirugia_abrir_editar, canUpdate, openEdit]);

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

        if (cirugia_filtro_ui.fuera_del_mes_actual) {
            c += 1;
        }

        if (filters.estado !== '') {
            c += 1;
        }

        return c;
    }, [filters.search, filters.sort, filters.per_page, filters.estado, cirugia_filtro_ui.fuera_del_mes_actual]);

    const columns = useMemo<DataTableColumn<CirugiaRow>[]>(() => {
        const base: DataTableColumn<CirugiaRow>[] = [
            {
                key: 'programada_at',
                header: t('columns.programada_at'),
                sortable: true,
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm">
                        {formatAtendidoInAppTimezone(row.programada_at, appLocale, appTz)}
                    </span>
                ),
            },
            {
                key: 'nombre_procedimiento',
                header: t('columns.procedimiento'),
                sortable: true,
                cell: (row) => (
                    <span className="max-w-48 truncate text-sm font-medium">{row.nombre_procedimiento}</span>
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
                        <CirugiaRowActions
                            cirugia={row}
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
                        <Can permission="cirugias.create">
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
                                <FilterChips
                                    ariaLabel={t('filters.estado')}
                                    value={filters.estado === '' ? SELECT_ALL : filters.estado}
                                    onChange={(v) =>
                                        applyFilter({ estado: v === SELECT_ALL ? '' : v })
                                    }
                                    options={estadoOptions}
                                    disabled={isLoading}
                                    triggerClassName="sm:min-w-48"
                                />
                                <AtencionDateRangeFilter
                                    desde={filters.programada_desde}
                                    hasta={filters.programada_hasta}
                                    defaultDesde={cirugia_filtro_ui.default_desde}
                                    defaultHasta={cirugia_filtro_ui.default_hasta}
                                    disabled={isLoading}
                                    translationNs="cirugia"
                                    triggerClassName="h-10"
                                    onApply={(desde, hasta) =>
                                        applyFilter({ programada_desde: desde, programada_hasta: hasta })
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
                                programada_desde: filters.programada_desde,
                                programada_hasta: filters.programada_hasta,
                                estado: filters.estado || undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Scissors}
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

            <CirugiaFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                cirugia={modal.type === 'edit' ? modal.cirugia : null}
                pacientesOpciones={pacientes_opciones}
                sedesOpciones={sedes_opciones}
                consultasOpciones={consultas_opciones}
            />

            <CirugiaDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                cirugia={modal.type === 'delete' ? modal.cirugia : null}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Cirugías', href: clinica.cirugias.index().url },
    ],
};
