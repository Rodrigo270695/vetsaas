import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    Download,
    Filter,
    MapPin,
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
import type {
    DataTableColumn,
    FilterChip,
} from '@/components/data-page';
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
import AppLayout from '@/layouts/app-layout';
import sedes from '@/routes/configuracion/sedes';
import type { Paginated } from '@/types';
import { SedeBulkDeleteDialog } from './components/sede-bulk-delete-dialog';
import { SedeDeleteDialog } from './components/sede-delete-dialog';
import { SedeFormModal } from './components/sede-form-modal';
import { SedeRowActions } from './components/sede-row-actions';
import type {
    GeoOption,
    Sede,
    SedeEstadoFilter,
    SedeFilters,
    SedeStats,
} from './types';

type SedesIndexProps = {
    sedes: Paginated<Sede>;
    filters: SedeFilters;
    stats: SedeStats;
    /** Catálogo de departamentos para el modal (cascada geo). */
    departamentos: readonly GeoOption[];
};

/**
 * State machine de modales del módulo Sedes.
 * Un solo estado evita inconsistencias entre tres useStates booleanos
 * y deja claras las transiciones posibles.
 */
type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; sede: Sede }
    | { type: 'delete'; sede: Sede }
    | { type: 'bulk-delete' };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: SedeEstadoFilter = 'todas';

/**
 * Página principal del módulo Sedes (Configuración → Sedes).
 *
 * Estructura:
 *  - PageHeader con título, descripción, badges y acción primaria.
 *  - DataTable con toolbar, paginación, selección y aria-live integrados.
 *  - Modales de crear/editar/eliminar y bulk-delete bajo state machine.
 *
 * Comportamiento:
 *  - Búsqueda con debounce adaptativo (300/500/800ms).
 *  - Filtros sincronizados con la URL (Inertia partial reload).
 *  - Persistencia de `per_page`/`sort` en localStorage por usuario.
 *  - Reset automático a página 1 ante cualquier cambio de filtros.
 *  - Loading visible (atenuación) durante consultas.
 *  - Bulk-delete con selección multi-fila.
 *  - Export CSV respetando filtros activos.
 *  - i18n (`sedes` + `common` namespaces) con cambio dinámico de idioma.
 */
