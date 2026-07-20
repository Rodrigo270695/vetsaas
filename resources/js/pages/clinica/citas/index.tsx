import { Head, router, usePage } from '@inertiajs/react';
import { TZDate } from '@date-fns/tz';
import { Activity, CalendarDays, Download, Filter, LayoutList, Plus, UserCircle } from 'lucide-react';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { toastManager } from '@/lib/toast';
import { dashboard } from '@/routes';
import clinica from '@/routes/clinica';
import { exportMethod as citasExportExcel } from '@/routes/clinica/citas';
import type { Paginated } from '@/types';
import { AtencionDateRangeFilter } from '../historias-clinicas/components/atencion-date-range-filter';
import { formatAtendidoInAppTimezone } from '../historias-clinicas/format-atendido';
import { CitaCancelDialog } from './components/cita-cancel-dialog';
import { CitaDetailModal } from './components/cita-detail-modal';
import { CitaDeleteDialog } from './components/cita-delete-dialog';
import { CitaFormModal } from './components/cita-form-modal';
import { CitaRowActions } from './components/cita-row-actions';
import { CitasCalendar, displayPacienteCita, displayPropietarioCita, monthRangeFromMes, shiftMes } from './components/citas-calendar';
import type {
    CitaFilters,
    CitaFormPrefill,
    CitaFiltroUi,
    CitaRow,
    CitaStats,
    PacienteCitaOpcion,
    SedeCitaOpcion,
    UsuarioCitaOpcion,
    VistaCita,
} from './types';

type Props = {
    citas: Paginated<CitaRow>;
    citas_agenda: readonly CitaRow[];
    pacientes_opciones: readonly PacienteCitaOpcion[];
    usuarios_opciones: readonly UsuarioCitaOpcion[];
    sedes_opciones: readonly SedeCitaOpcion[];
    filters: CitaFilters;
    cita_filtro_ui: CitaFiltroUi;
    stats: CitaStats;
    cita_abrir_editar: CitaRow | null;
};

type CitasTableExtra = Pick<CitaFilters, 'cita_desde' | 'cita_hasta' | 'vista' | 'mes'>;

type ModalState =
    | { type: 'idle' }
    | { type: 'create'; prefill?: CitaFormPrefill }
    | { type: 'detail'; cita: CitaRow }
    | { type: 'edit'; cita: CitaRow }
    | { type: 'delete'; cita: CitaRow }
    | { type: 'cancel'; cita: CitaRow };

const DEFAULT_PER_PAGE = 10;

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

    if (estado === 'confirmada') {
        return 'default';
    }

    return 'outline';
}

