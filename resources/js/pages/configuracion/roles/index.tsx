import { Head } from '@inertiajs/react';
import {
    CheckCircle2,
    Download,
    Filter,
    KeyRound,
    Lock,
    Plus,
    ScreenShare,
    ShieldCheck,
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
import roles from '@/routes/configuracion/roles';
import type { Paginated } from '@/types';
import { RoleBulkDeleteDialog } from './components/role-bulk-delete-dialog';
import { RoleDeleteDialog } from './components/role-delete-dialog';
import { RoleFormModal } from './components/role-form-modal';
import { RolePermissionsModal } from './components/role-permissions-modal';
import { RoleRowActions } from './components/role-row-actions';
import type {
    PermissionsCatalog,
    Role,
    RoleFilters,
    RoleStats,
    RoleTipoFilter,
} from './types';

type RolesIndexProps = {
    roles: Paginated<Role>;
    filters: RoleFilters;
    stats: RoleStats;
    /** Catálogo completo de permisos agrupado por módulo. */
    permissions_catalog: PermissionsCatalog;
    /** Solo tenant demo: bloquear create/edit/delete de roles. */
    mutations_locked?: boolean;
};

/**
 * State machine de modales del módulo Roles.
 * Espejo exacto del patrón usado en Sedes para mantener consistencia.
 */
type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; role: Role }
    | { type: 'permissions'; role: Role }
    | { type: 'delete'; role: Role }
    | { type: 'bulk-delete' };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_TIPO: RoleTipoFilter = 'todos';

/**
 * Página principal del módulo Roles & Permisos (Configuración → Roles).
 *
 * Replica el patrón de Sedes:
 *  - PageHeader con título, descripción, badges y botones de acción.
 *  - DataTable con toolbar (búsqueda + filtros segmentados), paginación,
 *    selección multi-fila y aria-live integrado.
 *  - Modales create / edit / delete / bulk-delete bajo state machine.
 *  - Persistencia de `per_page` / `sort` en localStorage por módulo.
 *  - Exportación XLSX respetando filtros vigentes.
 *  - i18n (`roles` + `common` namespaces).
 *
 * Particularidades de roles:
 *  - Los roles del sistema (ej. `superadmin`) NO se pueden editar ni eliminar
 *    (lo bloquea el row-action y el backend).
 *  - La fila renderiza badge "Sistema/Personalizado" y el conteo de permisos.
 */
