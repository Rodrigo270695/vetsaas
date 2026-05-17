import { Head } from '@inertiajs/react';
import {
    Activity,
    Ban,
    CheckCircle2,
    Clock3,
    DollarSign,
    Download,
    Filter,
    Hourglass,
    PauseCircle,
    Plus,
    Repeat,
    ScreenShare,
    Trash2,
    XCircle,
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
import suscripciones from '@/routes/plataforma/suscripciones';
import type { Paginated } from '@/types';
import { SubscriptionActionsDialog, type SubscriptionActionMode } from './components/subscription-actions-dialog';
import { SubscriptionBulkDeleteDialog } from './components/subscription-bulk-delete-dialog';
import { SubscriptionDeleteDialog } from './components/subscription-delete-dialog';
import { SubscriptionFormModal } from './components/subscription-form-modal';
import { SubscriptionRowActions } from './components/subscription-row-actions';
import type {
    Subscription,
    SubscriptionEstadoFilter,
    SubscriptionFilters,
    SubscriptionPlanOption,
    SubscriptionStats,
    SubscriptionTenantOption,
} from './types';

type SuscripcionesIndexProps = {
    subscriptions: Paginated<Subscription>;
    filters: SubscriptionFilters;
    stats: SubscriptionStats;
    plans_catalog: readonly SubscriptionPlanOption[];
    tenants_catalog: readonly SubscriptionTenantOption[];
};

/**
 * State machine de modales del módulo Suscripciones.
 *
 * Tenemos 3 dialogs especializados que comparten skeleton:
 *   - 'extend-trial': extender prueba.
 *   - 'change-plan' : cambiar plan.
 *   - 'cancel'      : cancelar.
 *
 * Internamente se renderizan con `SubscriptionActionsDialog` y se
 * distinguen por el campo `mode`.
 */
type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; subscription: Subscription }
    | { type: 'extend-trial'; subscription: Subscription }
    | { type: 'change-plan'; subscription: Subscription }
    | { type: 'cancel'; subscription: Subscription }
    | { type: 'delete'; subscription: Subscription }
    | { type: 'bulk-delete' };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: SubscriptionEstadoFilter = 'todos';

const formatPrice = (value: string | null): string => {
    if (value === null) return '—';
    const num = Number(value);
    if (Number.isNaN(num)) return '—';
    return `S/. ${num.toFixed(2)}`;
};

const formatDate = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

/**
 * Renderiza un badge para el estado de la suscripción usando la paleta
 * Verde Bosque Clínico. Centralizado acá porque varias columnas lo
 * usan y queremos un único lugar para tocar colores.
 */
function renderEstadoBadge(
    estado: Subscription['estado'],
    t: (key: string) => string,
) {
    const label = t(`suscripciones:estados.${estado}`);
    const variant: Parameters<typeof StatBadge>[0]['variant'] =
        estado === 'active'
            ? 'success'
            : estado === 'trial'
              ? 'info'
              : estado === 'grace'
                ? 'warning'
                : estado === 'suspended'
                  ? 'warning'
                  : 'muted';
    return <StatBadge label={label} value="" variant={variant} />;
}

/**
 * Página principal del módulo Plataforma → Suscripciones (superadmin).
 *
 * Igual patrón que Sedes/Roles/Usuarios/Tenants/Planes:
 *   - PageHeader con stats (incluye MRR estimado).
 *   - DataTable con búsqueda transitiva (busca por tenant y plan),
 *     chips de estado, sort, paginación, multi-selección y aria-live.
 *   - Modales create / edit / delete / bulk-delete + 3 dialogs de
 *     transición (extend-trial, change-plan, cancel).
 *   - Persistencia local de preferencias y export XLSX.
 */
