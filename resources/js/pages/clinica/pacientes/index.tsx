import { Head, Link } from '@inertiajs/react';
import {
    Download,
    Filter,
    PawPrint,
    Plus,
    PowerOff,
    ScreenShare,
    Trash2,
    UserCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import {
    BulkAction,
    BulkActionBar,
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
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePlanLimitReached } from '@/hooks/use-plan-limits';
import { usePermission } from '@/hooks/use-permission';
import { useRowSelection } from '@/hooks/use-row-selection';
import clinica from '@/routes/clinica';
import pacientes from '@/routes/clinica/pacientes';
import type { Paginated } from '@/types';
import type {
    Paciente,
    PacienteEstadoFilter,
    PacienteFilters,
    PacienteStats,
    PropietarioOpcion,
} from '../propietarios/types';
import type { EspecieRazaCatalogo } from '@/lib/paciente-especie-raza-options';
import { PacienteBulkDeleteDialog } from './components/paciente-bulk-delete-dialog';
import { PacienteDeleteDialog } from './components/paciente-delete-dialog';
import { PacienteFormModal } from './components/paciente-form-modal';
import { PacienteFotoCell } from './components/paciente-foto-cell';
import { PacienteRowActions } from './components/paciente-row-actions';

type Props = {
    pacientes: Paginated<Paciente>;
    propietarios_opciones: readonly PropietarioOpcion[];
    especie_raza_catalogo: EspecieRazaCatalogo;
    filters: PacienteFilters;
    stats: PacienteStats;
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; paciente: Paciente }
    | { type: 'delete'; paciente: Paciente }
    | { type: 'bulk-delete' };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: PacienteEstadoFilter = 'todos';

function displayPropietario(p: Paciente['propietario']): string {
    if (!p) {
        return '—';
    }
    if (p.razon_social) {
        return p.razon_social;
    }
    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

function sexoLabel(
    t: (k: string) => string,
    sexo: string | null,
): string {
    if (!sexo) {
        return '—';
    }
    const k = sexo.toLowerCase();
    if (k === 'm') {
        return t('row.sexo_m');
    }
    if (k === 'h') {
        return t('row.sexo_h');
    }
    if (k === 'u') {
        return t('row.sexo_u');
    }
    return sexo;
}

export default function Index({
    pacientes: paginated,
    propietarios_opciones,
    especie_raza_catalogo,
    filters,
    stats,
}: Props) {
    const { t } = useTranslation(['pacientes', 'common']);
    const { can } = usePermission();
    const canCreate = can('pacientes.create');
    const patientsLimitReached = usePlanLimitReached('max_pacientes');
    const canCreatePatient = canCreate && !patientsLimitReached;
    const canUpdate = can('pacientes.update');
    const canDelete = can('pacientes.delete');
    const canExport = can('pacientes.export');
    const canBulkDelete = can('pacientes.bulk-delete');
    const canSeeAudit = can('audit-trail.view');
    const canDownloadCarnetVacunas = can('vacunaciones.view');
    const canViewHistorial = can('pacientes.view');
    const showRowActions =
        canUpdate || canDelete || canDownloadCarnetVacunas || canViewHistorial;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{ estado: PacienteEstadoFilter }>({
        routeUrl: pacientes.index().url,
        initialFilters: filters,
        only: ['pacientes', 'filters', 'stats', 'propietarios_opciones'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.pacientes.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estadoOptions: readonly FilterChip<PacienteEstadoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('common:filters.all') },
            { value: 'activo', label: t('common:filters.active') },
            { value: 'inactivo', label: t('common:filters.inactive') },
        ],
        [t],
    );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((p: Paciente) => setModal({ type: 'edit', paciente: p }), []);
    const openDelete = useCallback((p: Paciente) => setModal({ type: 'delete', paciente: p }), []);
    const openBulkDelete = useCallback(() => setModal({ type: 'bulk-delete' }), []);

    const selection = useRowSelection({
        rows: paginated.data,
        rowKey: (p) => p.id,
    });

    const activeFiltersCount = useMemo(() => {
        let c = 0;
        if (filters.search) {
            c += 1;
        }
        if (filters.sort) {
            c += 1;
        }
        if (filters.estado !== DEFAULT_ESTADO) {
            c += 1;
        }
        if (filters.per_page !== DEFAULT_PER_PAGE) {
            c += 1;
        }
        return c;
    }, [filters.search, filters.sort, filters.estado, filters.per_page]);

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
        if (filters.estado !== DEFAULT_ESTADO) {
            params.set('estado', filters.estado);
        }
        const qs = params.toString();
        return qs.length > 0
            ? `${pacientes.export().url}?${qs}`
            : pacientes.export().url;
    }, [filters.search, filters.sort, filters.direction, filters.estado]);

    const columns = useMemo<DataTableColumn<Paciente>[]>(() => {
        const base: DataTableColumn<Paciente>[] = [
            {
                key: 'foto',
                header: t('columns.foto'),
                cell: (p) => (
                    <PacienteFotoCell fotoUrl={p.foto_url} nombre={p.nombre} />
                ),
                className: 'w-14',
            },
            {
                key: 'nombre',
                header: t('columns.nombre'),
                sortable: true,
                cell: (p) => (
                    <div className="flex flex-col">
                        {canViewHistorial ? (
                            <Link
                                href={clinica.pacientes.show.url({ paciente: p.id })}
                                className="font-medium text-primary underline-offset-4 hover:underline"
                            >
                                {p.nombre}
                            </Link>
                        ) : (
                            <span className="font-medium text-foreground">{p.nombre}</span>
                        )}
                        {(p.especie || p.raza) && (
                            <span className="text-xs text-muted-foreground line-clamp-1">
                                {[p.especie, p.raza].filter(Boolean).join(' · ')}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                key: 'propietario',
                header: t('columns.propietario'),
                sortable: true,
                cell: (p) => (
                    <span className="text-sm">{displayPropietario(p.propietario)}</span>
                ),
            },
            {
                key: 'sexo',
                header: t('columns.sexo'),
                cell: (p) => (
                    <span className="text-xs text-muted-foreground">
                        {sexoLabel(t, p.sexo)}
                    </span>
                ),
            },
            {
                key: 'microchip',
                header: t('columns.microchip'),
                cell: (p) =>
                    p.microchip ? (
                        <span className="font-mono text-xs">{p.microchip}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'activo',
                header: t('columns.estado'),
                sortable: true,
                cell: (p) =>
                    p.activo ? (
                        <StatBadge label={t('common:filters.active')} value="" variant="success" />
                    ) : (
                        <StatBadge label={t('common:filters.inactive')} value="" variant="muted" />
                    ),
            },
        ];

        if (canSeeAudit) {
            base.push({
                key: 'creado_por',
                header: t('columns.creado_por'),
                cell: (p) => {
                    if (!p.creado_por) {
                        return (
                            <span className="text-xs text-muted-foreground">
                                {t('row.system')}
                            </span>
                        );
                    }
                    return (
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCircle className="size-4" strokeWidth={2.25} />
                            </span>
                            <div className="flex flex-col leading-tight">
                                <span className="text-xs font-medium text-foreground">
                                    {p.creado_por.name}
                                </span>
                                <span className="text-[0.65rem] text-muted-foreground">
                                    {new Date(p.created_at).toLocaleDateString(undefined, {
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
                cell: (p) => (
                    <div className="flex justify-end">
                        <PacienteRowActions
                            paciente={p}
                            onEdit={openEdit}
                            onDelete={openDelete}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                            canDownloadCarnetVacunas={canDownloadCarnetVacunas}
                            carnetVacunasPdfUrl={
                                canDownloadCarnetVacunas
                                    ? clinica.pacientes.carnetVacunacionPdf.url({ paciente: p.id })
                                    : undefined
                            }
                            canViewHistorial={canViewHistorial}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [t, canSeeAudit, showRowActions, canUpdate, canDelete, canDownloadCarnetVacunas, canViewHistorial, openEdit, openDelete]);

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        { label: t('stats.total'), value: stats.total, variant: 'info', icon: PawPrint },
                        { label: t('stats.active'), value: stats.activos, variant: 'success', icon: PawPrint },
                        { label: t('stats.inactive'), value: stats.inactivos, variant: 'muted', icon: PowerOff as LucideIcon },
                        { label: t('stats.filters'), value: activeFiltersCount, variant: 'warning', icon: Filter },
                        { label: t('stats.matches'), value: stats.coincidencias, variant: 'primary', icon: ScreenShare },
                    ]}
                    action={
                        <div className="flex flex-row items-center gap-2">
                            {canExport && (
                                <Button asChild variant="outline" className="cursor-pointer gap-2">
                                    <a href={exportUrl} download>
                                        <Download className="size-4" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">{t('common:actions.export_xlsx')}</span>
                                    </a>
                                </Button>
                            )}
                            <Can permission="pacientes.create">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="inline-flex">
                                            <Button
                                                type="button"
                                                onClick={openCreate}
                                                disabled={patientsLimitReached}
                                                className="cursor-pointer gap-2"
                                            >
                                                <Plus className="size-4" strokeWidth={2.5} />
                                                <span className="hidden sm:inline">{t('actions.new')}</span>
                                                <span className="sm:hidden">{t('actions.new_short')}</span>
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    {patientsLimitReached ? (
                                        <TooltipContent side="bottom" className="max-w-xs">
                                            {t('plan_limit.max_pacientes')}
                                        </TooltipContent>
                                    ) : null}
                                </Tooltip>
                            </Can>
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(p) => p.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    selection={canBulkDelete ? selection : undefined}
                    ariaLiveMessage={t('common:aria.results_count_other', { count: stats.coincidencias })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('filter_label')}
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
                            icon={PawPrint}
                            title={activeFiltersCount > 0 ? t('empty.no_results_title') : t('empty.no_records_title')}
                            description={activeFiltersCount > 0 ? t('empty.no_results_description') : t('empty.no_records_description')}
                            action={
                                activeFiltersCount === 0 && canCreatePatient ? (
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

            <PacienteFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                paciente={modal.type === 'edit' ? modal.paciente : null}
                propietarioFijoId={null}
                propietariosOpciones={propietarios_opciones}
                especieRazaCatalogo={especie_raza_catalogo}
            />

            <PacienteDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                paciente={modal.type === 'delete' ? modal.paciente : null}
            />

            <PacienteBulkDeleteDialog
                open={modal.type === 'bulk-delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                ids={Array.from(selection.selectedIds)}
                onCompleted={() => selection.clear()}
            />

            {canBulkDelete && (
                <BulkActionBar
                    count={selection.count}
                    labels={{
                        singular: t('bulk.selected_singular'),
                        plural: t('bulk.selected_plural'),
                    }}
                    onClear={selection.clear}
                >
                    <BulkAction
                        type="button"
                        variant="destructive"
                        size="sm"
                        onClick={openBulkDelete}
                        className="cursor-pointer gap-1.5"
                    >
                        <Trash2 className="size-4" strokeWidth={2.5} />
                        <span className="hidden sm:inline">{t('actions.delete_selected')}</span>
                    </BulkAction>
                </BulkActionBar>
            )}
        </>
    );
}
