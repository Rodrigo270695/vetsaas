import { Head, usePage } from '@inertiajs/react';
import {
    Activity,
    CheckCircle2,
    Download,
    Filter,
    MailCheck,
    PauseCircle,
    Plus,
    ScreenShare,
    ShieldCheck,
    Trash2,
    UserCog,
    Users as UsersIcon,
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
import usuarios from '@/routes/configuracion/usuarios';
import type { Auth, Paginated } from '@/types';
import { UserBulkDeleteDialog } from './components/user-bulk-delete-dialog';
import { UserDeleteDialog } from './components/user-delete-dialog';
import { UserFormModal } from './components/user-form-modal';
import { UserRowActions } from './components/user-row-actions';
import type {
    User,
    UserEstadoFilter,
    UserFilters,
    UserRoleOption,
    UserStats,
} from './types';

type UsuariosIndexProps = {
    users: Paginated<User>;
    filters: UserFilters;
    stats: UserStats;
    roles_catalog: readonly UserRoleOption[];
};

/**
 * State machine de modales del módulo Usuarios.
 * Mismo patrón que Sedes/Roles.
 */
type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; user: User }
    | { type: 'delete'; user: User }
    | { type: 'bulk-delete' };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: UserEstadoFilter = 'todos';

/**
 * Página principal del módulo Usuarios (Configuración → Usuarios).
 *
 * Replica el patrón Sedes/Roles:
 *  - PageHeader con título, descripción, stats y botones de acción.
 *  - DataTable con búsqueda + filtros segmentados, paginación, multi-
 *    selección y `aria-live` con el conteo de coincidencias.
 *  - Modales create / edit / delete / bulk-delete bajo state machine.
 *  - Persistencia local de `per_page` / `sort` por módulo.
 *  - Export XLSX respetando los filtros vigentes.
 *
 * Particularidades de usuarios:
 *  - Las cuentas `superadmin` y la propia sesión nunca pueden ser
 *    eliminadas (la UI muestra un lock y el backend lo bloquea).
 *  - El catálogo `roles_catalog` viaja con cada index() para mantener
 *    el select del modal sincronizado con la BD.
 */
