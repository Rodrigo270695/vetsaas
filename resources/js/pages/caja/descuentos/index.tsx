import { Head } from '@inertiajs/react';
import { Percent, Plus, Tag } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCallback, useMemo, useState } from 'react';
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
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';
import { PromotionDeleteDialog } from './components/promotion-delete-dialog';
import { PromotionFormModal } from './components/promotion-form-modal';
import { PromotionRowActions } from './components/promotion-row-actions';
import type {
    GroomingServiceOption,
    Promotion,
    PromotionEstadoFilter,
    PromotionFilters,
    PromotionMeta,
    PromotionStats,
} from './types';

type Props = {
    promotions: Paginated<Promotion>;
    filters: PromotionFilters;
    stats: PromotionStats;
    groomingServiceOptions: GroomingServiceOption[];
    meta: PromotionMeta;
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; promotion: Promotion }
    | { type: 'delete'; promotion: Promotion };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: PromotionEstadoFilter = 'todas';

function formatDiscount(row: Promotion, t: (key: string) => string): string {
    const value = Number(row.value);
    if (Number.isNaN(value)) {
        return row.value;
    }

    if (row.discount_type === 'pct_line' || row.discount_type === 'pct_sale') {
        return `${value.toFixed(0)}%`;
    }

    return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Index({
    promotions: paginated,
    filters,
    stats,
    groomingServiceOptions,
    meta,
}: Props) {
    const { t } = useTranslation(['descuentos-promociones', 'common']);
    const { can } = usePermission();
    const canCreate = can('descuentos.create');
    const canUpdate = can('descuentos.update');
    const canDelete = can('descuentos.delete');

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<{ estado: PromotionEstadoFilter }>({
            routeUrl: '/caja/descuentos',
            initialFilters: filters,
            only: ['promotions', 'filters', 'stats', 'groomingServiceOptions', 'meta'],
            errorMessage: t('toast.load_error'),
            storageKey: 'vetsaas.caja.descuentos.prefs',
            defaults: {
                per_page: DEFAULT_PER_PAGE,
                sort: null,
                direction: null,
            },
        });

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);

    const estadoOptions: readonly FilterChip<PromotionEstadoFilter>[] = useMemo(
        () => [
            { value: 'todas', label: t('common:filters.all') },
            { value: 'activa', label: t('common:filters.active') },
            { value: 'inactiva', label: t('common:filters.inactive') },
        ],
        [t],
    );

    const columns = useMemo<DataTableColumn<Promotion>[]>(() => {
        const base: DataTableColumn<Promotion>[] = [
            {
                key: 'name',
                header: t('columns.name'),
                sortable: true,
                cell: (p) => (
                    <div className="flex flex-col">
                        <span className="font-medium text-foreground">{p.name}</span>
                        {p.description ? (
                            <span className="line-clamp-1 text-[0.7rem] text-muted-foreground">{p.description}</span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'code',
                header: t('columns.code'),
                cell: (p) =>
                    p.code ? (
                        <span className="font-mono text-xs">{p.code}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'discount_type',
                header: t('columns.discount'),
                cell: (p) => (
                    <span className="tabular-nums text-sm font-medium">{formatDiscount(p, t)}</span>
                ),
            },
            {
                key: 'scope',
                header: t('columns.scope'),
                cell: (p) => <span className="text-sm">{t(`scopes.${p.scope}`)}</span>,
            },
            {
                key: 'condition_type',
                header: t('columns.condition'),
                cell: (p) => (
                    <span className="text-xs text-muted-foreground">{t(`conditions.${p.condition_type}`)}</span>
                ),
            },
            {
                key: 'priority',
                header: t('columns.priority'),
                sortable: true,
                cell: (p) => <span className="tabular-nums text-sm">{p.priority}</span>,
                className: 'w-20',
            },
            {
                key: 'uses_count',
                header: t('columns.uses'),
                cell: (p) => (
                    <span className="tabular-nums text-xs text-muted-foreground">
                        {p.uses_count}
                        {p.max_uses != null ? ` / ${p.max_uses}` : ` · ${t('row.uses_unlimited')}`}
                    </span>
                ),
            },
            {
                key: 'is_active',
                header: t('columns.status'),
                sortable: true,
                cell: (p) =>
                    p.is_active ? (
                        <StatBadge label={t('common:filters.active')} value="" variant="success" />
                    ) : (
                        <StatBadge label={t('common:filters.inactive')} value="" variant="muted" />
                    ),
            },
        ];

        if (canUpdate || canDelete) {
            base.push({
                key: 'acciones',
                header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                align: 'right',
                cell: (p) => (
                    <div className="flex justify-end">
                        <PromotionRowActions
                            promotion={p}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                            onEdit={(row) => setModal({ type: 'edit', promotion: row })}
                            onDelete={(row) => setModal({ type: 'delete', promotion: row })}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [t, canUpdate, canDelete]);

    const hasRecords = stats.total > 0;
    const showEmpty = paginated.total === 0 && !filters.search && filters.estado === DEFAULT_ESTADO;

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        { label: t('stats.total'), value: stats.total, variant: 'info', icon: Tag },
                        { label: t('stats.active'), value: stats.activas, variant: 'success', icon: Percent },
                        { label: t('stats.inactive'), value: stats.inactivas, variant: 'muted', icon: Tag },
                        { label: t('stats.matches'), value: stats.coincidencias, variant: 'default', icon: Tag },
                    ]}
                    actions={
                        <Can permission="descuentos.create">
                            <Button className="gap-2" onClick={() => setModal({ type: 'create' })}>
                                <Plus className="size-4" aria-hidden />
                                <span className="hidden sm:inline">{t('actions.new')}</span>
                                <span className="sm:hidden">{t('actions.new_short')}</span>
                            </Button>
                        </Can>
                    }
                />

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder={t('search_placeholder')}
                    isLoading={isLoading}
                    filtersSlot={
                        <FilterChips
                            label={t('filter_estado')}
                            value={filters.estado}
                            options={estadoOptions}
                            onChange={(estado) => applyFilter('estado', estado)}
                        />
                    }
                />

                {showEmpty && !hasRecords ? (
                    <EmptyState
                        icon={Percent}
                        title={t('empty.no_records_title')}
                        description={t('empty.no_records_description')}
                        action={
                            canCreate ? (
                                <Button onClick={() => setModal({ type: 'create' })}>{t('actions.create_first')}</Button>
                            ) : undefined
                        }
                    />
                ) : paginated.total === 0 ? (
                    <EmptyState
                        icon={Percent}
                        title={t('empty.no_results_title')}
                        description={t('empty.no_results_description')}
                    />
                ) : (
                    <>
                        <DataTable
                            columns={columns}
                            rows={paginated.data}
                            rowKey={(p) => p.id}
                            sort={sort}
                            onSort={setSort}
                            isLoading={isLoading}
                        />
                        <DataPagination paginated={paginated} onPerPageChange={setPerPage} />
                    </>
                )}
            </div>

            <PromotionFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                promotion={modal.type === 'edit' ? modal.promotion : null}
                meta={meta}
                groomingServiceOptions={groomingServiceOptions}
            />

            <PromotionDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                promotion={modal.type === 'delete' ? modal.promotion : null}
            />
        </>
    );
}

Index.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
