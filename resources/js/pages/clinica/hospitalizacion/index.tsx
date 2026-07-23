import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, BedDouble, ClipboardList, Filter, Plus, UserCircle } from 'lucide-react';
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
import type { Paginated } from '@/types';
import { AtencionDateRangeFilter } from '../historias-clinicas/components/atencion-date-range-filter';
import { formatAtendidoInAppTimezone } from '../historias-clinicas/format-atendido';
import { InternamientoDeleteDialog } from './components/internamiento-delete-dialog';
import { InternamientoFormModal } from './components/internamiento-form-modal';
import { InternamientoRowActions } from './components/internamiento-row-actions';
import type {
    ConsultaHospitalizacionOpcion,
    HospitalizacionFilters,
    HospitalizacionFiltroUi,
    HospitalizacionStats,
    InternamientoRow,
    PacienteHospitalizacionOpcion,
    SedeHospitalizacionOpcion,
    UsuarioHospitalizacionOpcion,
} from './types';

type Props = {
    internamientos: Paginated<InternamientoRow>;
    internamiento_abrir_editar: InternamientoRow | null;
    pacientes_opciones: readonly PacienteHospitalizacionOpcion[];
    usuarios_opciones: readonly UsuarioHospitalizacionOpcion[];
    sedes_opciones: readonly SedeHospitalizacionOpcion[];
    consultas_opciones: readonly ConsultaHospitalizacionOpcion[];
    filters: HospitalizacionFilters;
    hospitalizacion_filtro_ui: HospitalizacionFiltroUi;
    stats: HospitalizacionStats;
};

type TableExtra = Pick<HospitalizacionFilters, 'ingreso_desde' | 'ingreso_hasta' | 'estado'>;

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; internamiento: InternamientoRow }
    | { type: 'delete'; internamiento: InternamientoRow };

const DEFAULT_PER_PAGE = 10;
const LIST_URL = '/clinica/hospitalizacion';
const SELECT_ALL = '__all__';

const ESTADOS_FILTRO = ['activo', 'alta', 'cancelado'] as const;

function displayPropietario(p: InternamientoRow['paciente']['propietario']): string {
    if (!p) {
        return '—';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
}

function estadoBadgeVariant(estado: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (estado === 'activo') {
        return 'default';
    }

    if (estado === 'alta') {
        return 'secondary';
    }

    if (estado === 'cancelado') {
        return 'outline';
    }

    return 'outline';
}

export default function Index({
    internamientos: paginated,
    internamiento_abrir_editar,
    pacientes_opciones,
    usuarios_opciones,
    sedes_opciones,
    consultas_opciones,
    filters,
    hospitalizacion_filtro_ui,
    stats,
}: Props) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canCreate = can('hospitalizacion.create');
    const canUpdate = can('hospitalizacion.update');
    const canDelete = can('hospitalizacion.delete');
    const canSeeAudit = can('audit-trail.view');
    const showRowActions = canUpdate || canDelete;

    const estadoOptions = useMemo<readonly FilterChip<string>[]>(
        () => [
            {
                value: SELECT_ALL,
                label: t('filters.estado_all'),
                description: t('common:filters.all_states_description'),
            },
            ...ESTADOS_FILTRO.map((st) => ({
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
    } = useDataTablePage<TableExtra>({
        routeUrl: LIST_URL,
        initialFilters: filters,
        only: [
            'internamientos',
            'pacientes_opciones',
            'usuarios_opciones',
            'sedes_opciones',
            'consultas_opciones',
            'filters',
            'hospitalizacion_filtro_ui',
            'stats',
            'internamiento_abrir_editar',
        ],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.hospitalizacion.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((row: InternamientoRow) => setModal({ type: 'edit', internamiento: row }), []);
    const openDelete = useCallback((row: InternamientoRow) => setModal({ type: 'delete', internamiento: row }), []);

    const openedEditRef = useRef<string | null>(null);
    useEffect(() => {
        if (!internamiento_abrir_editar || !canUpdate) {
            return;
        }

        if (openedEditRef.current === internamiento_abrir_editar.id) {
            return;
        }

        openedEditRef.current = internamiento_abrir_editar.id;
        openEdit(internamiento_abrir_editar);

        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (url.searchParams.has('editar_internamiento')) {
            url.searchParams.delete('editar_internamiento');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    }, [internamiento_abrir_editar, canUpdate, openEdit]);

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

        if (hospitalizacion_filtro_ui.fuera_del_mes_actual) {
            c += 1;
        }

        if (filters.estado !== '') {
            c += 1;
        }

        return c;
    }, [
        filters.search,
        filters.sort,
        filters.per_page,
        filters.estado,
        hospitalizacion_filtro_ui.fuera_del_mes_actual,
    ]);

    const columns = useMemo<DataTableColumn<InternamientoRow>[]>(() => {
        const base: DataTableColumn<InternamientoRow>[] = [
            {
                key: 'ingreso_at',
                header: t('columns.ingreso_at'),
                sortable: true,
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm">
                        {formatAtendidoInAppTimezone(row.ingreso_at, appLocale, appTz)}
                    </span>
                ),
            },
            {
                key: 'motivo_ingreso',
                header: t('columns.motivo'),
                sortable: true,
                cell: (row) => (
                    <Link
                        href={`/clinica/hospitalizacion/${row.id}`}
                        className="max-w-48 truncate text-sm font-medium text-primary hover:underline"
                    >
                        {row.motivo_ingreso}
                    </Link>
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
                key: 'ubicacion',
                header: t('columns.ubicacion'),
                cell: (row) => (
                    <span className="max-w-28 truncate text-sm text-muted-foreground">
                        {row.ubicacion ?? '—'}
                    </span>
                ),
            },
            {
                key: 'alta_at',
                header: t('columns.alta_at'),
                cell: (row) => (
                    <span className="whitespace-nowrap text-xs text-muted-foreground">
                        {row.alta_at
                            ? formatAtendidoInAppTimezone(row.alta_at, appLocale, appTz)
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
                        <InternamientoRowActions
                            internamiento={row}
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
                            label: t('stats.activos'),
                            value: stats.activos,
                            variant: 'primary',
                            icon: BedDouble,
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
                            variant: 'secondary',
                            icon: Activity,
                        },
                    ]}
                    action={
                        <Can permission="hospitalizacion.create">
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
                                    desde={filters.ingreso_desde}
                                    hasta={filters.ingreso_hasta}
                                    defaultDesde={hospitalizacion_filtro_ui.default_desde}
                                    defaultHasta={hospitalizacion_filtro_ui.default_hasta}
                                    disabled={isLoading}
                                    translationNs="hospitalizacion"
                                    triggerClassName="h-10"
                                    onApply={(desde, hasta) =>
                                        applyFilter({ ingreso_desde: desde, ingreso_hasta: hasta })
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
                                ingreso_desde: filters.ingreso_desde,
                                ingreso_hasta: filters.ingreso_hasta,
                                estado: filters.estado || undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={BedDouble}
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

            <InternamientoFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                internamiento={modal.type === 'edit' ? modal.internamiento : null}
                pacientesOpciones={pacientes_opciones}
                sedesOpciones={sedes_opciones}
                consultasOpciones={consultas_opciones}
            />

            <InternamientoDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                internamiento={modal.type === 'delete' ? modal.internamiento : null}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Hospitalización', href: LIST_URL },
    ],
};
