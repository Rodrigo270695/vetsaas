import { Head } from '@inertiajs/react';
import { Megaphone, Plus } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

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
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

import { AnnouncementDeleteDialog } from './components/announcement-delete-dialog';
import { AnnouncementFormModal } from './components/announcement-form-modal';
import { AnnouncementRowActions } from './components/announcement-row-actions';
import { AnnouncementTypeBadge } from './components/announcement-type-badge';
import type { AnnouncementEntry, AnnouncementFilters, AnnouncementStatusFilter } from './types';

const ROUTE_URL = '/plataforma/bot-ia-announcements';
const DEFAULT_PER_PAGE = 10;
const DEFAULT_STATUS: AnnouncementStatusFilter = 'todos';

type IndexProps = {
    entries: Paginated<AnnouncementEntry>;
    active_announcement_id: string | null;
    filters: AnnouncementFilters;
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; entry: AnnouncementEntry }
    | { type: 'delete'; entry: AnnouncementEntry };

const formatDate = (value: string | null, locale: string): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

function resolveStatus(
    entry: AnnouncementEntry,
    activeAnnouncementId: string | null,
    t: (key: string) => string,
): { label: string; variant: 'success' | 'warning' | 'default' } {
    if (activeAnnouncementId === entry.id) {
        return { label: t('status.live_now'), variant: 'success' };
    }

    if (!entry.is_active) {
        return { label: t('status.inactive'), variant: 'default' };
    }

    if (entry.published_at && new Date(entry.published_at) > new Date()) {
        return { label: t('status.scheduled'), variant: 'warning' };
    }

    if (entry.expires_at && new Date(entry.expires_at) <= new Date()) {
        return { label: t('status.expired'), variant: 'default' };
    }

    return { label: t('status.active'), variant: 'success' };
}

export default function Index({
    entries: paginated,
    active_announcement_id: activeAnnouncementId,
    filters,
}: IndexProps) {
    const { t, i18n } = useTranslation(['bot-ia-announcements', 'common']);
    const locale = i18n.language;
    const { can } = usePermission();
    const canCreate = can('bot-ia-announcements.create');
    const canUpdate = can('bot-ia-announcements.update');
    const canDelete = can('bot-ia-announcements.delete');
    const showRowActions = canUpdate || canDelete;

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((entry: AnnouncementEntry) => setModal({ type: 'edit', entry }), []);
    const openDelete = useCallback((entry: AnnouncementEntry) => setModal({ type: 'delete', entry }), []);

    const { search, setSearch, isLoading, setPerPage, applyFilter } = useDataTablePage<{
        status: AnnouncementStatusFilter;
    }>({
        routeUrl: ROUTE_URL,
        initialFilters: filters,
        errorMessage: t('toast.load_error'),
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const statusOptions: readonly FilterChip<AnnouncementStatusFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('filters.all') },
            { value: 'activo', label: t('filters.active') },
            { value: 'inactivo', label: t('filters.inactive') },
            { value: 'programado', label: t('filters.scheduled') },
        ],
        [t],
    );

    const columns = useMemo<DataTableColumn<AnnouncementEntry>[]>(() => {
        const base: DataTableColumn<AnnouncementEntry>[] = [
            {
                key: 'badge',
                header: t('columns.badge'),
                cell: (entry) => <AnnouncementTypeBadge badge={entry.badge} />,
                className: 'w-28',
            },
            {
                key: 'title',
                header: t('columns.title'),
                cell: (entry) => (
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-foreground">{entry.title}</p>
                        <p className="truncate text-xs text-muted-foreground">{entry.bullet_1}</p>
                    </div>
                ),
            },
            {
                key: 'status',
                header: t('columns.status'),
                cell: (entry) => {
                    const status = resolveStatus(entry, activeAnnouncementId, t);
                    return <StatBadge label={status.label} value="" variant={status.variant} />;
                },
            },
            {
                key: 'published_at',
                header: t('columns.published_at'),
                cell: (entry) => (
                    <span className="text-xs text-muted-foreground">
                        {formatDate(entry.published_at, locale)}
                    </span>
                ),
            },
            {
                key: 'expires_at',
                header: t('columns.expires_at'),
                cell: (entry) => (
                    <span className="text-xs text-muted-foreground">
                        {formatDate(entry.expires_at, locale)}
                    </span>
                ),
            },
            {
                key: 'updated_at',
                header: t('columns.updated_at'),
                cell: (entry) => (
                    <span className="text-xs text-muted-foreground">
                        {formatDate(entry.updated_at, locale)}
                    </span>
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: <span className="sr-only">Acciones</span>,
                align: 'right',
                cell: (entry) => (
                    <div className="flex justify-end">
                        <AnnouncementRowActions
                            entry={entry}
                            activeAnnouncementId={activeAnnouncementId}
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
    }, [t, locale, activeAnnouncementId, showRowActions, canUpdate, canDelete, openEdit, openDelete]);

    const hasSearchOrFilter =
        (filters?.search ?? '') !== '' || (filters?.status ?? DEFAULT_STATUS) !== DEFAULT_STATUS;

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    action={
                        canCreate ? (
                            <Button size="sm" onClick={openCreate} className="gap-1.5">
                                <Plus className="size-4" />
                                <span className="hidden sm:inline">{t('actions.new')}</span>
                                <span className="sm:hidden">{t('actions.new_short')}</span>
                            </Button>
                        ) : undefined
                    }
                />

                <Alert className="border-violet-500/30 bg-violet-500/5">
                    <Megaphone className="size-4 text-violet-600" />
                    <AlertDescription className="text-sm text-muted-foreground">{t('hint')}</AlertDescription>
                </Alert>

                <DataTable
                    columns={columns}
                    data={paginated?.data ?? []}
                    rowKey={(entry) => entry.id}
                    isLoading={isLoading}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('title')}
                                value={filters?.status ?? DEFAULT_STATUS}
                                onChange={(status) => applyFilter({ status })}
                                options={statusOptions}
                            />
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={paginated}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters?.search || undefined,
                                per_page: filters?.per_page,
                                status:
                                    filters?.status !== DEFAULT_STATUS ? filters?.status : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Megaphone}
                            title={
                                hasSearchOrFilter
                                    ? t('empty.no_results_title')
                                    : t('empty.no_records_title')
                            }
                            description={
                                hasSearchOrFilter
                                    ? t('empty.no_results_description')
                                    : t('empty.no_records_description')
                            }
                            action={
                                canCreate && !hasSearchOrFilter ? (
                                    <Button size="sm" onClick={openCreate}>
                                        {t('actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            {canCreate || canUpdate ? (
                <AnnouncementFormModal
                    open={modal.type === 'create' || modal.type === 'edit'}
                    onOpenChange={(open) => !open && closeModal()}
                    entry={modal.type === 'edit' ? modal.entry : null}
                />
            ) : null}

            {canDelete ? (
                <AnnouncementDeleteDialog
                    open={modal.type === 'delete'}
                    onOpenChange={(open) => !open && closeModal()}
                    entry={modal.type === 'delete' ? modal.entry : null}
                />
            ) : null}
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Novedades Asistente IA', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
