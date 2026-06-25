import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Activity,
    Bot,
    BookOpen,
    Filter,
    HelpCircle,
    MessageSquareQuote,
    Plus,
    RefreshCw,
    ShieldAlert,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { toastManager } from '@/lib/toast';
import { useRowSelection } from '@/hooks/use-row-selection';
import AppLayout from '@/layouts/app-layout';
import salesbotKnowledge from '@/routes/plataforma/salesbot-knowledge';
import type { Paginated } from '@/types';
import { KnowledgeDeleteDialog } from './components/knowledge-delete-dialog';
import { KnowledgeFormModal } from './components/knowledge-form-modal';
import { KnowledgeRowActions } from './components/knowledge-row-actions';
import type {
    KnowledgeEntry,
    KnowledgeFilters,
    KnowledgeSectionFilter,
    KnowledgeStats,
} from './types';

type KnowledgeIndexProps = {
    entries: Paginated<KnowledgeEntry>;
    filters: KnowledgeFilters;
    stats: KnowledgeStats;
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; entry: KnowledgeEntry }
    | { type: 'delete'; entry: KnowledgeEntry };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_SECTION: KnowledgeSectionFilter = 'todos';

/** Icono por sección para las celdas de la tabla. */
function SectionIcon({ section }: { section: string }) {
    switch (section) {
        case 'plan':
            return <Sparkles className="size-3.5 shrink-0 text-amber-500" strokeWidth={2.5} />;
        case 'modulo':
            return <BookOpen className="size-3.5 shrink-0 text-blue-500" strokeWidth={2.5} />;
        case 'faq':
            return <HelpCircle className="size-3.5 shrink-0 text-violet-500" strokeWidth={2.5} />;
        case 'objecion':
            return <MessageSquareQuote className="size-3.5 shrink-0 text-orange-500" strokeWidth={2.5} />;
        default:
            return <ShieldAlert className="size-3.5 shrink-0 text-muted-foreground" strokeWidth={2.5} />;
    }
}

