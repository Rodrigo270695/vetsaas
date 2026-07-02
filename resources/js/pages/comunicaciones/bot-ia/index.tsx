import { Head, Link, router } from '@inertiajs/react';
import {
    BookOpen,
    Clock,
    HelpCircle,
    Lock,
    MapPin,
    MessageCircle,
    MessageSquare,
    Plus,
    Scissors,
    ShieldAlert,
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
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';
import { type WhatsAppProps } from '../components/whatsapp-connect-card';
import { AssistantGlobalToggle } from './components/assistant-global-toggle';
import { BotIaUpdateBanner } from './components/bot-ia-update-banner';
import { KnowledgeDeleteDialog } from './components/knowledge-delete-dialog';
import { KnowledgeFormModal } from './components/knowledge-form-modal';
import { KnowledgeRowActions } from './components/knowledge-row-actions';
import { ConversationsPanel } from './components/conversations-panel';
import type {
    AssistantSettings,
    BotIaTab,
    ConversationEntry,
    ConversationFilters,
    ConversationStats,
    KnowledgeEntry,
    KnowledgeFilters,
    KnowledgeSectionFilter,
    KnowledgeStats,
} from './types';
import type { TenantAnnouncement } from '@/pages/plataforma/bot-ia-announcements/types';

const ROUTE_URL = '/comunicaciones/bot-ia';
const DEFAULT_PER_PAGE = 10;
const DEFAULT_SECTION: KnowledgeSectionFilter = 'todos';

type BotIaPayload = {
    activo: boolean;
    precio_mensual: string;
    activado_at: string | null;
};

type ModalState =
    | { type: 'idle' }
    | { type: 'create' }
    | { type: 'edit'; entry: KnowledgeEntry }
    | { type: 'delete'; entry: KnowledgeEntry };

type BotIaAnnouncement = TenantAnnouncement;

type BotIaPageProps = {
    bot_ia: BotIaPayload;
    whatsapp: WhatsAppProps;
    can_manage: boolean;
    announcement?: BotIaAnnouncement | null;
    assistant: AssistantSettings | null;
    tab: BotIaTab;
    knowledge: Paginated<KnowledgeEntry> | null;
    knowledge_stats: KnowledgeStats | null;
    knowledge_filters: KnowledgeFilters | null;
    conversations: Paginated<ConversationEntry> | null;
    conversation_filters: ConversationFilters | null;
    conversation_stats: ConversationStats | null;
};

const EMPTY_KNOWLEDGE: Paginated<KnowledgeEntry> = {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_PER_PAGE,
    total: 0,
    from: null,
    to: null,
    path: ROUTE_URL,
    links: [],
};

const EMPTY_STATS: KnowledgeStats = {
    total: 0,
    faqs: 0,
    horarios: 0,
    politicas: 0,
    servicios: 0,
    contacto: 0,
    general: 0,
};

const EMPTY_CONVERSATIONS: Paginated<ConversationEntry> = {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_PER_PAGE,
    total: 0,
    from: null,
    to: null,
    path: ROUTE_URL,
    links: [],
};

const EMPTY_CONVERSATION_STATS: ConversationStats = {
    total: 0,
    activos: 0,
    pausados: 0,
};

const DEFAULT_CHAT_ESTADO = 'todos' as const;
const DEFAULT_ASSISTANT: AssistantSettings = { respuestas_activas: true };
const DEFAULT_TAB: BotIaTab = 'chats';

function SectionIcon({ section }: { section: string }) {
    switch (section) {
        case 'faq':
            return <HelpCircle className="size-3.5 shrink-0 text-violet-500" strokeWidth={2.5} />;
        case 'horario':
            return <Clock className="size-3.5 shrink-0 text-blue-500" strokeWidth={2.5} />;
        case 'politica':
            return <ShieldAlert className="size-3.5 shrink-0 text-orange-500" strokeWidth={2.5} />;
        case 'servicio':
            return <Scissors className="size-3.5 shrink-0 text-emerald-500" strokeWidth={2.5} />;
        case 'contacto':
            return <MapPin className="size-3.5 shrink-0 text-rose-500" strokeWidth={2.5} />;
        default:
            return <BookOpen className="size-3.5 shrink-0 text-muted-foreground" strokeWidth={2.5} />;
    }
}

const formatDate = (value: string | null, locale: string): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatPrice = (value: string): string => {
    const num = Number(value);
    if (Number.isNaN(num)) return value;
    return num.toFixed(2);
};

export default function Index({
    bot_ia,
    whatsapp,
    can_manage,
    announcement = null,
    assistant = DEFAULT_ASSISTANT,
    tab = DEFAULT_TAB,
    knowledge: paginated = EMPTY_KNOWLEDGE,
    knowledge_stats: _stats = EMPTY_STATS,
    knowledge_filters: filters = {
        search: '',
        section: DEFAULT_SECTION,
        sort: null,
        direction: null,
        per_page: DEFAULT_PER_PAGE,
    },
    conversations: paginatedConversations = EMPTY_CONVERSATIONS,
    conversation_filters: chatFilters = {
        chat_search: '',
        chat_estado: DEFAULT_CHAT_ESTADO,
        chat_per_page: DEFAULT_PER_PAGE,
    },
    conversation_stats: chatStats = EMPTY_CONVERSATION_STATS,
}: BotIaPageProps) {
    const { t, i18n } = useTranslation(['bot-ia', 'comunicaciones']);
    const locale = i18n.language;
    const isActive = bot_ia.activo === true;
    const isWhatsappReady = whatsapp.session?.is_ready === true;

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });
    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openCreate = useCallback(() => setModal({ type: 'create' }), []);
    const openEdit = useCallback((entry: KnowledgeEntry) => setModal({ type: 'edit', entry }), []);
    const openDelete = useCallback((entry: KnowledgeEntry) => setModal({ type: 'delete', entry }), []);

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<{ section: KnowledgeSectionFilter }>({
            routeUrl: ROUTE_URL,
            initialFilters: filters ?? {
                search: '',
                section: DEFAULT_SECTION,
                sort: null,
                direction: null,
                per_page: DEFAULT_PER_PAGE,
            },
            only: ['knowledge', 'knowledge_filters', 'knowledge_stats'],
            errorMessage: t('knowledge.toast.load_error'),
            storageKey: 'vetsaas.comunicaciones.bot-ia.prefs',
            defaults: {
                per_page: DEFAULT_PER_PAGE,
                sort: null,
                direction: null,
            },
        });

    const sectionOptions: readonly FilterChip<KnowledgeSectionFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('knowledge.filters.all') },
            { value: 'faq', label: t('knowledge.filters.faq') },
            { value: 'horario', label: t('knowledge.filters.horario') },
            { value: 'politica', label: t('knowledge.filters.politica') },
            { value: 'servicio', label: t('knowledge.filters.servicio') },
            { value: 'contacto', label: t('knowledge.filters.contacto') },
            { value: 'general', label: t('knowledge.filters.general') },
        ],
        [t],
    );

    const columns = useMemo<DataTableColumn<KnowledgeEntry>[]>(() => {
        const base: DataTableColumn<KnowledgeEntry>[] = [
            {
                key: 'title',
                header: t('knowledge.columns.entry'),
                sortable: true,
                cell: (entry) => (
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-foreground">{entry.title}</p>
                        <p className="truncate font-mono text-xs text-muted-foreground">{entry.slug}</p>
                    </div>
                ),
            },
            {
                key: 'section',
                header: t('knowledge.columns.section'),
                sortable: true,
                cell: (entry) => (
                    <div className="flex items-center gap-1.5">
                        <SectionIcon section={entry.section} />
                        <span className="text-xs font-medium">
                            {t(`knowledge.sections.${entry.section}`)}
                        </span>
                    </div>
                ),
            },
            {
                key: 'content',
                header: t('knowledge.columns.content'),
                cell: (entry) => (
                    <span className="line-clamp-2 max-w-md text-xs text-muted-foreground">
                        {entry.content}
                    </span>
                ),
            },
            {
                key: 'is_active',
                header: t('knowledge.columns.status'),
                cell: (entry) => (
                    <StatBadge
                        label={
                            entry.is_active ? t('knowledge.row.active') : t('knowledge.row.inactive')
                        }
                        value=""
                        variant={entry.is_active ? 'success' : 'warning'}
                    />
                ),
            },
            {
                key: 'updated_at',
                header: t('knowledge.columns.updated_at'),
                sortable: true,
                cell: (entry) => (
                    <span className="text-xs text-muted-foreground">
                        {formatDate(entry.updated_at, locale)}
                    </span>
                ),
            },
        ];

        if (can_manage) {
            base.push({
                key: 'acciones',
                header: <span className="sr-only">Acciones</span>,
                align: 'right',
                cell: (entry) => (
                    <div className="flex justify-end">
                        <KnowledgeRowActions
                            entry={entry}
                            onEdit={openEdit}
                            onDelete={openDelete}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
    }, [t, can_manage, locale, openEdit, openDelete]);

    const hasSearchOrFilter =
        (filters?.search ?? '') !== '' || (filters?.section ?? DEFAULT_SECTION) !== DEFAULT_SECTION;

    const knowledgePreservedQuery = useMemo(
        () => ({
            search: filters?.search || undefined,
            per_page: filters?.per_page,
            section:
                filters?.section !== DEFAULT_SECTION ? filters?.section : undefined,
            sort: filters?.sort ?? undefined,
            direction: filters?.direction ?? undefined,
        }),
        [filters],
    );

    const chatPreservedQuery = useMemo(
        () => ({
            tab,
            chat_search: chatFilters?.chat_search || undefined,
            chat_per_page: chatFilters?.chat_per_page,
            chat_estado:
                chatFilters?.chat_estado !== DEFAULT_CHAT_ESTADO
                    ? chatFilters?.chat_estado
                    : undefined,
        }),
        [chatFilters, tab],
    );

    const setTab = useCallback(
        (nextTab: BotIaTab) => {
            router.get(
                ROUTE_URL,
                {
                    ...knowledgePreservedQuery,
                    ...chatPreservedQuery,
                    tab: nextTab,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        },
        [knowledgePreservedQuery, chatPreservedQuery],
    );

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
                <PageHeader title={t('title')} description={t('description')} />

                {!isActive ? (
                    <>
                        {announcement ? <BotIaUpdateBanner announcement={announcement} /> : null}

                        <Alert className="border-amber-500/30 bg-amber-500/5">
                        <Lock className="size-4 text-amber-600" />
                        <AlertDescription className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="font-medium text-foreground">{t('locked.title')}</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('locked.description')}
                                </p>
                            </div>
                            <Button asChild variant="outline" className="shrink-0">
                                <Link href="/configuracion/suscripcion">{t('locked.cta')}</Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                    </>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 px-3 py-2.5 text-sm">
                            <StatBadge
                                label={t('status.service')}
                                value={`S/. ${formatPrice(bot_ia.precio_mensual)}`}
                                variant="success"
                            />
                            {bot_ia.activado_at ? (
                                <span className="text-xs text-muted-foreground">
                                    {t('active.activated_at', {
                                        date: formatDate(bot_ia.activado_at, locale),
                                    })}
                                </span>
                            ) : null}
                            <span className="hidden h-4 w-px bg-border sm:inline" aria-hidden />
                            <div className="flex items-center gap-2">
                                <MessageCircle className="size-4 text-emerald-600" />
                                <StatBadge
                                    label={t('status.whatsapp')}
                                    value={
                                        isWhatsappReady
                                            ? (whatsapp.session?.phone ?? t('comunicaciones:whatsapp.status_ready'))
                                            : t('comunicaciones:whatsapp.status_pending')
                                    }
                                    variant={isWhatsappReady ? 'success' : 'warning'}
                                />
                            </div>
                            <span className="hidden h-4 w-px bg-border sm:inline" aria-hidden />
                            {assistant ? (
                                <AssistantGlobalToggle
                                    assistant={assistant}
                                    canManage={can_manage}
                                />
                            ) : null}
                            <Button asChild variant="outline" size="sm" className="ml-auto h-8">
                                <Link href="/comunicaciones/cola">{t('whatsapp_manage')}</Link>
                            </Button>
                        </div>

                        {assistant && !assistant.respuestas_activas ? (
                            <Alert className="border-amber-500/30 bg-amber-500/5">
                                <AlertDescription className="text-sm text-muted-foreground">
                                    {t('assistant.off_banner')}
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <Tabs value={tab} onValueChange={(value) => setTab(value as BotIaTab)}>
                            <TabsList className="grid h-9 w-full max-w-md grid-cols-2">
                                <TabsTrigger value="chats" className="cursor-pointer gap-1.5 text-xs sm:text-sm">
                                    <MessageSquare className="size-3.5" />
                                    {t('tabs.chats')}
                                </TabsTrigger>
                                <TabsTrigger
                                    value="conocimiento"
                                    className="cursor-pointer gap-1.5 text-xs sm:text-sm"
                                >
                                    <BookOpen className="size-3.5" />
                                    {t('tabs.knowledge')}
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="chats" className="mt-4 flex flex-col gap-3">
                                <ConversationsPanel
                                    conversations={paginatedConversations}
                                    filters={chatFilters}
                                    stats={chatStats}
                                    canManage={can_manage}
                                    autoRefreshEnabled={
                                        assistant.respuestas_activas && tab === 'chats'
                                    }
                                    knowledgePreservedQuery={{
                                        ...knowledgePreservedQuery,
                                        tab: 'chats',
                                    }}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('conversations.hint_manual')}
                                </p>
                            </TabsContent>

                            <TabsContent value="conocimiento" className="mt-4 flex flex-col gap-3">
                                <div className="flex flex-wrap items-end justify-between gap-3">
                                    <p className="text-sm text-muted-foreground">
                                        {t('knowledge.description')}
                                    </p>
                                    {can_manage ? (
                                        <Button size="sm" onClick={openCreate} className="gap-1.5">
                                            <Plus className="size-4" />
                                            <span className="hidden sm:inline">
                                                {t('knowledge.actions.new')}
                                            </span>
                                            <span className="sm:hidden">
                                                {t('knowledge.actions.new_short')}
                                            </span>
                                        </Button>
                                    ) : null}
                                </div>

                                <DataTable
                                    columns={columns}
                                    data={paginated?.data ?? []}
                                    rowKey={(entry) => entry.id}
                                    sort={sort}
                                    onSortChange={setSort}
                                    isLoading={isLoading}
                                    toolbar={
                                        <DataToolbar
                                            search={search}
                                            onSearchChange={setSearch}
                                            isSearching={isLoading}
                                            placeholder={t('knowledge.search_placeholder')}
                                        >
                                            <FilterChips
                                                ariaLabel={t('knowledge.title')}
                                                value={filters?.section ?? DEFAULT_SECTION}
                                                onChange={(section) => applyFilter({ section })}
                                                options={sectionOptions}
                                            />
                                        </DataToolbar>
                                    }
                                    footer={
                                        <DataPagination
                                            meta={paginated ?? EMPTY_KNOWLEDGE}
                                            onPerPageChange={setPerPage}
                                            preservedQuery={{
                                                ...chatPreservedQuery,
                                                tab: 'conocimiento',
                                                search: filters?.search || undefined,
                                                per_page: filters?.per_page,
                                                section:
                                                    filters?.section !== DEFAULT_SECTION
                                                        ? filters?.section
                                                        : undefined,
                                                sort: filters?.sort ?? undefined,
                                                direction: filters?.direction ?? undefined,
                                            }}
                                        />
                                    }
                                    emptyState={
                                        <EmptyState
                                            icon={BookOpen}
                                            title={
                                                hasSearchOrFilter
                                                    ? t('knowledge.empty.no_results_title')
                                                    : t('knowledge.empty.no_records_title')
                                            }
                                            description={
                                                hasSearchOrFilter
                                                    ? t('knowledge.empty.no_results_description')
                                                    : t('knowledge.empty.no_records_description')
                                            }
                                            action={
                                                can_manage && !hasSearchOrFilter ? (
                                                    <Button size="sm" onClick={openCreate}>
                                                        {t('knowledge.actions.create_first')}
                                                    </Button>
                                                ) : undefined
                                            }
                                        />
                                    }
                                />
                            </TabsContent>
                        </Tabs>
                    </>
                )}
            </div>

            {can_manage && isActive ? (
                <>
                    <KnowledgeFormModal
                        open={modal.type === 'create' || modal.type === 'edit'}
                        onOpenChange={(open) => !open && closeModal()}
                        entry={modal.type === 'edit' ? modal.entry : null}
                    />
                    <KnowledgeDeleteDialog
                        open={modal.type === 'delete'}
                        onOpenChange={(open) => !open && closeModal()}
                        entry={modal.type === 'delete' ? modal.entry : null}
                    />
                </>
            ) : null}
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Comunicaciones' },
            { title: 'Asistente IA', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