export default function Index({
    subscriptions: paginated,
    filters,
    stats,
    plans_catalog,
    tenants_catalog,
}: SuscripcionesIndexProps) {
    const { t } = useTranslation(['suscripciones', 'common']);
    const { can } = usePermission();
    const canCreate = can('plataforma-suscripciones.create');
    const canUpdate = can('plataforma-suscripciones.update');
    const canDelete = can('plataforma-suscripciones.delete');
    const canExport = can('plataforma-suscripciones.export');
    const canBulkDelete = can('plataforma-suscripciones.bulk-delete');
    const canExtendTrial = can('plataforma-suscripciones.extend-trial');
    const canChangePlan = can('plataforma-suscripciones.change-plan');
    const canCancel = can('plataforma-suscripciones.cancel');
    const showRowActions =
        canUpdate ||
        canDelete ||
        canExtendTrial ||
        canChangePlan ||
        canCancel;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{
        estado: SubscriptionEstadoFilter;
        plan_id: string | null;
    }>({
        routeUrl: suscripciones.index().url,
        initialFilters: filters,
        only: ['subscriptions', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.plataforma.suscripciones.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estadoOptions: readonly FilterChip<SubscriptionEstadoFilter>[] =
        useMemo(
            () => [
                { value: 'todos', label: t('suscripciones:filters.all') },
                { value: 'trial', label: t('suscripciones:filters.trial') },
                {
                    value: 'active',
                    label: t('suscripciones:filters.active'),
                },
                { value: 'grace', label: t('suscripciones:filters.grace') },
                {
                    value: 'suspended',
                    label: t('suscripciones:filters.suspended'),
                },
                {
                    value: 'cancelled',
                    label: t('suscripciones:filters.cancelled'),
                },
            ],
            [t],
        );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback(
        (subscription: Subscription) =>
            setModal({ type: 'edit', subscription }),
        [],
    );
    const openExtendTrial = useCallback(
        (subscription: Subscription) =>
            setModal({ type: 'extend-trial', subscription }),
        [],
    );
    const openChangePlan = useCallback(
        (subscription: Subscription) =>
            setModal({ type: 'change-plan', subscription }),
        [],
    );
    const openCancel = useCallback(
        (subscription: Subscription) =>
            setModal({ type: 'cancel', subscription }),
        [],
    );
    const openDelete = useCallback(
        (subscription: Subscription) =>
            setModal({ type: 'delete', subscription }),
        [],
    );
    const openBulkDelete = useCallback(
        () => setModal({ type: 'bulk-delete' }),
        [],
    );

    /** Selección de filas. UUID → string. */
    const selection = useRowSelection<Subscription, string>({
        rows: paginated.data,
        rowKey: (s) => s.id,
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;
        if (filters.plan_id) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [
        filters.search,
        filters.sort,
        filters.estado,
        filters.plan_id,
        filters.per_page,
    ]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.sort) params.set('sort', filters.sort);
        if (filters.direction) params.set('direction', filters.direction);
        if (filters.estado !== DEFAULT_ESTADO)
            params.set('estado', filters.estado);
        if (filters.plan_id) params.set('plan_id', filters.plan_id);

        const qs = params.toString();
        return qs.length > 0
            ? `${suscripciones.export().url}?${qs}`
            : suscripciones.export().url;
    }, [
        filters.search,
        filters.sort,
        filters.direction,
        filters.estado,
        filters.plan_id,
    ]);

    /**
     * Determina el modo del SubscriptionActionsDialog según el estado
     * actual del modal. Devuelve null si no es ninguno de los 3 modos.
     */
    const actionsMode: SubscriptionActionMode | null = useMemo(() => {
        if (
            modal.type === 'extend-trial' ||
            modal.type === 'change-plan' ||
            modal.type === 'cancel'
        ) {
            return modal.type;
        }
        return null;
    }, [modal]);

    const actionsSubscription = useMemo(() => {
        if (
            modal.type === 'extend-trial' ||
            modal.type === 'change-plan' ||
            modal.type === 'cancel'
        ) {
            return modal.subscription;
        }
        return null;
    }, [modal]);

    const columns = useMemo<DataTableColumn<Subscription>[]>(() => {
        const base: DataTableColumn<Subscription>[] = [
            {
                key: 'tenant',
                header: t('suscripciones:columns.tenant'),
                cell: (s) => (
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-semibold text-foreground">
                            {s.tenant?.razon_social ?? '—'}
                        </span>
                        <span className="truncate font-mono text-xs text-muted-foreground">
                            {s.tenant?.slug ?? '—'}
                        </span>
                    </div>
                ),
            },
            {
                key: 'plan',
                header: t('suscripciones:columns.plan'),
                cell: (s) => {
                    if (!s.plan) {
                        return (
                            <span className="text-xs text-muted-foreground italic">
                                —
                            </span>
                        );
                    }
                    const hex =
                        s.plan.color_hex &&
                        /^#[0-9a-fA-F]{3,6}$/.test(s.plan.color_hex)
                            ? s.plan.color_hex
                            : '#1F6E4A';
                    return (
                        <div className="flex flex-col gap-0.5 leading-tight">
                            <div className="flex items-center gap-1.5">
                                <span
                                    className="size-2 shrink-0 rounded-full"
                                    style={{ backgroundColor: hex }}
                                />
                                <span className="text-sm font-medium">
                                    {s.plan.nombre}
                                </span>
                                {s.plan.badge && (
                                    <span
                                        className="rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                                        style={{
                                            color: hex,
                                            backgroundColor: `${hex}1A`,
                                        }}
                                    >
                                        {s.plan.badge}
                                    </span>
                                )}
                            </div>
                            <span className="font-mono text-xs text-muted-foreground">
                                {s.plan.codigo}
                            </span>
                        </div>
                    );
                },
            },
            {
                key: 'estado',
                header: t('suscripciones:columns.estado'),
                sortable: true,
                cell: (s) => renderEstadoBadge(s.estado, t),
            },
            {
                key: 'ciclo',
                header: t('suscripciones:columns.ciclo'),
                sortable: true,
                cell: (s) => (
                    <span className="text-xs">
                        {t(`suscripciones:ciclos.${s.ciclo}`)}
                    </span>
                ),
            },
            {
                key: 'precio_pactado',
                header: t('suscripciones:columns.precio_pactado'),
                sortable: true,
                cell: (s) => (
                    <div className="flex flex-col leading-tight">
                        <span className="font-mono text-sm font-semibold">
                            {formatPrice(s.precio_pactado)}
                        </span>
                        {Number(s.descuento_pct) > 0 && (
                            <span className="text-[10px] text-emerald-600 dark:text-emerald-400">
                                −{Number(s.descuento_pct).toFixed(2)}%
                            </span>
                        )}
                    </div>
                ),
            },
            {
                key: 'trial_ends_at',
                header: t('suscripciones:columns.trial_ends_at'),
                sortable: true,
                cell: (s) => (
                    <span className="text-xs">
                        {formatDate(s.trial_ends_at)}
                    </span>
                ),
            },
            {
                key: 'proximo_cobro_at',
                header: t('suscripciones:columns.proximo_cobro_at'),
                sortable: true,
                cell: (s) => (
                    <span className="text-xs">
                        {formatDate(s.proximo_cobro_at)}
                    </span>
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: (
                    <span className="md:sr-only">
                        {t('suscripciones:columns.acciones')}
                    </span>
                ),
                align: 'right',
                cell: (s: Subscription) => (
                    <div className="flex justify-end">
                        <SubscriptionRowActions
                            subscription={s}
                            onEdit={openEdit}
                            onExtendTrial={openExtendTrial}
                            onChangePlan={openChangePlan}
                            onCancel={openCancel}
                            onDelete={openDelete}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                            canExtendTrial={canExtendTrial}
                            canChangePlan={canChangePlan}
                            canCancel={canCancel}
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
        canExtendTrial,
        canChangePlan,
        canCancel,
        openEdit,
        openExtendTrial,
        openChangePlan,
        openCancel,
        openDelete,
    ]);

    return (
        <>
            <Head title={t('suscripciones:title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('suscripciones:title')}
                    description={t('suscripciones:description')}
                    stats={[
                        {
                            label: t('suscripciones:stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: Repeat,
                        },
                        {
                            label: t('suscripciones:stats.active'),
                            value: stats.active,
                            variant: 'success',
                            icon: CheckCircle2,
                        },
                        {
                            label: t('suscripciones:stats.trial'),
                            value: stats.trial,
                            variant: 'info',
                            icon: Hourglass,
                        },
                        {
                            label: t('suscripciones:stats.grace'),
                            value: stats.grace,
                            variant: 'warning',
                            icon: Clock3,
                        },
                        {
                            label: t('suscripciones:stats.suspended'),
                            value: stats.suspended,
                            variant: 'warning',
                            icon: PauseCircle,
                        },
                        {
                            label: t('suscripciones:stats.cancelled'),
                            value: stats.cancelled,
                            variant: 'muted',
                            icon: XCircle,
                        },
                        {
                            label: t('suscripciones:stats.mrr'),
                            value: formatPrice(String(stats.mrr)),
                            variant: 'primary',
                            icon: DollarSign,
                        },
                        {
                            label: t('suscripciones:stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('suscripciones:stats.matches'),
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
                            <Can permission="plataforma-suscripciones.create">
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
                                        {t('suscripciones:actions.new')}
                                    </span>
                                    <span className="sm:hidden">
                                        {t('suscripciones:actions.new_short')}
                                    </span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(s) => s.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    selection={canBulkDelete ? selection : undefined}
                    ariaLiveMessage={t(
                        'suscripciones:aria.results_count_other',
                        {
                            count: stats.coincidencias,
                        },
                    )}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t(
                                'suscripciones:search_placeholder',
                            )}
                        >
                            <FilterChips
                                ariaLabel={t(
                                    'suscripciones:filter_label',
                                )}
                                value={filters.estado}
                                onChange={(estado) =>
                                    applyFilter({ estado })
                                }
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
                                plan_id: filters.plan_id ?? undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={
                                activeFiltersCount > 0 ? Activity : Ban
                            }
                            title={
                                activeFiltersCount > 0
                                    ? t(
                                          'suscripciones:empty.no_results_title',
                                      )
                                    : t(
                                          'suscripciones:empty.no_records_title',
                                      )
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t(
                                          'suscripciones:empty.no_results_description',
                                      )
                                    : t(
                                          'suscripciones:empty.no_records_description',
                                      )
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
                                        {t(
                                            'suscripciones:actions.create_first',
                                        )}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <SubscriptionFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                subscription={modal.type === 'edit' ? modal.subscription : null}
                plansCatalog={plans_catalog}
                tenantsCatalog={tenants_catalog}
            />

            <SubscriptionActionsDialog
                open={actionsMode !== null}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                subscription={actionsSubscription}
                mode={actionsMode}
                plansCatalog={plans_catalog}
            />

            <SubscriptionDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                subscription={
                    modal.type === 'delete' ? modal.subscription : null
                }
            />

            <SubscriptionBulkDeleteDialog
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
                        singular: t(
                            'suscripciones:bulk.selected_singular',
                        ),
                        plural: t('suscripciones:bulk.selected_plural'),
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
                            {t('suscripciones:actions.delete_selected')}
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
            { title: 'Suscripciones', href: '/plataforma/suscripciones' },
        ]}
    >
        {page}
    </AppLayout>
);