export default function Index({
    roles: paginated,
    filters,
    stats,
    permissions_catalog,
    mutations_locked = false,
}: RolesIndexProps) {
    const { t } = useTranslation(['roles', 'common']);
    const { can } = usePermission();
    const canCreate = !mutations_locked && can('roles.create');
    const canUpdate = !mutations_locked && can('roles.update');
    const canDelete = !mutations_locked && can('roles.delete');
    const canExport = can('roles.export');
    const canBulkDelete = !mutations_locked && can('roles.bulk-delete');
    const showRowActions = canUpdate || canDelete;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{ tipo: RoleTipoFilter }>({
        routeUrl: roles.index().url,
        initialFilters: filters,
        only: ['roles', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.roles.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    /** Filtro segmentado por tipo (todos / sistema / personalizado). */
    const tipoOptions: readonly FilterChip<RoleTipoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('roles:filters.all') },
            { value: 'sistema', label: t('roles:filters.system') },
            { value: 'personalizado', label: t('roles:filters.custom') },
        ],
        [t],
    );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback(
        (role: Role) => setModal({ type: 'edit', role }),
        [],
    );
    const openManagePermissions = useCallback(
        (role: Role) => setModal({ type: 'permissions', role }),
        [],
    );
    const openDelete = useCallback(
        (role: Role) => setModal({ type: 'delete', role }),
        [],
    );
    const openBulkDelete = useCallback(
        () => setModal({ type: 'bulk-delete' }),
        [],
    );

    /** Selección de filas. Tipamos como number porque Spatie usa BIGINT. */
    const selection = useRowSelection<Role, number>({
        rows: paginated.data,
        rowKey: (role) => role.id,
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.tipo !== DEFAULT_TIPO) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.tipo, filters.per_page]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.sort) params.set('sort', filters.sort);
        if (filters.direction) params.set('direction', filters.direction);
        if (filters.tipo !== DEFAULT_TIPO) params.set('tipo', filters.tipo);

        const qs = params.toString();
        return qs.length > 0
            ? `${roles.export().url}?${qs}`
            : roles.export().url;
    }, [filters.search, filters.sort, filters.direction, filters.tipo]);

    const columns = useMemo<DataTableColumn<Role>[]>(() => {
        const base: DataTableColumn<Role>[] = [
            {
                key: 'name',
                header: t('roles:columns.name'),
                sortable: true,
                cell: (role) => (
                    <div className="flex items-center gap-2">
                        <span
                            className={
                                role.is_system
                                    ? 'flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                    : 'flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary'
                            }
                        >
                            {role.is_system ? (
                                <Lock className="size-3.5" strokeWidth={2.5} />
                            ) : (
                                <ShieldCheck
                                    className="size-3.5"
                                    strokeWidth={2.5}
                                />
                            )}
                        </span>
                        <div className="flex flex-col leading-tight">
                            <span className="font-mono text-xs font-semibold tracking-wider text-foreground">
                                {role.name}
                            </span>
                            <span className="text-[0.65rem] text-muted-foreground">
                                {role.guard_name}
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'description',
                header: t('roles:columns.description'),
                sortable: true,
                cell: (role) =>
                    role.description ? (
                        <span className="line-clamp-2 text-sm text-muted-foreground">
                            {role.description}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground italic">
                            {t('roles:row.no_description')}
                        </span>
                    ),
            },
            {
                key: 'tipo',
                header: t('roles:columns.type'),
                cell: (role) =>
                    role.is_system ? (
                        <StatBadge
                            label={t('roles:row.type_system')}
                            value=""
                            variant="warning"
                        />
                    ) : (
                        <StatBadge
                            label={t('roles:row.type_custom')}
                            value=""
                            variant="success"
                        />
                    ),
            },
            {
                key: 'permissions_count',
                header: t('roles:columns.permissions_count'),
                sortable: true,
                cell: (role) => (
                    <div className="flex items-center gap-1.5">
                        <KeyRound
                            className="size-3.5 shrink-0 text-primary/70"
                            strokeWidth={2.25}
                        />
                        <span className="text-sm tabular-nums">
                            {role.permissions_count}
                        </span>
                    </div>
                ),
            },
            {
                key: 'created_at',
                header: t('roles:columns.created_at'),
                sortable: true,
                cell: (role) => (
                    <span className="text-xs text-muted-foreground">
                        {new Date(role.created_at).toLocaleDateString(
                            undefined,
                            {
                                day: '2-digit',
                                month: 'short',
                                year: 'numeric',
                            },
                        )}
                    </span>
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: (
                    <span className="md:sr-only">
                        {t('roles:columns.acciones')}
                    </span>
                ),
                align: 'right',
                cell: (role: Role) => (
                    <div className="flex justify-end">
                        <RoleRowActions
                            role={role}
                            onEdit={openEdit}
                            onDelete={openDelete}
                            onManagePermissions={openManagePermissions}
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
        showRowActions,
        canUpdate,
        canDelete,
        openEdit,
        openDelete,
        openManagePermissions,
    ]);

    return (
        <>
            <Head title={t('roles:title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('roles:title')}
                    description={
                        mutations_locked
                            ? t('roles:demo_locked.description')
                            : t('roles:description')
                    }
                    stats={[
                        {
                            label: t('roles:stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: ShieldCheck,
                        },
                        {
                            label: t('roles:stats.system'),
                            value: stats.sistema,
                            variant: 'warning',
                            icon: Lock,
                        },
                        {
                            label: t('roles:stats.custom'),
                            value: stats.personalizados,
                            variant: 'success',
                            icon: CheckCircle2,
                        },
                        {
                            label: t('roles:stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('roles:stats.matches'),
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
                            <Can permission="roles.create">
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
                                        {t('roles:actions.new')}
                                    </span>
                                    <span className="sm:hidden">
                                        {t('roles:actions.new_short')}
                                    </span>
                                </Button>
                            </Can>
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(role) => role.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    selection={canBulkDelete ? selection : undefined}
                    ariaLiveMessage={t('roles:aria.results_count_other', {
                        count: stats.coincidencias,
                    })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('roles:search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('roles:filter_label')}
                                value={filters.tipo}
                                onChange={(tipo) => applyFilter({ tipo })}
                                options={tipoOptions}
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
                                tipo:
                                    filters.tipo !== DEFAULT_TIPO
                                        ? filters.tipo
                                        : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={ShieldCheck}
                            title={
                                activeFiltersCount > 0
                                    ? t('roles:empty.no_results_title')
                                    : t('roles:empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('roles:empty.no_results_description')
                                    : t('roles:empty.no_records_description')
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
                                        {t('roles:actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <RoleFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                role={modal.type === 'edit' ? modal.role : null}
            />

            <RolePermissionsModal
                open={modal.type === 'permissions'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                role={modal.type === 'permissions' ? modal.role : null}
                catalog={permissions_catalog}
            />

            <RoleDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                role={modal.type === 'delete' ? modal.role : null}
            />

            <RoleBulkDeleteDialog
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
                        singular: t('roles:bulk.selected_singular'),
                        plural: t('roles:bulk.selected_plural'),
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
                            {t('roles:actions.delete_selected')}
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
            { title: 'Roles', href: '/configuracion/roles' },
        ]}
    >
        {page}
    </AppLayout>
);
