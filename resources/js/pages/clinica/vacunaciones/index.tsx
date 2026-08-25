import { Head, usePage } from '@inertiajs/react';
import { Activity, Filter, Plus, Syringe, Upload, UserCircle } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import {
    BulkImportModal,
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
} from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { CobroEstadoBadge } from '@/components/cobro-estado-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import type { Paginated } from '@/types';
import { AtencionDateRangeFilter } from '../historias-clinicas/components/atencion-date-range-filter';
import {
    formatAtendidoInAppTimezone,
    formatDateOnlyLabel,
} from '../historias-clinicas/format-atendido';
import { VacunaDeleteDialog } from './components/vacuna-delete-dialog';
import { VacunaFormModal } from './components/vacuna-form-modal';
import { VacunaRowActions } from './components/vacuna-row-actions';
import type {
    AplicacionFiltroUi,
    PacienteVacunaOpcion,
    SedeVacunaOpcion,
    ServicioVacunaOpcion,
    UsuarioVacunaOpcion,
    VacunaAplicadaFilters,
    VacunaAplicadaRow,
    VacunaAplicadaStats,
    VacunaPrefillCreate,
} from './types';

type Props = {
    vacunas: Paginated<VacunaAplicadaRow>;
    pacientes_opciones: readonly PacienteVacunaOpcion[];
    usuarios_opciones: readonly UsuarioVacunaOpcion[];
    sedes_opciones: readonly SedeVacunaOpcion[];
    servicios_vacuna_opciones?: readonly ServicioVacunaOpcion[];
    filters: VacunaAplicadaFilters;
    aplicacion_filtro_ui: AplicacionFiltroUi;
    stats: VacunaAplicadaStats;
    vacuna_prefill: VacunaPrefillCreate | null;
    vacuna_abrir_editar: VacunaAplicadaRow | null;
};

type VacunasTableExtra = Pick<
    VacunaAplicadaFilters,
    'aplicada_desde' | 'aplicada_hasta' | 'sin_rango' | 'cobro'
>;

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; vacuna: VacunaAplicadaRow }
    | { type: 'delete'; vacuna: VacunaAplicadaRow }
    | { type: 'bulk' };

const DEFAULT_PER_PAGE = 10;

