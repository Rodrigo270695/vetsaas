import { Head } from '@inertiajs/react';
import {
    Activity,
    CheckCircle2,
    Download,
    Eye,
    EyeOff,
    Filter,
    Layers,
    Plus,
    PowerOff,
    ScreenShare,
    Sparkles,
    Trash2,
} from 'lucide-react';
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
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { useRowSelection } from '@/hooks/use-row-selection';
import AppLayout from '@/layouts/app-layout';
import planes from '@/routes/plataforma/planes';
import type { Paginated } from '@/types';
import { PlanBulkDeleteDialog } from './components/plan-bulk-delete-dialog';
import { PlanDeleteDialog } from './components/plan-delete-dialog';
import { PlanFeaturesModal } from './components/plan-features-modal';
import { PlanFormModal } from './components/plan-form-modal';
import { PlanRowActions } from './components/plan-row-actions';
import type {
    Plan,
    PlanEstadoFilter,
    PlanFeatureCatalogEntry,
    PlanFilters,
    PlanStats,
} from './types';

type PlanesIndexProps = {
    plans: Paginated<Plan>;
    filters: PlanFilters;
    stats: PlanStats;
    feature_catalog: readonly PlanFeatureCatalogEntry[];
};

/**
 * State machine de modales del módulo Planes.
 * Mismo patrón que Roles (porque tenemos un modal de "features"
 * análogo al de "permisos"):
 *   - 'features' es independiente y NO se mezcla con create/edit
 *     porque opera contra otro endpoint y otro permiso.
 */
type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; plan: Plan }
    | { type: 'features'; plan: Plan }
    | { type: 'delete'; plan: Plan }
    | { type: 'bulk-delete' };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: PlanEstadoFilter = 'todos';

/**
 * Página principal del módulo Plataforma → Planes (superadmin).
 *
 * Replica el patrón Sedes/Roles/Usuarios/Tenants:
 *  - PageHeader con stats y botones (export + nuevo).
 *  - DataTable con búsqueda, chips de estado, sort, paginación,
 *    multi-selección y aria-live.
 *  - Modales create / edit / delete / bulk-delete + modal dedicado
 *    de features (gestión de límites y módulos del plan).
 *  - Persistencia local de preferencias (`per_page` / `sort`).
 *  - Export XLSX respetando los filtros vigentes.
 */
