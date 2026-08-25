import { Head, Link, router } from '@inertiajs/react';
import { Building2, Filter, Plus, PowerOff, ScreenShare } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import {
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
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { ClinicaAsesoradaFormModal } from './components/clinica-asesorada-form-modal';
import { ClinicaAsesoradaRowActions } from './components/clinica-asesorada-row-actions';
import type { ClinicaAsesorada, ClinicasAsesoradasPageProps } from './types';

const ROUTE_URL = '/clinica/clinicas-asesoradas';
const PACIENTES_URL = '/clinica/pacientes';
const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO = 'todas';

export default function Index({
    items: paginated,
    filters,
    stats,
    departamentos,
}: ClinicasAsesoradasPageProps) {
    const { t } = useTranslation(['clinicas-asesoradas', 'common']);
    const { can } = usePermission();
    const canCreate = can('clinicas-asesoradas.create');
    const canUpdate = can('clinicas-asesoradas.update');
    const canDelete = can('clinicas-asesoradas.delete');
    const showRowActions = canUpdate || canDelete;

    const [editing, setEditing] = useState<ClinicaAsesorada | null | undefined>(
        undefined,
    );
    const [deleting, setDeleting] = useState<ClinicaAsesorada | null>(null);

    const { search, setSearch, isLoading, setPerPage, applyFilter } =
        useDataTablePage<{ estado: string }>({
            routeUrl: ROUTE_URL,
            initialFilters: filters,
            only: ['items', 'filters', 'stats'],
            errorMessage: t('toast.load_error'),
            storageKey: 'vetsaas.clinicas-asesoradas.prefs',
            defaults: {
                per_page: DEFAULT_PER_PAGE,
                sort: null,
                direction: null,
            },
        });

    const activeFiltersCount = useMemo(() => {
        let c = 0;
        if (filters.search) {
            c += 1;
        }
        if (filters.estado !== DEFAULT_ESTADO) {
            c += 1;
        }
        return c;
    }, [filters.estado, filters.search]);

    const estadoOptions: readonly FilterChip<string>[] = useMemo(
        () => [
            { value: 'todas', label: t('filters.todas') },
            { value: 'activa', label: t('filters.activa') },
            { value: 'inactiva', label: t('filters.inactiva') },
        ],
        [t],
    );

    const columns = useMemo<DataTableColumn<ClinicaAsesorada>[]>(() => {
        const base: DataTableColumn<ClinicaAsesorada>[] = [
            {
                key: 'nombre',
                header: t('columns.nombre'),
                cell: (row) => (
                    <div>
                        <p className="font-medium">{row.nombre}</p>
                        {row.direccion ? (
                            <p className="text-xs text-muted-foreground">
                                {row.direccion}
                            </p>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'ruc',
                header: t('columns.ruc'),
                cell: (row) => (
                    <span className="font-mono text-sm">{row.ruc || '—'}</span>
                ),
            },
            {
                key: 'ubicacion',
                header: t('columns.ubicacion'),
                cell: (row) => {
                    const parts = [
                        row.distrito,
                        row.provincia,
                        row.departamento,
                    ].filter(Boolean);
                    return parts.length > 0 ? parts.join(', ') : '—';
                },
            },
            {
                key: 'mascotas_count',
                header: t('columns.mascotas'),
                cell: (row) => {
                    const count = row.mascotas_count ?? 0;
                    if (count <= 0) {
                        return (
                            <span className="text-sm text-muted-foreground">
                                0
                            </span>
                        );
                    }

                    return (
                        <Link
                            href={`${PACIENTES_URL}?clinica_asesorada_id=${row.id}`}
                            className="font-medium text-primary underline-offset-4 hover:underline"
                            preserveState={false}
                        >
                            {count}
                        </Link>
                    );
                },
            },
            {
                key: 'estado',
                header: t('columns.estado'),
                cell: (row) => (
                    <StatBadge
                        label={
                            row.activo
                                ? t('estado.activa')
                                : t('estado.inactiva')
                        }
                        value=""
                        variant={row.activo ? 'success' : 'muted'}
                    />
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'actions',
                header: t('columns.acciones'),
                cell: (row) => (
                    <ClinicaAsesoradaRowActions
                        clinica={row}
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                        onEdit={(item) => setEditing(item)}
                        onDelete={(item) => setDeleting(item)}
                    />
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [canDelete, canUpdate, showRowActions, t]);

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
                            label: t('stats.activas'),
                            value: stats.activas,
                            variant: 'success',
                            icon: Building2,
                        },
                        {
                            label: t('stats.inactivas'),
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
                            label: t('stats.coincidencias'),
                            value: stats.coincidencias,
                            variant: 'primary',
                            icon: ScreenShare,
                        },
                    ]}
                    action={
                        <Can permission="clinicas-asesoradas.create">
                            <Button
                                type="button"
                                className="cursor-pointer gap-2"
                                onClick={() => setEditing(null)}
                            >
                                <Plus className="size-4" strokeWidth={2.5} />
                                <span className="hidden sm:inline">
                                    {t('actions.new')}
                                </span>
                                <span className="sm:hidden">
                                    {t('actions.new_short')}
                                </span>
                            </Button>
                        </Can>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(row) => row.id}
                    isLoading={isLoading}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('search_placeholder')}
                        >
                            <FilterChips
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
                                        className="cursor-pointer gap-2"
                                        onClick={() => setEditing(null)}
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

            {(canCreate || canUpdate) && editing !== undefined ? (
                <ClinicaAsesoradaFormModal
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(undefined);
                        }
                    }}
                    clinica={editing}
                    departamentos={departamentos}
                />
            ) : null}

            {deleting ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-xl border border-border bg-background p-5 shadow-lg">
                        <h2 className="text-lg font-semibold">
                            {t('delete.title')}
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('delete.description', { name: deleting.nombre })}
                        </p>
                        <div className="mt-4 flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDeleting(null)}
                            >
                                {t('common:actions.cancel')}
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => {
                                    router.delete(
                                        `${ROUTE_URL}/${deleting.id}`,
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => setDeleting(null),
                                        },
                                    );
                                }}
                            >
                                {t('delete.confirm')}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Clínica', href: '#' },
        { title: 'Clínicas asesoradas', href: ROUTE_URL },
    ],
};
