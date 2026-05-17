import { Head, usePage } from '@inertiajs/react';
import { FileText, Filter, Plus, Stethoscope } from 'lucide-react';
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
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import type { Paginated } from '@/types';
import { AtencionDateRangeFilter } from './components/atencion-date-range-filter';
import { ConsultaDeleteDialog } from './components/consulta-delete-dialog';
import { ConsultaFormModal } from './components/consulta-form-modal';
import { ConsultaRowActions } from './components/consulta-row-actions';
import { formatAtendidoInAppTimezone } from './format-atendido';
import type {
    AtencionFiltroUi,
    ConsultaHistoriaFilters,
    ConsultaHistoriaRow,
    ConsultaHistoriaStats,
    PacienteHistoriaOpcion,
} from './types';

type Props = {
    consultas: Paginated<ConsultaHistoriaRow>;
    /** Si viene de `?editar_consulta=`, abre el modal de edición una vez. */
    consulta_abrir_editar: ConsultaHistoriaRow | null;
    /** Si viene de `?nuevo_para_paciente=`, abre el modal de alta con paciente preseleccionado. */
    paciente_prefill_nueva_consulta: {
        id: string;
        nombre: string;
        propietario: {
            id: string;
            nombres: string;
            apellidos: string | null;
            razon_social: string | null;
        };
    } | null;
    pacientes_opciones: readonly PacienteHistoriaOpcion[];
    filters: ConsultaHistoriaFilters;
    atencion_filtro_ui: AtencionFiltroUi;
    stats: ConsultaHistoriaStats;
};

type HistoriasTableExtra = Pick<ConsultaHistoriaFilters, 'atendido_desde' | 'atendido_hasta'>;

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; consulta: ConsultaHistoriaRow }
    | { type: 'delete'; consulta: ConsultaHistoriaRow };

const DEFAULT_PER_PAGE = 10;

