import { Head } from '@inertiajs/react';
import {
    Activity,
    BookOpenCheck,
    Building2,
    Filter,
    Plus,
    Server,
} from 'lucide-react';
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
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';
import { KnowledgeDeleteDialog } from './components/knowledge-delete-dialog';
import { KnowledgeFormModal } from './components/knowledge-form-modal';
import { KnowledgeRowActions } from './components/knowledge-row-actions';
import type {
    KnowledgeEntry,
    KnowledgeFilters,
    KnowledgeScope,
    KnowledgeSection,
    KnowledgeStats,
} from './types';

type Props = {
    entries: Paginated<KnowledgeEntry>;
    filters: KnowledgeFilters;
    stats: KnowledgeStats;
};

type ModalState =
    | { type: 'closed' }
    | { type: 'create' }
    | { type: 'edit'; entry: KnowledgeEntry }
    | { type: 'delete'; entry: KnowledgeEntry };

export default function Index({ entries, filters, stats }: Props) {
    const { t } = useTranslation('in-app-assistant-knowledge');
    const { can } = usePermission();
    const canCreate = can('in-app-assistant-knowledge.create');
    const canUpdate = can('in-app-assistant-knowledge.update');
    const canDelete = can('in-app-assistant-knowledge.delete');
    const [modal, setModal] = useState<ModalState>({ type: 'closed' });
    const closeModal = useCallback(() => setModal({ type: 'closed' }), []);

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{
        scope: KnowledgeScope | 'all';
        section: KnowledgeSection | 'all';
        status: 'active' | 'inactive' | 'all';
    }>({
        routeUrl: '/plataforma/in-app-assistant-knowledge',
        initialFilters: filters,
        only: ['entries', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.plataforma.in-app-assistant-knowledge.prefs',
        defaults: { per_page: 15, sort: 'priority', direction: 'desc' },
    });

    const scopeOptions = useMemo<readonly FilterChip<KnowledgeScope | 'all'>[]>(
        () => [
            { value: 'all', label: t('filters.all_scopes') },
            { value: 'clinic', label: t('scopes.clinic') },
            { value: 'platform', label: t('scopes.platform') },
            { value: 'both', label: t('scopes.both') },
        ],
        [t],
    );
    const sectionOptions = useMemo<
        readonly FilterChip<KnowledgeSection | 'all'>[]
    >(
        () => [
            { value: 'all', label: t('filters.all_sections') },
            ...(['module', 'screen', 'workflow', 'role', 'faq'] as const).map(
                (section) => ({
                    value: section,
                    label: t(`sections.${section}`),
                }),
            ),
        ],
        [t],
    );
    const statusOptions = useMemo<
        readonly FilterChip<'active' | 'inactive' | 'all'>[]
    >(
        () => [
            { value: 'all', label: t('filters.all_statuses') },
            { value: 'active', label: t('status.active') },
            { value: 'inactive', label: t('status.inactive') },
        ],
        [t],
    );

    const activeFilters = [
        filters.search !== '',
        filters.scope !== 'all',
        filters.section !== 'all',
        filters.status !== 'all',
    ].filter(Boolean).length;

    const columns = useMemo<DataTableColumn<KnowledgeEntry>[]>(() => {
        const result: DataTableColumn<KnowledgeEntry>[] = [
            {
                key: 'title',
                header: t('columns.entry'),
                sortable: true,
                cell: (entry) => (
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">
                            {entry.title}
                        </p>
                        <p className="truncate font-mono text-xs text-muted-foreground">
                            {entry.slug}
                        </p>
                    </div>
                ),
            },
            {
                key: 'scope',
                header: t('columns.scope'),
                sortable: true,
                cell: (entry) => (
                    <span className="text-xs">
                        {t(`scopes.${entry.scope}`)}
                    </span>
                ),
            },
            {
                key: 'section',
                header: t('columns.section'),
                sortable: true,
                cell: (entry) => (
                    <span className="text-xs">
                        {t(`sections.${entry.section}`)}
                    </span>
                ),
            },
            {
                key: 'priority',
                header: t('columns.priority'),
                sortable: true,
                align: 'right',
                cell: (entry) => (
                    <span className="font-mono text-xs">{entry.priority}</span>
                ),
            },
            {
                key: 'is_active',
                header: t('columns.status'),
                sortable: true,
                cell: (entry) => (
                    <StatBadge
                        label={t(
                            entry.is_active
                                ? 'status.active'
                                : 'status.inactive',
                        )}
                        value=""
                        variant={entry.is_active ? 'success' : 'warning'}
                    />
                ),
            },
        ];

        if (canUpdate || canDelete) {
            result.push({
                key: 'actions',
                header: <span className="sr-only">{t('columns.actions')}</span>,
                align: 'right',
                cell: (entry) => (
                    <KnowledgeRowActions
                        entry={entry}
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                        onEdit={(selected) =>
                            setModal({ type: 'edit', entry: selected })
                        }
                        onDelete={(selected) =>
                            setModal({ type: 'delete', entry: selected })
                        }
                    />
                ),
            });
        }

        return result;
    }, [canDelete, canUpdate, t]);

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
                            icon: BookOpenCheck,
                            variant: 'info',
                        },
                        {
                            label: t('stats.active'),
                            value: stats.active,
                            icon: Activity,
                            variant: 'success',
                        },
                        {
                            label: t('stats.clinic'),
                            value: stats.clinic,
                            icon: Building2,
                            variant: 'primary',
                        },
                        {
                            label: t('stats.platform'),
                            value: stats.platform,
                            icon: Server,
                            variant: 'warning',
                        },
                        {
                            label: t('stats.filters'),
                            value: activeFilters,
                            icon: Filter,
                            variant: 'info',
                        },
                    ]}
                    action={
                        canCreate ? (
                            <Button
                                onClick={() => setModal({ type: 'create' })}
                            >
                                <Plus className="size-4" />
                                {t('actions.new')}
                            </Button>
                        ) : undefined
                    }
                />

                <DataTable
                    columns={columns}
                    data={entries.data}
                    rowKey={(entry) => entry.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('search_placeholder')}
                        >
                            <div className="flex flex-wrap gap-2">
                                <FilterChips
                                    ariaLabel={t('filters.scope')}
                                    value={filters.scope}
                                    onChange={(scope) => applyFilter({ scope })}
                                    options={scopeOptions}
                                />
                                <FilterChips
                                    ariaLabel={t('filters.section')}
                                    value={filters.section}
                                    onChange={(section) =>
                                        applyFilter({ section })
                                    }
                                    options={sectionOptions}
                                />
                                <FilterChips
                                    ariaLabel={t('filters.status')}
                                    value={filters.status}
                                    onChange={(status) =>
                                        applyFilter({ status })
                                    }
                                    options={statusOptions}
                                />
                            </div>
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={entries}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                scope:
                                    filters.scope !== 'all'
                                        ? filters.scope
                                        : undefined,
                                section:
                                    filters.section !== 'all'
                                        ? filters.section
                                        : undefined,
                                status:
                                    filters.status !== 'all'
                                        ? filters.status
                                        : undefined,
                                sort: filters.sort ?? undefined,
                                direction: filters.direction ?? undefined,
                                per_page: filters.per_page,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={BookOpenCheck}
                            title={t(
                                activeFilters > 0
                                    ? 'empty.no_results'
                                    : 'empty.no_records',
                            )}
                            description={t(
                                activeFilters > 0
                                    ? 'empty.adjust_filters'
                                    : 'empty.create_first',
                            )}
                        />
                    }
                />
            </div>

            <KnowledgeFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                entry={modal.type === 'edit' ? modal.entry : null}
            />
            <KnowledgeDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
                        closeModal();
                    }
                }}
                entry={modal.type === 'delete' ? modal.entry : null}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            {
                title: 'Guías internas',
                href: '/plataforma/in-app-assistant-knowledge',
            },
        ]}
    >
        {page}
    </AppLayout>
);