export default function Index({
    citas: paginated,
    citas_agenda,
    pacientes_opciones,
    usuarios_opciones,
    sedes_opciones,
    filters,
    cita_filtro_ui,
    stats,
    cita_abrir_editar,
}: Props) {
    const { t } = useTranslation(['citas', 'common']);
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const { can } = usePermission();
    const canView = can('citas.view');
    const canCreate = can('citas.create');
    const canUpdate = can('citas.update');
    const canDelete = can('citas.delete');
    const canCancel = can('citas.cancel');
    const canSeeAudit = can('audit-trail.view');
    const showRowActions = canUpdate || canDelete || canCancel;

    const vista = (filters.vista ?? 'calendario') as VistaCita;
    const mesActivo =
        filters.mes ?? cita_filtro_ui.default_mes ?? filters.cita_desde?.slice(0, 7) ?? '';

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<CitasTableExtra>({
        routeUrl: clinica.citas.index().url,
        initialFilters: filters,
        only: [
            'citas',
            'citas_agenda',
            'pacientes_opciones',
            'usuarios_opciones',
            'sedes_opciones',
            'filters',
            'cita_filtro_ui',
            'stats',
            'cita_abrir_editar',
        ],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.citas.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const isLoadingRef = useRef(isLoading);

    useEffect(() => {
        isLoadingRef.current = isLoading;
    }, [isLoading]);

    useEffect(() => {
        const interval = window.setInterval(() => {
            if (document.visibilityState !== 'visible' || isLoadingRef.current) {
                return;
            }

            router.reload({
                only: ['citas', 'citas_agenda', 'stats'],
                preserveScroll: true,
            });
        }, 15_000);

        return () => window.clearInterval(interval);
    }, []);

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openCreateOnDay = useCallback(
        (fecha: string, hora?: string) => setModal({ type: 'create', prefill: { fecha, hora } }),
        [],
    );
    const openDetail = useCallback((c: CitaRow) => setModal({ type: 'detail', cita: c }), []);
    const openEdit = useCallback((c: CitaRow) => setModal({ type: 'edit', cita: c }), []);
    const openDelete = useCallback((c: CitaRow) => setModal({ type: 'delete', cita: c }), []);
    const openCancel = useCallback((c: CitaRow) => setModal({ type: 'cancel', cita: c }), []);

    const handleReschedule = useCallback(
        (cita: CitaRow, fecha: string, hora?: string) => {
            if (!canUpdate) {
                return;
            }

            const current = new TZDate(cita.inicio_at, appTz);
            const pad = (n: number) => String(n).padStart(2, '0');
            const time =
                hora ?? `${pad(current.getHours())}:${pad(current.getMinutes())}`;
            const inicioAt = `${fecha}T${time}`;
            const currentKey = `${current.getFullYear()}-${pad(current.getMonth() + 1)}-${pad(current.getDate())}T${pad(current.getHours())}:${pad(current.getMinutes())}`;

            if (inicioAt === currentKey) {
                return;
            }

            const target = new TZDate(`${inicioAt}:00`, appTz);
            if (target.getTime() <= Date.now()) {
                toastManager.add({
                    type: 'warning',
                    title: t('toast.inicio_pasado'),
                });

                return;
            }

            router.patch(
                clinica.citas.reschedule({ cita: cita.id }).url,
                {
                    inicio_at: inicioAt,
                    search: filters.search || undefined,
                    per_page: filters.per_page,
                    sort: filters.sort || undefined,
                    direction: filters.direction || undefined,
                    cita_desde: filters.cita_desde,
                    cita_hasta: filters.cita_hasta,
                    vista: 'calendario',
                    mes: mesActivo || undefined,
                },
                {
                    preserveScroll: true,
                    only: [
                        'citas',
                        'citas_agenda',
                        'filters',
                        'cita_filtro_ui',
                        'stats',
                        'flash',
                        'errors',
                    ],
                },
            );
        },
        [appTz, canUpdate, filters, mesActivo, t],
    );

    const openedCitaEditarRef = useRef<string | null>(null);
    useEffect(() => {
        if (!cita_abrir_editar || !canUpdate) {
            return;
        }

        if (openedCitaEditarRef.current === cita_abrir_editar.id) {
            return;
        }

        openedCitaEditarRef.current = cita_abrir_editar.id;
        openEdit(cita_abrir_editar);

        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (url.searchParams.has('editar_cita')) {
            url.searchParams.delete('editar_cita');
            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        }
    }, [cita_abrir_editar, canUpdate, openEdit]);

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

        if (cita_filtro_ui.fuera_del_mes_actual) {
            c += 1;
        }

        return c;
    }, [filters.search, filters.sort, filters.per_page, cita_filtro_ui.fuera_del_mes_actual]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();

        if (filters.search) {
            params.set('search', filters.search);
        }

        if (filters.sort) {
            params.set('sort', filters.sort);
        }

        if (filters.direction) {
            params.set('direction', filters.direction);
        }

        params.set('vista', vista);
        params.set('cita_desde', filters.cita_desde);
        params.set('cita_hasta', filters.cita_hasta);

        if (vista === 'calendario' && mesActivo) {
            params.set('mes', mesActivo);
        }

        const qs = params.toString();

        return qs.length > 0 ? `${citasExportExcel.url()}?${qs}` : citasExportExcel.url();
    }, [
        filters.search,
        filters.sort,
        filters.direction,
        filters.cita_desde,
        filters.cita_hasta,
        vista,
        mesActivo,
    ]);

    const handleVistaChange = useCallback(
        (next: VistaCita) => {
            if (next === 'calendario') {
                applyFilter({
                    vista: 'calendario',
                    mes: mesActivo,
                });

                return;
            }

            const range = monthRangeFromMes(mesActivo);

            applyFilter({
                vista: 'lista',
                cita_desde: range.desde,
                cita_hasta: range.hasta,
                mes: null,
            });
        },
        [applyFilter, mesActivo],
    );

    const columns = useMemo<DataTableColumn<CitaRow>[]>(() => {
        const base: DataTableColumn<CitaRow>[] = [
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
                        <span className="truncate text-sm font-medium">{displayPacienteCita(row.paciente)}</span>
                        <span className="truncate text-xs text-muted-foreground">
                            {displayPropietarioCita(row.paciente?.propietario)}
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
                key: 'motivo',
                header: t('columns.motivo'),
                cell: (row) => (
                    <span className="max-w-48 truncate text-sm text-muted-foreground">
                        {row.motivo?.trim() ? row.motivo : '—'}
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
                        <CitaRowActions
                            cita={row}
                            onEdit={openEdit}
                            onDelete={openDelete}
                            onCancel={openCancel}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                            canCancel={canCancel}
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
        canCancel,
        openEdit,
        openDelete,
        openCancel,
    ]);

    const toolbarFilters = (
        <div className="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <ToggleGroup
                type="single"
                value={vista}
                onValueChange={(v) => {
                    if (v === 'calendario' || v === 'lista') {
                        handleVistaChange(v);
                    }
                }}
                className="h-10 shrink-0 rounded-lg border border-border/70 bg-background/80 p-0.5 shadow-xs"
            >
                <ToggleGroupItem
                    value="calendario"
                    aria-label={t('view.calendar')}
                    className="h-8 cursor-pointer gap-1.5 px-3 text-xs data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                >
                    <CalendarDays className="size-3.5" />
                    <span className="hidden sm:inline">{t('view.calendar')}</span>
                </ToggleGroupItem>
                <ToggleGroupItem
                    value="lista"
                    aria-label={t('view.list')}
                    className="h-8 cursor-pointer gap-1.5 px-3 text-xs data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                >
                    <LayoutList className="size-3.5" />
                    <span className="hidden sm:inline">{t('view.list')}</span>
                </ToggleGroupItem>
            </ToggleGroup>

            {vista === 'lista' ? (
                <AtencionDateRangeFilter
                    desde={filters.cita_desde}
                    hasta={filters.cita_hasta}
                    defaultDesde={cita_filtro_ui.default_desde}
                    defaultHasta={cita_filtro_ui.default_hasta}
                    disabled={isLoading}
                    translationNs="citas"
                    triggerClassName="h-10"
                    onApply={(desde, hasta) => applyFilter({ cita_desde: desde, cita_hasta: hasta })}
                />
            ) : null}
        </div>
    );

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
                        <div className="flex flex-row flex-wrap items-center justify-end gap-2">
                            {canView ? (
                                <Button asChild variant="outline" className="h-10 shrink-0 cursor-pointer gap-2 px-3 font-normal">
                                    <a href={exportUrl} download>
                                        <Download className="size-4 shrink-0 opacity-70" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">{t('common:actions.export_xlsx')}</span>
                                    </a>
                                </Button>
                            ) : null}
                            <Can permission="citas.create">
                                <Button type="button" onClick={openCreate} className="cursor-pointer gap-2">
                                    <Plus className="size-4" strokeWidth={2.5} />
                                    <span className="hidden sm:inline">{t('actions.new')}</span>
                                    <span className="sm:hidden">{t('actions.new_short')}</span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                {vista === 'calendario' ? (
                    <div className="flex flex-col gap-4">
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('search_placeholder')}
                            filtersClassName="sm:flex-1 sm:min-w-0"
                        >
                            {toolbarFilters}
                        </DataToolbar>

                        <CitasCalendar
                            citas={citas_agenda}
                            mes={mesActivo}
                            timeZone={appTz}
                            isLoading={isLoading}
                            canCreate={canCreate}
                            canUpdate={canUpdate}
                            onSelectCita={openDetail}
                            onScheduleDay={openCreateOnDay}
                            onReschedule={handleReschedule}
                            onPrevMonth={() => applyFilter({ mes: shiftMes(mesActivo, -1) })}
                            onNextMonth={() => applyFilter({ mes: shiftMes(mesActivo, 1) })}
                            onJumpToMonth={(nextMes) => applyFilter({ mes: nextMes })}
                            onToday={() =>
                                applyFilter({
                                    mes: cita_filtro_ui.default_mes,
                                })
                            }
                        />
                    </div>
                ) : (
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
                                filtersClassName="sm:flex-1 sm:min-w-0"
                            >
                                {toolbarFilters}
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
                                    cita_desde: filters.cita_desde,
                                    cita_hasta: filters.cita_hasta,
                                    vista: filters.vista,
                                    mes: filters.mes ?? undefined,
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
                )}
            </div>

            <CitaDetailModal
                open={modal.type === 'detail'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                cita={modal.type === 'detail' ? modal.cita : null}
                onEdit={openEdit}
                onDelete={openDelete}
                onCancel={openCancel}
                canUpdate={canUpdate}
                canDelete={canDelete}
                canCancel={canCancel}
            />

            <CitaFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                cita={modal.type === 'edit' ? modal.cita : null}
                prefill={modal.type === 'create' ? modal.prefill ?? null : null}
                pacientesOpciones={pacientes_opciones}
                sedesOpciones={sedes_opciones}
            />

            <CitaDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                cita={modal.type === 'delete' ? modal.cita : null}
            />

            <CitaCancelDialog
                open={modal.type === 'cancel'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                cita={modal.type === 'cancel' ? modal.cita : null}
            />
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: dashboard().url },
        { title: 'Citas', href: clinica.citas.index().url },
    ],
};