function displayPropietario(
    p: ConsultaHistoriaRow['historia_clinica']['paciente']['propietario'],
): string {
    if (!p) {
        return '—';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ') || '—';
}

function truncate(s: string | null, max: number): string {
    if (!s) {
        return '—';
    }

    const t = s.trim();

    if (t.length <= max) {
        return t;
    }

    return `${t.slice(0, max - 1)}…`;
}

export default function Index({
    consultas: paginated,
    consulta_abrir_editar,
    paciente_prefill_nueva_consulta,
    pacientes_opciones,
    filters,
    atencion_filtro_ui,
    stats,
}: Props) {
    const { t } = useTranslation(['historias-clinicas', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canCreate = can('historias-clinicas.create');
    const canUpdate = can('historias-clinicas.update');
    const canDelete = can('historias-clinicas.delete');
    const canPlanView = can('historias-clinicas-planes.view');
    const canPlanManage = can('historias-clinicas-planes.manage');
    const canVacunasCreate = can('vacunaciones.create');
    const canCargosView = can('consulta-cargos.view') || can('historias-clinicas.view');
    const canSeeAudit = can('audit-trail.view');
    const showRowActions =
        canUpdate ||
        canDelete ||
        canPlanManage ||
        (canPlanView && paginated.data.some((r) => r.plan_tratamiento)) ||
        canVacunasCreate ||
        canCargosView;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<HistoriasTableExtra>({
        routeUrl: clinica.historiasClinicas.url(),
        initialFilters: filters,
        only: [
            'consultas',
            'consulta_abrir_editar',
            'paciente_prefill_nueva_consulta',
            'filters',
            'atencion_filtro_ui',
            'stats',
            'pacientes_opciones',
        ],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.historias-clinicas.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const openedEditorFromQuery = useRef<string | null>(null);
    const openedPrefillPaciente = useRef<string | null>(null);

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((c: ConsultaHistoriaRow) => setModal({ type: 'edit', consulta: c }), []);
    const openDelete = useCallback((c: ConsultaHistoriaRow) => setModal({ type: 'delete', consulta: c }), []);

    useEffect(() => {
        const row = consulta_abrir_editar;

        if (row === null || row === undefined) {
            openedEditorFromQuery.current = null;

            return;
        }

        if (openedEditorFromQuery.current === row.id) {
            return;
        }

        openedEditorFromQuery.current = row.id;
        openEdit(row);

        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (url.searchParams.has('editar_consulta')) {
            url.searchParams.delete('editar_consulta');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    }, [consulta_abrir_editar, openEdit]);

    useEffect(() => {
        const p = paciente_prefill_nueva_consulta;
        if (p === null || p === undefined) {
            openedPrefillPaciente.current = null;

            return;
        }

        if (!canCreate) {
            return;
        }

        if (openedPrefillPaciente.current === p.id) {
            return;
        }

        openedPrefillPaciente.current = p.id;
        setModal({ type: 'create' });

        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (url.searchParams.has('nuevo_para_paciente')) {
            url.searchParams.delete('nuevo_para_paciente');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    }, [paciente_prefill_nueva_consulta, canCreate]);

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

        if (atencion_filtro_ui.fuera_del_mes_actual) {
            c += 1;
        }

        return c;
    }, [
        filters.search,
        filters.sort,
        filters.per_page,
        atencion_filtro_ui.fuera_del_mes_actual,
    ]);

    const columns = useMemo<DataTableColumn<ConsultaHistoriaRow>[]>(() => {
        const base: DataTableColumn<ConsultaHistoriaRow>[] = [
            {
                key: 'atendido_at',
                header: t('columns.atendido_at'),
                sortable: true,
                cell: (row) => (
                    <span className="whitespace-nowrap text-sm">
                        {formatAtendidoInAppTimezone(row.atendido_at, appLocale, appTz)}
                    </span>
                ),
            },
            {
                key: 'estado',
                header: t('columns.estado'),
                cell: (row) => (
                    <Badge variant={row.cerrada_at ? 'secondary' : 'outline'} className="text-[0.65rem] font-normal">
                        {row.cerrada_at ? t('row.estado_cerrada') : t('row.estado_abierta')}
                    </Badge>
                ),
                className: 'w-28',
            },
            {
                key: 'paciente',
                header: t('columns.paciente'),
                sortable: true,
                cell: (row) => (
                    <span className="font-medium text-foreground">
                        {row.historia_clinica.paciente.nombre}
                    </span>
                ),
            },
            {
                key: 'propietario',
                header: t('columns.propietario'),
                cell: (row) => (
                    <span className="text-sm text-muted-foreground">
                        {displayPropietario(row.historia_clinica.paciente.propietario)}
                    </span>
                ),
            },
            {
                key: 'motivo',
                header: t('columns.motivo'),
                cell: (row) => (
                    <span className="line-clamp-2 text-sm text-muted-foreground">
                        {truncate(row.motivo, 120)}
                    </span>
                ),
                className: 'max-w-[14rem]',
            },
            {
                key: 'peso_kg',
                header: t('columns.peso_kg'),
                cell: (row) => (
                    <span className="text-sm tabular-nums">
                        {row.peso_kg != null && row.peso_kg !== '' ? row.peso_kg : '—'}
                    </span>
                ),
                className: 'w-24',
            },
            {
                key: 'veterinario',
                header: t('columns.veterinario'),
                cell: (row) => (
                    <span className="text-sm text-muted-foreground">
                        {row.veterinario?.name ?? '—'}
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
                        <div className="flex flex-col leading-tight">
                            <span className="text-xs font-medium text-foreground">
                                {row.creado_por.name}
                            </span>
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
                        <ConsultaRowActions
                            consulta={row}
                            onEdit={openEdit}
                            onDelete={openDelete}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                            canPlanView={canPlanView}
                            canPlanManage={canPlanManage}
                            canCargosView={canCargosView}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [
        t,
        canSeeAudit,
        showRowActions,
        canUpdate,
        canDelete,
        canPlanView,
        canPlanManage,
        canCargosView,
        canVacunasCreate,
        openEdit,
        openDelete,
        appLocale,
        appTz,
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
                            icon: FileText,
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
                            icon: Stethoscope,
                        },
                    ]}
                    action={
                        <Can permission="historias-clinicas.create">
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
                                desde={filters.atendido_desde}
                                hasta={filters.atendido_hasta}
                                defaultDesde={atencion_filtro_ui.default_desde}
                                defaultHasta={atencion_filtro_ui.default_hasta}
                                disabled={isLoading}
                                onApply={(desde, hasta) =>
                                    applyFilter({ atendido_desde: desde, atendido_hasta: hasta })
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
                                atendido_desde: filters.atendido_desde,
                                atendido_hasta: filters.atendido_hasta,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Stethoscope}
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

            <ConsultaFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        openedEditorFromQuery.current = null;
                        openedPrefillPaciente.current = null;
                        closeModal();
                    }
                }}
                consulta={modal.type === 'edit' ? modal.consulta : null}
                pacientesOpciones={pacientes_opciones}
                pacienteIdPrefillNueva={paciente_prefill_nueva_consulta?.id ?? null}
                puedeCerrarConsulta={canUpdate}
            />

            <ConsultaDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                consulta={modal.type === 'delete' ? modal.consulta : null}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Historias clínicas', href: clinica.historiasClinicas.url() },
    ],
};
