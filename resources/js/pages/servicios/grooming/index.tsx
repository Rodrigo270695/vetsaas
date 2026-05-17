import { Head, usePage } from '@inertiajs/react';
import { Activity, CalendarDays, Filter, Plus, UserCircle } from 'lucide-react';
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
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import type { Paginated } from '@/types';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import { formatAtendidoInAppTimezone } from '@/pages/clinica/historias-clinicas/format-atendido';
import { GroomingDeleteDialog } from './components/grooming-delete-dialog';
import { GroomingFormModal } from './components/grooming-form-modal';
import { GroomingRowActions } from './components/grooming-row-actions';
import type {
    GroomingFilters,
    GroomingFiltroUi,
    GroomingServicioGrupo,
    GroomingStats,
    GroomingTurnoRow,
    PacienteGroomingOpcion,
    SedeGroomingOpcion,
    UsuarioGroomingOpcion,
} from './types';

const LIST_URL = '/servicios/grooming';

type Props = {
    turnos: Paginated<GroomingTurnoRow>;
    grooming_servicio_grupos: readonly GroomingServicioGrupo[];
    grooming_servicio_duraciones: Record<string, number>;
    pacientes_opciones: readonly PacienteGroomingOpcion[];
    usuarios_opciones: readonly UsuarioGroomingOpcion[];
    sedes_opciones: readonly SedeGroomingOpcion[];
    filters: GroomingFilters;
    grooming_filtro_ui: GroomingFiltroUi;
    stats: GroomingStats;
    turno_abrir_editar: GroomingTurnoRow | null;
};

type GroomingTableExtra = Pick<GroomingFilters, 'grooming_desde' | 'grooming_hasta'>;

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; turno: GroomingTurnoRow }
    | { type: 'delete'; turno: GroomingTurnoRow };

const DEFAULT_PER_PAGE = 10;

function displayPropietario(p: GroomingTurnoRow['paciente']['propietario']): string {
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

    if (estado === 'no_asistio') {
        return 'destructive';
    }

    if (estado === 'confirmada' || estado === 'en_proceso') {
        return 'default';
    }

    return 'outline';
}

export default function Index({
    turnos: paginated,
    grooming_servicio_grupos,
    grooming_servicio_duraciones,
    pacientes_opciones,
    usuarios_opciones,
    sedes_opciones,
    filters,
    grooming_filtro_ui,
    stats,
    turno_abrir_editar,
}: Props) {
    const { t } = useTranslation(['grooming', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canCreate = can('grooming.create');
    const canUpdate = can('grooming.update');
    const canDelete = can('grooming.delete');
    const canSeeAudit = can('audit-trail.view');
    const canCobrarGrooming = can('ventas.create') && can('grooming.view');
    const showRowActions = canUpdate || canDelete || canCobrarGrooming;

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<GroomingTableExtra>({
            routeUrl: LIST_URL,
            initialFilters: filters,
            only: [
                'turnos',
                'grooming_servicio_grupos',
                'grooming_servicio_duraciones',
                'pacientes_opciones',
                'usuarios_opciones',
                'sedes_opciones',
                'filters',
                'grooming_filtro_ui',
                'stats',
                'turno_abrir_editar',
            ],
            errorMessage: t('toast.load_error'),
            storageKey: 'vetsaas.grooming.prefs',
            defaults: {
                per_page: DEFAULT_PER_PAGE,
                sort: null,
                direction: null,
            },
        });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((row: GroomingTurnoRow) => setModal({ type: 'edit', turno: row }), []);
    const openDelete = useCallback((row: GroomingTurnoRow) => setModal({ type: 'delete', turno: row }), []);

    const openedTurnoEditarRef = useRef<string | null>(null);
    useEffect(() => {
        if (!turno_abrir_editar || !canUpdate) {
            return;
        }

        if (openedTurnoEditarRef.current === turno_abrir_editar.id) {
            return;
        }

        openedTurnoEditarRef.current = turno_abrir_editar.id;
        openEdit(turno_abrir_editar);

        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (url.searchParams.has('editar_grooming_turno')) {
            url.searchParams.delete('editar_grooming_turno');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    }, [turno_abrir_editar, canUpdate, openEdit]);

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

        if (grooming_filtro_ui.fuera_del_mes_actual) {
            c += 1;
        }

        return c;
    }, [filters.search, filters.sort, filters.per_page, grooming_filtro_ui.fuera_del_mes_actual]);

    const columns = useMemo<DataTableColumn<GroomingTurnoRow>[]>(() => {
        const base: DataTableColumn<GroomingTurnoRow>[] = [
            {
                key: 'inicio_at',
                header: t('columns.inicio_at'),
                sortable: true,
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm">
                        {formatAtendidoInAppTimezone(row.inicio_at, appLocale, appTz)}
                    </span>
                ),
            },
            {
                key: 'duracion',
                header: t('columns.duracion'),
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm text-muted-foreground">
                        {row.duracion_minutos} min
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
                key: 'servicio',
                header: t('columns.servicio'),
                cell: (row) => {
                    const label = t(`tipos_servicio.items.${row.servicio}.label`, { defaultValue: row.servicio });
                    const showDetalle =
                        row.servicio === 'otro_personalizado' &&
                        row.servicio_detalle != null &&
                        row.servicio_detalle.trim() !== '';

                    return (
                        <div className="flex min-w-0 max-w-52 flex-col gap-0.5">
                            <span className="truncate text-sm text-muted-foreground">{label}</span>
                            {showDetalle ? (
                                <span className="truncate text-xs text-foreground/80">{row.servicio_detalle}</span>
                            ) : null}
                        </div>
                    );
                },
            },
            {
                key: 'responsable',
                header: t('columns.responsable'),
                cell: (row) => (
                    <span className="text-sm">{row.responsable?.name ?? '—'}</span>
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
                        <GroomingRowActions
                            turno={row}
                            onEdit={openEdit}
                            onDelete={openDelete}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                            canCobrar={canCobrarGrooming}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [t, appLocale, appTz, canSeeAudit, showRowActions, canUpdate, canDelete, canCobrarGrooming, openEdit, openDelete]);

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
                            icon: CalendarDays,
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
                        <Can permission="grooming.create">
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
                            <AtencionDateRangeFilter
                                desde={filters.grooming_desde}
                                hasta={filters.grooming_hasta}
                                defaultDesde={grooming_filtro_ui.default_desde}
                                defaultHasta={grooming_filtro_ui.default_hasta}
                                disabled={isLoading}
                                translationNs="grooming"
                                triggerClassName="h-10"
                                onApply={(desde, hasta) =>
                                    applyFilter({ grooming_desde: desde, grooming_hasta: hasta })
                                }
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
                                grooming_desde: filters.grooming_desde,
                                grooming_hasta: filters.grooming_hasta,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={CalendarDays}
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

            <GroomingFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                turno={modal.type === 'edit' ? modal.turno : null}
                servicioGrupos={grooming_servicio_grupos}
                servicioDuraciones={grooming_servicio_duraciones}
                pacientesOpciones={pacientes_opciones}
                usuariosOpciones={usuarios_opciones}
                sedesOpciones={sedes_opciones}
            />

            <GroomingDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                turno={modal.type === 'delete' ? modal.turno : null}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Servicios', href: '#' },
        { title: 'Grooming', href: LIST_URL },
    ],
};