export default function Index({
    plans: paginated,
    filters,
    stats,
    feature_catalog,
}: PlanesIndexProps) {
    const { t } = useTranslation(['planes', 'common']);
    const { can } = usePermission();
    const canCreate = can('plataforma-planes.create');
    const canUpdate = can('plataforma-planes.update');
    const canDelete = can('plataforma-planes.delete');
    const canExport = can('plataforma-planes.export');
    const canBulkDelete = can('plataforma-planes.bulk-delete');
    const showRowActions = canUpdate || canDelete;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{ estado: PlanEstadoFilter }>({
        routeUrl: planes.index().url,
        initialFilters: filters,
        only: ['plans', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.plataforma.planes.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estadoOptions: readonly FilterChip<PlanEstadoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('planes:filters.all') },
            { value: 'activos', label: t('planes:filters.active') },
            { value: 'inactivos', label: t('planes:filters.inactive') },
            { value: 'publicos', label: t('planes:filters.public') },
            { value: 'privados', label: t('planes:filters.private') },
        ],
        [t],
    );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback(
        (plan: Plan) => setModal({ type: 'edit', plan }),
        [],
    );
    const openFeatures = useCallback(
        (plan: Plan) => setModal({ type: 'features', plan }),
        [],
    );
    const openDelete = useCallback(
        (plan: Plan) => setModal({ type: 'delete', plan }),
        [],
    );
    const openBulkDelete = useCallback(
        () => setModal({ type: 'bulk-delete' }),
        [],
    );

    /** Selección de filas. UUID → tipamos como string. */
    const selection = useRowSelection<Plan, string>({
        rows: paginated.data,
        rowKey: (plan) => plan.id,
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.estado, filters.per_page]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.sort) params.set('sort', filters.sort);
        if (filters.direction) params.set('direction', filters.direction);
        if (filters.estado !== DEFAULT_ESTADO)
            params.set('estado', filters.estado);

        const qs = params.toString();
        return qs.length > 0
            ? `${planes.export().url}?${qs}`
            : planes.export().url;
    }, [filters.search, filters.sort, filters.direction, filters.estado]);

    /** Formatea un decimal como "S/. 49.90". */
    const formatPrice = (value: string | null): string => {
        if (value === null) return '—';
        const num = Number(value);
        if (Number.isNaN(num)) return '—';
        return `S/. ${num.toFixed(2)}`;
    };

    const columns = useMemo<DataTableColumn<Plan>[]>(() => {
        const base: DataTableColumn<Plan>[] = [
            {
                key: 'codigo',
                header: t('planes:columns.plan'),
                sortable: true,
                cell: (plan) => {
                    const hex =
                        plan.color_hex && /^#[0-9a-fA-F]{3,6}$/.test(plan.color_hex)
                            ? plan.color_hex
                            : '#1F6E4A';
                    return (
                        <div className="flex items-center gap-2">
                            <span
                                className="flex size-8 shrink-0 items-center justify-center rounded-full"
                                style={{
                                    backgroundColor: `${hex}1A`,
                                    color: hex,
                                }}
                            >
                                <Sparkles
                                    className="size-4"
                                    strokeWidth={2.5}
                                />
                            </span>
                            <div className="flex min-w-0 flex-col leading-tight">
                                <div className="flex items-center gap-1.5">
                                    <span className="truncate text-sm font-semibold text-foreground">
                                        {plan.nombre}
                                    </span>
                                    {plan.badge && (
                                        <span
                                            className="rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                                            style={{
                                                color: hex,
                                                backgroundColor: `${hex}1A`,
                                            }}
                                        >
                                            {plan.badge}
                                        </span>
                                    )}
                                </div>
                                <span className="truncate font-mono text-xs text-muted-foreground">
                                    {plan.codigo}
                                </span>
                            </div>
                        </div>
                    );
                },
            },
            {
                key: 'precio_mensual',
                header: t('planes:columns.pricing'),
                sortable: true,
                cell: (plan) => (
                    <div className="flex flex-col text-xs leading-tight">
                        <span className="font-mono font-semibold text-foreground">
                            {formatPrice(plan.precio_mensual)}
                            <span className="text-[10px] font-normal text-muted-foreground">
                                {' '}
                                /{t('planes:row.per_month')}
                            </span>
                        </span>
                        {plan.precio_anual ? (
                            <span className="font-mono text-muted-foreground">
                                {formatPrice(plan.precio_anual)}{' '}
                                <span className="text-[10px]">
                                    /{t('planes:row.per_year')}
                                </span>
                            </span>
                        ) : (
                            <span className="text-muted-foreground italic">
                                {t('planes:row.no_yearly')}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                key: 'features_count',
                header: t('planes:columns.features'),
                cell: (plan) => (
                    <div className="flex items-center gap-1.5">
                        <Layers
                            className="size-3.5 shrink-0 text-primary/70"
                            strokeWidth={2.25}
                        />
                        <span className="text-xs font-medium">
                            {plan.features_count}
                        </span>
                    </div>
                ),
            },
            {
                key: 'subscriptions_count',
                header: t('planes:columns.subscriptions'),
                cell: (plan) => (
                    <span
                        className={
                            plan.subscriptions_count > 0
                                ? 'text-xs font-mono font-semibold text-foreground'
                                : 'text-xs text-muted-foreground italic'
                        }
                    >
                        {plan.subscriptions_count > 0
                            ? plan.subscriptions_count
                            : t('planes:row.no_subscriptions')}
                    </span>
                ),
            },
            {
                key: 'trial_days',
                header: t('planes:columns.trial'),
                cell: (plan) =>
                    plan.trial_days > 0 ? (
                        <span className="text-xs font-mono">
                            {plan.trial_days}{' '}
                            <span className="text-muted-foreground">
                                {t('planes:row.days')}
                            </span>
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground italic">
                            {t('planes:row.no_trial')}
                        </span>
                    ),
            },
            {
                key: 'flags',
                header: t('planes:columns.status'),
                cell: (plan) => (
                    <div className="flex flex-wrap items-center gap-1.5">
                        <StatBadge
                            label={
                                plan.activo
                                    ? t('planes:row.active')
                                    : t('planes:row.inactive')
                            }
                            value=""
                            variant={plan.activo ? 'success' : 'warning'}
                        />
                        <StatBadge
                            label={
                                plan.es_publico
                                    ? t('planes:row.public')
                                    : t('planes:row.private')
                            }
                            value=""
                            variant={plan.es_publico ? 'info' : 'muted'}
                        />
                    </div>
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: (
                    <span className="md:sr-only">
                        {t('planes:columns.acciones')}
                    </span>
                ),
                align: 'right',
                cell: (plan: Plan) => (
                    <div className="flex justify-end">
                        <PlanRowActions
                            plan={plan}
                            onEdit={openEdit}
                            onManageFeatures={openFeatures}
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
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        t,
        showRowActions,
        canUpdate,
        canDelete,
        openEdit,
        openFeatures,
        openDelete,
    ]);

    return (
        <>
            <Head title={t('planes:title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('planes:title')}
                    description={t('planes:description')}
                    stats={[
                        {
                            label: t('planes:stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: Layers,
                        },
                        {
                            label: t('planes:stats.active'),
                            value: stats.activos,
                            variant: 'success',
                            icon: CheckCircle2,
                        },
                        {
                            label: t('planes:stats.inactive'),
                            value: stats.inactivos,
                            variant: 'warning',
                            icon: PowerOff,
                        },
                        {
                            label: t('planes:stats.public'),
                            value: stats.publicos,
                            variant: 'primary',
                            icon: Eye,
                        },
                        {
                            label: t('planes:stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('planes:stats.matches'),
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
                                        <span className="hidden sm:inline">
                                            {t('common:actions.export_xlsx')}
                                        </span>
                                    </a>
                                </Button>
                            )}
                            <Can permission="plataforma-planes.create">
                                <Button
                                    type="button"
                                    onClick={openCreate}
                                    className="cursor-pointer gap-2"
                                >
                                    <Plus
                                        className="size-4"
                                        strokeWidth={2.5}
                                    />
                                    <span className="hidden sm:inline">
                                        {t('planes:actions.new')}
                                    </span>
                                    <span className="sm:hidden">
                                        {t('planes:actions.new_short')}
                                    </span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(plan) => plan.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    selection={canBulkDelete ? selection : undefined}
                    ariaLiveMessage={t('planes:aria.results_count_other', {
                        count: stats.coincidencias,
                    })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('planes:search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('planes:filter_label')}
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
                            icon={
                                activeFiltersCount > 0 ? Activity : EyeOff
                            }
                            title={
                                activeFiltersCount > 0
                                    ? t('planes:empty.no_results_title')
                                    : t('planes:empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('planes:empty.no_results_description')
                                    : t('planes:empty.no_records_description')
                            }
                            action={
                                activeFiltersCount === 0 && canCreate ? (
                                    <Button
                                        type="button"
                                        onClick={openCreate}
                                        className="cursor-pointer gap-2"
                                    >
                                        <Plus
                                            className="size-4"
                                            strokeWidth={2.5}
                                        />
                                        {t('planes:actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <PlanFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                plan={modal.type === 'edit' ? modal.plan : null}
            />

            <PlanFeaturesModal
                open={modal.type === 'features'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                plan={modal.type === 'features' ? modal.plan : null}
                initialFeatures={
                    modal.type === 'features' ? modal.plan.features : []
                }
                catalog={feature_catalog}
            />

            <PlanDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                plan={modal.type === 'delete' ? modal.plan : null}
            />

            <PlanBulkDeleteDialog
                open={modal.type === 'bulk-delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                ids={Array.from(selection.selectedIds)}
                onCompleted={() => selection.clear()}
            />

            {canBulkDelete && (
                <BulkActionBar
                    count={selection.count}
                    labels={{
                        singular: t('planes:bulk.selected_singular'),
                        plural: t('planes:bulk.selected_plural'),
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
                            {t('planes:actions.delete_selected')}
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
            { title: 'Plataforma' },
            { title: 'Planes', href: '/plataforma/planes' },
        ]}
    >
        {page}
    </AppLayout>
);