export default function Index({
    entries: paginated,
    filters,
    stats,
}: KnowledgeIndexProps) {
    const { t } = useTranslation(['salesbot-knowledge', 'common']);
    const { can } = usePermission();
    const canCreate = can('salesbot-knowledge.create');
    const canUpdate = can('salesbot-knowledge.update');
    const canDelete = can('salesbot-knowledge.delete');
    const showRowActions = canUpdate || canDelete;

    const [flushingCache, setFlushingCache] = useState(false);

    const handleFlushCache = useCallback(() => {
        setFlushingCache(true);
        axios
            .post(salesbotKnowledge.flushCache.url(), { product: 'vetsaas' })
            .then(() => {
                toastManager.success({
                    title: t('salesbot-knowledge:toast.cache_flushed'),
                });
            })
            .catch(() => {
                toastManager.error({
                    title: t('salesbot-knowledge:toast.cache_error'),
                });
            })
            .finally(() => {
                setFlushingCache(false);
            });
    }, [t]);

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{ section: KnowledgeSectionFilter }>({
        routeUrl: salesbotKnowledge.index().url,
        initialFilters: filters,
        only: ['entries', 'filters', 'stats'],
        errorMessage: t('salesbot-knowledge:toast.load_error'),
        storageKey: 'vetsaas.plataforma.salesbot-knowledge.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const sectionOptions: readonly FilterChip<KnowledgeSectionFilter>[] = useMemo(
        () => [
            { value: 'todos',     label: t('salesbot-knowledge:filters.all') },
            { value: 'plan',      label: t('salesbot-knowledge:filters.plan') },
            { value: 'modulo',    label: t('salesbot-knowledge:filters.modulo') },
            { value: 'faq',       label: t('salesbot-knowledge:filters.faq') },
            { value: 'objecion',  label: t('salesbot-knowledge:filters.objecion') },
            { value: 'general',   label: t('salesbot-knowledge:filters.general') },
        ],
        [t],
    );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit   = useCallback((entry: KnowledgeEntry) => setModal({ type: 'edit', entry }), []);
    const openDelete = useCallback((entry: KnowledgeEntry) => setModal({ type: 'delete', entry }), []);

    const selection = useRowSelection<KnowledgeEntry, number>({
        rows: paginated.data,
        rowKey: (entry) => entry.id,
    });

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.section !== DEFAULT_SECTION) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [filters.search, filters.sort, filters.section, filters.per_page]);

    const formatDate = (iso: string): string =>
        new Date(iso).toLocaleDateString(undefined, {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });

    const columns = useMemo<DataTableColumn<KnowledgeEntry>[]>(() => {
        const base: DataTableColumn<KnowledgeEntry>[] = [
            {
                key: 'title',
                header: t('salesbot-knowledge:columns.entry'),
                sortable: true,
                cell: (entry) => (
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Bot className="size-4" strokeWidth={2.25} />
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                            <span className="truncate text-sm font-semibold text-foreground">
                                {entry.title}
                            </span>
                            <span className="truncate font-mono text-xs text-muted-foreground">
                                {entry.slug}
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'section',
                header: t('salesbot-knowledge:columns.section'),
                sortable: true,
                cell: (entry) => (
                    <div className="flex items-center gap-1.5">
                        <SectionIcon section={entry.section} />
                        <span className="text-xs font-medium">
                            {t(`salesbot-knowledge:sections.${entry.section}`)}
                        </span>
                    </div>
                ),
            },
            {
                key: 'content',
                header: t('salesbot-knowledge:columns.content'),
                cell: (entry) => (
                    <span className="line-clamp-2 max-w-xs text-xs text-muted-foreground">
                        {entry.content}
                    </span>
                ),
            },
            {
                key: 'is_active',
                header: t('salesbot-knowledge:columns.status'),
                cell: (entry) =>
                    entry.is_active ? (
                        <StatBadge label={t('salesbot-knowledge:row.active')} value="" variant="success" />
                    ) : (
                        <StatBadge label={t('salesbot-knowledge:row.inactive')} value="" variant="warning" />
                    ),
            },
            {
                key: 'updated_at',
                header: t('salesbot-knowledge:columns.updated_at'),
                sortable: true,
                cell: (entry) => (
                    <span className="text-xs text-muted-foreground">
                        {formatDate(entry.updated_at)}
                    </span>
                ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: (
                    <span className="md:sr-only">
                        {t('salesbot-knowledge:columns.acciones')}
                    </span>
                ),
                align: 'right',
                cell: (entry) => (
                    <div className="flex justify-end">
                        <KnowledgeRowActions
                            entry={entry}
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
    }, [t, showRowActions, canUpdate, canDelete, openEdit, openDelete]);

    return (
        <>
            <Head title={t('salesbot-knowledge:title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('salesbot-knowledge:title')}
                    description={t('salesbot-knowledge:description')}
                    stats={[
                        {
                            label: t('salesbot-knowledge:stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: Bot,
                        },
                        {
                            label: t('salesbot-knowledge:stats.planes'),
                            value: stats.planes,
                            variant: 'primary',
                            icon: Sparkles,
                        },
                        {
                            label: t('salesbot-knowledge:stats.modulos'),
                            value: stats.modulos,
                            variant: 'success',
                            icon: BookOpen,
                        },
                        {
                            label: t('salesbot-knowledge:stats.faqs'),
                            value: stats.faqs,
                            variant: 'info',
                            icon: HelpCircle,
                        },
                        {
                            label: t('salesbot-knowledge:stats.objeciones'),
                            value: stats.objeciones,
                            variant: 'warning',
                            icon: MessageSquareQuote,
                        },
                        {
                            label: t('salesbot-knowledge:stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('salesbot-knowledge:stats.matches'),
                            value: stats.coincidencias,
                            variant: 'primary',
                            icon: Activity,
                        },
                    ]}
                    action={
                        <div className="flex flex-row items-center gap-2">
                            {canUpdate && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleFlushCache}
                                    disabled={flushingCache}
                                    className="cursor-pointer gap-2"
                                >
                                    <RefreshCw
                                        className={`size-4 ${flushingCache ? 'animate-spin' : ''}`}
                                        strokeWidth={2.5}
                                    />
                                    <span className="hidden sm:inline">
                                        {t('salesbot-knowledge:actions.flush_cache')}
                                    </span>
                                </Button>
                            )}
                            {canCreate && (
                                <Button
                                    type="button"
                                    onClick={openCreate}
                                    className="cursor-pointer gap-2"
                                >
                                    <Plus className="size-4" strokeWidth={2.5} />
                                    <span className="hidden sm:inline">
                                        {t('salesbot-knowledge:actions.new')}
                                    </span>
                                    <span className="sm:hidden">
                                        {t('salesbot-knowledge:actions.new_short')}
                                    </span>
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(entry) => entry.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    selection={canDelete ? selection : undefined}
                    ariaLiveMessage={t('salesbot-knowledge:aria.results_count_other', {
                        count: stats.coincidencias,
                    })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('salesbot-knowledge:search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('salesbot-knowledge:filter_label')}
                                value={filters.section}
                                onChange={(section) => applyFilter({ section })}
                                options={sectionOptions}
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
                                section:
                                    filters.section !== DEFAULT_SECTION
                                        ? filters.section
                                        : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={activeFiltersCount > 0 ? Activity : Bot}
                            title={
                                activeFiltersCount > 0
                                    ? t('salesbot-knowledge:empty.no_results_title')
                                    : t('salesbot-knowledge:empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('salesbot-knowledge:empty.no_results_description')
                                    : t('salesbot-knowledge:empty.no_records_description')
                            }
                            action={
                                activeFiltersCount === 0 && canCreate ? (
                                    <Button
                                        type="button"
                                        onClick={openCreate}
                                        className="cursor-pointer gap-2"
                                    >
                                        <Plus className="size-4" strokeWidth={2.5} />
                                        {t('salesbot-knowledge:actions.create_first')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <KnowledgeFormModal
                open={modal.type === 'create' || modal.type === 'edit'}
                onOpenChange={(open) => { if (!open) closeModal(); }}
                entry={modal.type === 'edit' ? modal.entry : null}
            />

            <KnowledgeDeleteDialog
                open={modal.type === 'delete'}
                onOpenChange={(open) => { if (!open) closeModal(); }}
                entry={modal.type === 'delete' ? modal.entry : null}
            />

            {canDelete && (
                <BulkActionBar
                    count={selection.count}
                    labels={{
                        singular: '1 entrada seleccionada',
                        plural: `${selection.count} entradas seleccionadas`,
                    }}
                    onClear={selection.clear}
                >
                    <BulkAction
                        type="button"
                        variant="destructive"
                        size="sm"
                        onClick={() => {/* bulk delete futuro */}}
                        className="cursor-pointer gap-1.5"
                    >
                        <Trash2 className="size-4" strokeWidth={2.5} />
                        <span className="hidden sm:inline">Eliminar seleccionadas</span>
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
            { title: 'Bot de ventas', href: '/plataforma/salesbot-knowledge' },
        ]}
    >
        {page}
    </AppLayout>
);
