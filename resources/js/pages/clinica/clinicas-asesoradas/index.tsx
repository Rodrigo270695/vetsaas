import { Head } from '@inertiajs/react';
import { Building2, Plus, Trash2 } from 'lucide-react';
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
import { router } from '@inertiajs/react';
import { ClinicaAsesoradaFormModal } from './components/clinica-asesorada-form-modal';
import type { ClinicaAsesorada, ClinicasAsesoradasPageProps } from './types';

const ROUTE_URL = '/clinica/clinicas-asesoradas';
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

    const estadoOptions: readonly FilterChip<string>[] = useMemo(
        () => [
            { value: 'todas', label: t('filters.todas') },
            { value: 'activa', label: t('filters.activa') },
            { value: 'inactiva', label: t('filters.inactiva') },
        ],
        [t],
    );

    const columns = useMemo<DataTableColumn<ClinicaAsesorada>[]>(
        () => [
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
            {
                key: 'actions',
                header: '',
                cell: (row) => (
                    <div className="flex justify-end gap-1">
                        {canUpdate ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="default"
                                onClick={() => setEditing(row)}
                            >
                                {t('actions.edit')}
                            </Button>
                        ) : null}
                        {canDelete ? (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="text-destructive"
                                onClick={() => setDeleting(row)}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        ) : null}
                    </div>
                ),
            },
        ],
        [canDelete, canUpdate, t],
    );

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    badges={[
                        {
                            label: t('stats.total'),
                            value: stats.total,
                            variant: 'info',
                        },
                        {
                            label: t('stats.activas'),
                            value: stats.activas,
                            variant: 'success',
                        },
                        {
                            label: t('stats.inactivas'),
                            value: stats.inactivas,
                            variant: 'muted',
                        },
                    ]}
                    actions={
                        canCreate ? (
                            <Button type="button" onClick={() => setEditing(null)}>
                                <Plus className="size-4" />
                                {t('actions.new')}
                            </Button>
                        ) : null
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
                            title={t('empty')}
                            action={
                                canCreate ? (
                                    <Button
                                        type="button"
                                        onClick={() => setEditing(null)}
                                    >
                                        <Plus className="size-4" />
                                        {t('actions.new')}
                                    </Button>
                                ) : null
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
                                    router.delete(`${ROUTE_URL}/${deleting.id}`, {
                                        preserveScroll: true,
                                        onSuccess: () => setDeleting(null),
                                    });
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