export default function Index({
    sedes: paginated,
    filters,
    stats,
    departamentos,
}: SedesIndexProps) {
    const { t } = useTranslation(['sedes', 'common']);
    const { can } = usePermission();
    const canCreate = can('sedes.create');
    const canUpdate = can('sedes.update');
    const canDelete = can('sedes.delete');
    const canExport = can('sedes.export');
    const canBulkDelete = can('sedes.bulk-delete');
    const canSeeAudit = can('audit-trail.view');
    const showRowActions = canUpdate || canDelete;
    const sedesLimitReached = usePlanLimitReached('max_sedes');

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{ estado: SedeEstadoFilter }>({
        routeUrl: sedes.index().url,
        initialFilters: filters,
        only: ['sedes', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.sedes.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    /** Filtro segmentado de estado (depende de i18n). */
    const estadoOptions: readonly FilterChip<SedeEstadoFilter>[] = useMemo(
        () => [
            { value: 'todas', label: t('common:filters.all') },
            { value: 'activa', label: t('common:filters.active') },
            { value: 'inactiva', label: t('common:filters.inactive') },
        ],
        [t],
    );

    /** State machine consolidada para los modales. */
    const [modal, setModal] = useState<ModalState>({ type: 'idle' });

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback(
        (sede: Sede) => setModal({ type: 'edit', sede }),
        [],
    );
    const openDelete = useCallback(
        (sede: Sede) => setModal({ type: 'delete', sede }),
        [],
    );
    const openBulkDelete = useCallback(
        () => setModal({ type: 'bulk-delete' }),
        [],
    );

    /** Selección de filas (limpia automáticamente al cambiar de página). */
    const selection = useRowSelection({
        rows: paginated.data,
        rowKey: (sede) => sede.id,
    });

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

        if (filters.per_page !== DEFAULT_PER_PAGE) {
            count += 1;
        }

        return count;
    }, [filters.search, filters.sort, filters.estado, filters.per_page]);

    /** URL del endpoint de export respetando filtros vigentes. */
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
            ? `${sedes.export().url}?${qs}`
            : sedes.export().url;
    }, [filters.search, filters.sort, filters.direction, filters.estado]);

    const columns = useMemo<DataTableColumn<Sede>[]>(() => {
        const base: DataTableColumn<Sede>[] = [
            {
                key: 'codigo',
                header: t('columns.codigo'),
                sortable: true,
                cell: (sede) => (
                    <span className="font-mono text-xs font-semibold tracking-wider text-foreground">
                        {sede.codigo}
                    </span>
                ),
                className: 'w-28',
            },
            {
                key: 'nombre',
                header: t('columns.nombre'),
                sortable: true,
                cell: (sede) => (
                    <div className="flex flex-col">
                        <span className="font-medium text-foreground">
                            {sede.nombre}
                        </span>
                        {sede.email && (
                            <span className="text-xs text-muted-foreground">
                                {sede.email}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                key: 'ubicacion',
                header: t('columns.ubicacion'),
                sortable: true,
                sortKey: 'distrito',
                cell: (sede) => {
                    const parts = [
                        sede.distrito,
                        sede.provincia,
                        sede.departamento,
                    ].filter(Boolean);

                    if (parts.length === 0) {
                        return (
                            <span className="text-xs text-muted-foreground">
                                {t('row.no_location')}
                            </span>
                        );
                    }

                    return (
                        <div className="flex items-start gap-1.5 text-xs text-muted-foreground">
                            <MapPin
                                className="mt-0.5 size-3.5 shrink-0 text-primary/70"
                                strokeWidth={2.25}
                            />
                            <span>{parts.join(' · ')}</span>
                        </div>
                    );
                },
            },
            {
                key: 'telefono',
                header: t('columns.telefono'),
                sortable: true,
                cell: (sede) =>
                    sede.telefono ? (
                        <span className="text-sm tabular-nums">
                            {sede.telefono}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            —
                        </span>
                    ),
            },
            {
                key: 'series',
                header: t('columns.series'),
                cell: (sede) => (
                    <Link
                        href={`/facturacion/series?sede_id=${sede.id}`}
                        className="text-xs font-medium text-primary hover:underline"
                    >
                        {t('columns.series_link')}
                    </Link>
                ),
            },
            {
                key: 'estado',
                header: t('columns.estado'),
                sortable: true,
                sortKey: 'activa',
                cell: (sede) =>
                    sede.activa ? (
                        <StatBadge
                            label={t('common:filters.active')}
                            value=""
                            variant="success"
                        />
                    ) : (
                        <StatBadge
                            label={t('common:filters.inactive')}
                            value=""
                            variant="muted"
                        />
                    ),
            },
        ];

        if (canSeeAudit) {
            base.push({
                key: 'creado_por',
                header: t('columns.creada_por'),
                cell: (sede: Sede) => {
                    if (!sede.creado_por) {
                        return (
                            <span className="text-xs text-muted-foreground">
                                {t('row.system')}
                            </span>
                        );
                    }

                    return (
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCircle
                                    className="size-4"
                                    strokeWidth={2.25}
                                />
                            </span>
                            <div className="flex flex-col leading-tight">
                                <span className="text-xs font-medium text-foreground">
                                    {sede.creado_por.name}
                                </span>
                                <span className="text-[0.65rem] text-muted-foreground">
                                    {new Date(sede.created_at).toLocaleDateString(
                                        undefined,
                                        {
                                            day: '2-digit',
                                            month: 'short',
                                            year: 'numeric',
                                        },
                                    )}
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
                header: (
                    <span className="md:sr-only">{t('columns.acciones')}</span>
                ),
                align: 'right',
                cell: (sede: Sede) => (
                    <div className="flex justify-end">
                        <SedeRowActions
                            sede={sede}
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
    }, [t, canSeeAudit, showRowActions, canUpdate, canDelete, openEdit, openDelete]);

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
                            icon: Building2,
                        },
                        {
                            label: t('stats.active'),
                            value: stats.activas,
                            variant: 'success',
                            icon: CheckCircle2,
                        },
                        {
                            label: t('stats.inactive'),
                            value: stats.inactivas,
                            variant: 'muted',
                            icon: PowerOff as LucideIcon,
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
                            icon: ScreenShare,
                        },
                    ]}
                    action={
                        <div className="flex flex-row items-center gap-2">
                            {canExport && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="cursor-pointer gap-2"
                                >
                                    <a href={exportUrl} download>
                                        <Download
                                            className="size-4"
                                            strokeWidth={2.5}
                                        />
                                        {/* Texto se oculta en mobile: queda solo el icono para ahorrar ancho. */}
                                        <span className="hidden sm:inline">
                                            {t('common:actions.export_xlsx')}
                                        </span>
                                    </a>
                                </Button>
                            )}
                            <Can permission="sedes.create">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="inline-flex">
                                            <Button
                                                type="button"
                                                onClick={openCreate}
                                                disabled={sedesLimitReached}
                                                className="cursor-pointer gap-2"
                                            >
                                                <Plus
                                                    className="size-4"
                                                    strokeWidth={2.5}
                                                />
                                                <span className="hidden sm:inline">
                                                    {t('actions.new')}
                                                </span>
                                                <span className="sm:hidden">
                                                    {t('actions.new_short')}
                                                </span>
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    {sedesLimitReached ? (
                                        <TooltipContent side="bottom" className="max-w-xs">
                                            {t('plan_limit.max_sedes')}
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
                    rowKey={(sede) => sede.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    selection={canBulkDelete ? selection : undefined}
                    ariaLiveMessage={t('aria.results_count_other', {
                        count: stats.coincidencias,
                    })}
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
                                estado:
                                    filters.estado !== DEFAULT_ESTADO
                                        ? filters.estado
                                        : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Building2}
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
                                    <Button
                                        type="button"
                                        onClick={openCreate}
                                        disabled={sedesLimitReached}
                                        className="cursor-pointer gap-2"
                                    >
                                        <Plus
                                            className="size-4"
                                            strokeWidth={2.5}
                                        />
                                        {t('actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <SedeFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                sede={modal.type === 'edit' ? modal.sede : null}
                departamentos={departamentos}
            />

            <SedeDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                sede={modal.type === 'delete' ? modal.sede : null}
            />

            <SedeBulkDeleteDialog
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
                        <span className="hidden sm:inline">
                            {t('actions.delete_selected')}
                        </span>
                    </BulkAction>
                </BulkActionBar>
            )}
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Configuración' },
        { title: 'Sedes', href: '/configuracion/sedes' },
        ]}
    >
        {page}
    </AppLayout>
);