function displayPropietario(p: VacunaAplicadaRow['paciente']['propietario']): string {
    if (!p) {
        return '—';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
}

export default function Index({
    vacunas: paginated,
    pacientes_opciones,
    usuarios_opciones,
    sedes_opciones,
    servicios_vacuna_opciones = [],
    filters,
    aplicacion_filtro_ui,
    stats,
    vacuna_prefill,
    vacuna_abrir_editar,
}: Props) {
    const { t, i18n } = useTranslation(['vacunaciones', 'common', 'consulta-cargos']);
    const { timezone: appTz } = usePage().props;
    const uiLocale = i18n.language || 'es';
    const { can } = usePermission();
    const canCreate = can('vacunaciones.create');
    const canUpdate = can('vacunaciones.update');
    const canDelete = can('vacunaciones.delete');
    const canCobrar =
        can('consulta-cargos.cobrar') && can('ventas.create');
    const canSeeAudit = can('audit-trail.view');
    const showRowActions = canUpdate || canDelete || canCobrar;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<VacunasTableExtra>({
        routeUrl: clinica.vacunaciones.index().url,
        initialFilters: filters,
        only: [
            'vacunas',
            'pacientes_opciones',
            'usuarios_opciones',
            'sedes_opciones',
            'servicios_vacuna_opciones',
            'filters',
            'aplicacion_filtro_ui',
            'stats',
            'vacuna_prefill',
            'vacuna_abrir_editar',
        ],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.vacunaciones.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((v: VacunaAplicadaRow) => setModal({ type: 'edit', vacuna: v }), []);
    const openDelete = useCallback((v: VacunaAplicadaRow) => setModal({ type: 'delete', vacuna: v }), []);

    const prefillAbrirModal = useRef(false);
    useEffect(() => {
        if (vacuna_abrir_editar) {
            return;
        }
        if (!vacuna_prefill || !canCreate || prefillAbrirModal.current) {
            return;
        }
        prefillAbrirModal.current = true;
        openCreate();
    }, [vacuna_prefill, vacuna_abrir_editar, canCreate, openCreate]);

    const openedVacunaEditarRef = useRef<string | null>(null);
    useEffect(() => {
        if (!vacuna_abrir_editar || !canUpdate) {
            return;
        }

        if (openedVacunaEditarRef.current === vacuna_abrir_editar.id) {
            return;
        }

        openedVacunaEditarRef.current = vacuna_abrir_editar.id;
        openEdit(vacuna_abrir_editar);

        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (url.searchParams.has('editar_vacuna_aplicada')) {
            url.searchParams.delete('editar_vacuna_aplicada');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    }, [vacuna_abrir_editar, canUpdate, openEdit]);

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

        if (aplicacion_filtro_ui.fuera_del_mes_actual) {
            c += 1;
        }

        if (filters.cobro && filters.cobro !== 'todos') {
            c += 1;
        }

        return c;
    }, [
        filters.search,
        filters.sort,
        filters.per_page,
        filters.cobro,
        aplicacion_filtro_ui.fuera_del_mes_actual,
    ]);

    const cobroFiltro = filters.cobro ?? 'todos';
    const cobroOptions = useMemo(
        () =>
            [
                { value: 'todos' as const, label: t('consulta-cargos:filtro_cobro.todos') },
                {
                    value: 'por_cobrar' as const,
                    label: t('consulta-cargos:filtro_cobro.por_cobrar'),
                },
                { value: 'cobrado' as const, label: t('consulta-cargos:filtro_cobro.cobrado') },
                {
                    value: 'sin_precuenta' as const,
                    label: t('consulta-cargos:filtro_cobro.sin_precuenta'),
                },
            ] as const,
        [t],
    );

    const columns = useMemo<DataTableColumn<VacunaAplicadaRow>[]>(() => {
        const base: DataTableColumn<VacunaAplicadaRow>[] = [
            {
                key: 'aplicada_at',
                header: t('columns.aplicada_at'),
                sortable: true,
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm">
                        {formatAtendidoInAppTimezone(row.aplicada_at, uiLocale, appTz)}
                    </span>
                ),
            },
            {
                key: 'categoria_registro',
                header: t('columns.tipo'),
                cell: (row) => {
                    const cat = row.categoria_registro ?? 'vacuna';

                    return (
                        <div className="flex flex-col items-start gap-1">
                            <Badge
                                variant="secondary"
                                className="whitespace-nowrap text-[0.65rem] font-normal"
                            >
                                {cat === 'desparasitacion'
                                    ? t('row.categoria_desparasitacion')
                                    : cat === 'otro'
                                      ? t('row.categoria_otro')
                                      : t('row.categoria_vacuna')}
                            </Badge>
                            <CobroEstadoBadge estado={row.estado_cobro} />
                        </div>
                    );
                },
            },
            {
                key: 'paciente',
                header: t('columns.paciente'),
                sortable: true,
                cell: (row) => (
                    <div className="flex min-w-0 flex-col gap-0.5">
                        <span className="truncate font-medium text-foreground">{row.paciente.nombre}</span>
                        <span className="truncate text-xs text-muted-foreground">
                            {displayPropietario(row.paciente.propietario)}
                        </span>
                    </div>
                ),
            },
            {
                key: 'consulta',
                header: t('columns.consulta'),
                cell: (row) => {
                    const c = row.consulta;
                    if (!c?.atendido_at) {
                        return <span className="text-sm text-muted-foreground">—</span>;
                    }

                    return (
                        <span className="whitespace-nowrap text-sm text-muted-foreground">
                            {formatAtendidoInAppTimezone(c.atendido_at, uiLocale, appTz)}
                        </span>
                    );
                },
            },
            {
                key: 'nombre_vacuna',
                header: t('columns.vacuna'),
                sortable: true,
                cell: (row) => (
                    <div className="flex min-w-0 flex-col gap-1">
                        <span className="font-medium text-foreground">{row.nombre_vacuna}</span>
                        {row.esquema_antigenos?.trim() ? (
                            <span className="line-clamp-2 text-xs text-muted-foreground">
                                {row.esquema_antigenos.trim()}
                            </span>
                        ) : null}
                        {row.producto ? (
                            <div className="flex flex-wrap items-center gap-1.5">
                                <Badge variant="outline" className="text-[0.65rem] font-normal">
                                    {t('row.inv')}
                                </Badge>
                                <span className="truncate text-xs text-muted-foreground">
                                    {row.producto.nombre}
                                    {row.producto.sku ? ` · ${row.producto.sku}` : ''}
                                </span>
                            </div>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'fecha_proxima_sugerida',
                header: t('columns.proxima'),
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm text-muted-foreground">
                        {row.fecha_proxima_sugerida
                            ? formatDateOnlyLabel(
                                  row.fecha_proxima_sugerida,
                                  uiLocale,
                              )
                            : '—'}
                    </span>
                ),
            },
            {
                key: 'numero_dosis',
                header: t('columns.dosis'),
                cell: (row) => (
                    <span className="tabular-nums text-sm">
                        {row.numero_dosis != null ? row.numero_dosis : '—'}
                    </span>
                ),
            },
            {
                key: 'lote',
                header: t('columns.lote'),
                cell: (row) => (
                    <span className="max-w-32 truncate text-sm text-muted-foreground">
                        {row.lote?.trim() ? row.lote : '—'}
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
                        return (
                            <span className="text-xs text-muted-foreground">{t('row.system')}</span>
                        );
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
                                    {formatDateOnlyLabel(row.created_at, uiLocale)}
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
                        <VacunaRowActions
                            vacuna={row}
                            onEdit={openEdit}
                            onDelete={openDelete}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                            canCobrar={canCobrar}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [
        t,
        uiLocale,
        appTz,
        canSeeAudit,
        showRowActions,
        canUpdate,
        canDelete,
        canCobrar,
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
                            icon: Syringe,
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
                        <div className="flex flex-row items-center gap-2">
                            <Can permission="vacunaciones.create">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setModal({ type: 'bulk' })}
                                    className="cursor-pointer gap-2"
                                >
                                    <Upload className="size-4" strokeWidth={2.5} />
                                    <span className="hidden sm:inline">{t('actions.bulk_import')}</span>
                                    <span className="sm:hidden">{t('actions.bulk_import_short')}</span>
                                </Button>
                            </Can>
                            <Can permission="vacunaciones.create">
                                <Button type="button" onClick={openCreate} className="cursor-pointer gap-2">
                                    <Plus className="size-4" strokeWidth={2.5} />
                                    <span className="hidden sm:inline">{t('actions.new')}</span>
                                    <span className="sm:hidden">{t('actions.new_short')}</span>
                                </Button>
                            </Can>
                        </div>
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
                            <FilterChips
                                ariaLabel={t('consulta-cargos:filtro_cobro.aria')}
                                value={cobroFiltro}
                                onChange={(cobro) => applyFilter({ cobro })}
                                options={[...cobroOptions]}
                            />
                            <AtencionDateRangeFilter
                                desde={filters.aplicada_desde}
                                hasta={filters.aplicada_hasta}
                                defaultDesde={aplicacion_filtro_ui.default_desde}
                                defaultHasta={aplicacion_filtro_ui.default_hasta}
                                disabled={isLoading}
                                translationNs="vacunaciones"
                                triggerClassName="h-10"
                                onApply={(desde, hasta) =>
                                    applyFilter({
                                        aplicada_desde: desde,
                                        aplicada_hasta: hasta,
                                        sin_rango: false,
                                    })
                                }
                                onClear={() =>
                                    applyFilter({
                                        aplicada_desde: null,
                                        aplicada_hasta: null,
                                        sin_rango: true,
                                    })
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
                                aplicada_desde: filters.aplicada_desde || undefined,
                                aplicada_hasta: filters.aplicada_hasta || undefined,
                                sin_rango: filters.sin_rango ? true : undefined,
                                cobro:
                                    filters.cobro && filters.cobro !== 'todos'
                                        ? filters.cobro
                                        : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Syringe}
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

            <VacunaFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                vacuna={modal.type === 'edit' ? modal.vacuna : null}
                pacientesOpciones={pacientes_opciones}
                sedesOpciones={sedes_opciones}
                serviciosVacunaOpciones={servicios_vacuna_opciones}
                prefillCreate={modal.type === 'create' ? vacuna_prefill : null}
            />

            <VacunaDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                vacuna={modal.type === 'delete' ? modal.vacuna : null}
            />

            <BulkImportModal
                open={modal.type === 'bulk'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                translationNs="vacunaciones"
                templateUrl="/clinica/vacunaciones/plantilla-importacion"
                importUrl="/clinica/vacunaciones/importar"
                reloadOnly={['vacunas', 'filters', 'stats', 'aplicacion_filtro_ui']}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Vacunaciones', href: clinica.vacunaciones.index().url },
    ],
};