export default function Index({
    users: paginated,
    filters,
    stats,
    roles_catalog,
}: UsuariosIndexProps) {
    const { t } = useTranslation(['usuarios', 'common']);
    const { can } = usePermission();
    const canCreate = can('usuarios.create');
    const canUpdate = can('usuarios.update');
    const canDelete = can('usuarios.delete');
    const canExport = can('usuarios.export');
    const canBulkDelete = can('usuarios.bulk-delete');
    const showRowActions = canUpdate || canDelete;

    // Necesario para marcar "Tú" en la propia fila y bloquear el delete.
    const page = usePage<{ auth: Auth }>();
    const currentUserId = useMemo(() => {
        const id = page.props.auth.user?.id;
        return typeof id === 'string' ? id : id != null ? String(id) : null;
    }, [page.props.auth.user?.id]);

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{
        estado: UserEstadoFilter;
        rol: string | null;
    }>({
        routeUrl: usuarios.index().url,
        initialFilters: filters,
        only: ['users', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.usuarios.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estadoOptions: readonly FilterChip<UserEstadoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('usuarios:filters.all') },
            { value: 'activos', label: t('usuarios:filters.active') },
            { value: 'inactivos', label: t('usuarios:filters.inactive') },
        ],
        [t],
    );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback(
        (user: User) => setModal({ type: 'edit', user }),
        [],
    );
    const openDelete = useCallback(
        (user: User) => setModal({ type: 'delete', user }),
        [],
    );
    const openBulkDelete = useCallback(
        () => setModal({ type: 'bulk-delete' }),
        [],
    );

    /** Selección de filas. Tipamos como string porque User usa UUID. */
    const selection = useRowSelection<User, string>({
        rows: paginated.data,
        rowKey: (user) => user.id,
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;
        if (filters.rol) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [
        filters.search,
        filters.sort,
        filters.estado,
        filters.rol,
        filters.per_page,
    ]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.sort) params.set('sort', filters.sort);
        if (filters.direction) params.set('direction', filters.direction);
        if (filters.estado !== DEFAULT_ESTADO) params.set('estado', filters.estado);
        if (filters.rol) params.set('rol', filters.rol);

        const qs = params.toString();
        return qs.length > 0
            ? `${usuarios.export().url}?${qs}`
            : usuarios.export().url;
    }, [
        filters.search,
        filters.sort,
        filters.direction,
        filters.estado,
        filters.rol,
    ]);

    /** Formatea un timestamp ISO a "dd MMM yyyy" usando el locale del navegador. */
    const formatDate = (iso: string | null): string => {
        if (!iso) return t('usuarios:row.no_login');
        return new Date(iso).toLocaleDateString(undefined, {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const columns = useMemo<DataTableColumn<User>[]>(() => {
        const base: DataTableColumn<User>[] = [
            {
                key: 'name',
                header: t('usuarios:columns.user'),
                sortable: true,
                cell: (user) => {
                    const isSelf = currentUserId === user.id;
                    const isSuperadmin = user.roles.some(
                        (r) => r.name === 'superadmin',
                    );
                    return (
                        <div className="flex items-center gap-2">
                            <span
                                className={
                                    isSuperadmin
                                        ? 'flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                        : 'flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary'
                                }
                            >
                                <UserCog
                                    className="size-4"
                                    strokeWidth={2.25}
                                />
                            </span>
                            <div className="flex min-w-0 flex-col leading-tight">
                                <div className="flex items-center gap-1.5">
                                    <span className="truncate text-sm font-semibold text-foreground">
                                        {user.name}
                                    </span>
                                    {isSelf && (
                                        <StatBadge
                                            label={t(
                                                'usuarios:row.current_user_badge',
                                            )}
                                            value=""
                                            variant="info"
                                        />
                                    )}
                                </div>
                                <span className="truncate text-xs text-muted-foreground">
                                    {user.email}
                                </span>
                            </div>
                        </div>
                    );
                },
            },
            {
                key: 'phone',
                header: t('usuarios:columns.phone'),
                cell: (user) =>
                    user.phone ? (
                        <span className="font-mono text-xs text-foreground/80">
                            {user.phone}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground italic">
                            {t('usuarios:row.no_phone')}
                        </span>
                    ),
            },
            {
                key: 'role',
                header: t('usuarios:columns.role'),
                cell: (user) => {
                    const role = user.roles[0];
                    if (!role) {
                        return (
                            <span className="text-xs text-muted-foreground italic">
                                {t('usuarios:row.no_role')}
                            </span>
                        );
                    }
                    const isSuperadmin = role.name === 'superadmin';
                    return (
                        <div className="flex items-center gap-1.5">
                            <ShieldCheck
                                className={
                                    isSuperadmin
                                        ? 'size-3.5 shrink-0 text-amber-600 dark:text-amber-400'
                                        : 'size-3.5 shrink-0 text-primary/70'
                                }
                                strokeWidth={2.25}
                            />
                            <span className="font-mono text-xs">
                                {role.name}
                            </span>
                        </div>
                    );
                },
            },
            {
                key: 'status',
                header: t('usuarios:columns.status'),
                cell: (user) =>
                    user.is_active ? (
                        <StatBadge
                            label={t('usuarios:row.active')}
                            value=""
                            variant="success"
                        />
                    ) : (
                        <StatBadge
                            label={t('usuarios:row.suspended')}
                            value=""
                            variant="warning"
                        />
                    ),
            },
            {
                key: 'last_login_at',
                header: t('usuarios:columns.last_login'),
                sortable: true,
                cell: (user) => (
                    <span
                        className={
                            user.last_login_at
                                ? 'text-xs text-muted-foreground'
                                : 'text-xs text-muted-foreground italic'
                        }
                    >
                        {formatDate(user.last_login_at)}
                    </span>
                ),
            },
            {
                key: 'created_at',
                header: t('usuarios:columns.created_at'),
                sortable: true,
                cell: (user) => (
                    <span className="text-xs text-muted-foreground">
                        {formatDate(user.created_at)}
                    </span>
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: (
                    <span className="md:sr-only">
                        {t('usuarios:columns.acciones')}
                    </span>
                ),
                align: 'right',
                cell: (user: User) => (
                    <div className="flex justify-end">
                        <UserRowActions
                            user={user}
                            currentUserId={currentUserId}
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
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        t,
        showRowActions,
        canUpdate,
        canDelete,
        openEdit,
        openDelete,
        currentUserId,
    ]);

    return (
        <>
            <Head title={t('usuarios:title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('usuarios:title')}
                    description={t('usuarios:description')}
                    stats={[
                        {
                            label: t('usuarios:stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: UsersIcon,
                        },
                        {
                            label: t('usuarios:stats.active'),
                            value: stats.activos,
                            variant: 'success',
                            icon: CheckCircle2,
                        },
                        {
                            label: t('usuarios:stats.inactive'),
                            value: stats.inactivos,
                            variant: 'warning',
                            icon: PauseCircle,
                        },
                        {
                            label: t('usuarios:stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('usuarios:stats.matches'),
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
                            <Can permission="usuarios.create">
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
                                        {t('usuarios:actions.new')}
                                    </span>
                                    <span className="sm:hidden">
                                        {t('usuarios:actions.new_short')}
                                    </span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(user) => user.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    selection={canBulkDelete ? selection : undefined}
                    ariaLiveMessage={t('usuarios:aria.results_count_other', {
                        count: stats.coincidencias,
                    })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('usuarios:search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('usuarios:filter_label')}
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
                                rol: filters.rol ?? undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={
                                activeFiltersCount > 0 ? Activity : MailCheck
                            }
                            title={
                                activeFiltersCount > 0
                                    ? t('usuarios:empty.no_results_title')
                                    : t('usuarios:empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('usuarios:empty.no_results_description')
                                    : t('usuarios:empty.no_records_description')
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
                                        {t('usuarios:actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <UserFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                user={modal.type === 'edit' ? modal.user : null}
                rolesCatalog={roles_catalog}
            />

            <UserDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                user={modal.type === 'delete' ? modal.user : null}
                currentUserId={currentUserId}
            />

            <UserBulkDeleteDialog
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
                        singular: t('usuarios:bulk.selected_singular'),
                        plural: t('usuarios:bulk.selected_plural'),
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
                            {t('usuarios:actions.delete_selected')}
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
        { title: 'Usuarios', href: '/configuracion/usuarios' },
        ]}
    >
        {page}
    </AppLayout>
);
