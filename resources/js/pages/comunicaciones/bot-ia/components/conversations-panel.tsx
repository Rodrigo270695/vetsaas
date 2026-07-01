import { router } from '@inertiajs/react';
import { Eye, MessageSquare, Pause, Play, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { formatWhatsAppPhone } from '@/lib/format-whatsapp-phone';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import type { Paginated } from '@/types';
import type {
    ConversationEntry,
    ConversationEstadoFilter,
    ConversationFilters,
    ConversationStats,
} from '../types';
import { ConversationChatDialog } from './conversation-chat-dialog';

const ROUTE_URL = '/comunicaciones/bot-ia';

type Props = {
    conversations: Paginated<ConversationEntry>;
    filters: ConversationFilters;
    stats: ConversationStats;
    canManage: boolean;
    autoRefreshEnabled?: boolean;
    knowledgePreservedQuery?: Record<string, string | number | undefined | null>;
};

const DEFAULT_ESTADO: ConversationEstadoFilter = 'todos';

export function ConversationsPanel({
    conversations,
    filters,
    stats,
    canManage,
    autoRefreshEnabled = true,
    knowledgePreservedQuery = {},
}: Props) {
    const { t } = useTranslation(['bot-ia', 'common']);
    const [chatSearch, setChatSearch] = useState(filters.chat_search ?? '');
    const [isLoading, setIsLoading] = useState(false);
    const [viewConversation, setViewConversation] = useState<ConversationEntry | null>(null);
    const isFirstRender = useRef(true);

    const { secondsSince, isRefreshing, refresh } = useAutoRefresh({
        only: ['conversations', 'conversation_filters', 'conversation_stats', 'assistant'],
        enabled: autoRefreshEnabled,
    });

    const fetchChats = useCallback(
        (overrides: Partial<ConversationFilters & { chat_page?: number }>) => {
            setIsLoading(true);
            router.get(
                ROUTE_URL,
                {
                    ...knowledgePreservedQuery,
                    tab: knowledgePreservedQuery.tab ?? 'chats',
                    chat_search: overrides.chat_search ?? filters.chat_search,
                    chat_estado: overrides.chat_estado ?? filters.chat_estado,
                    chat_per_page: overrides.chat_per_page ?? filters.chat_per_page,
                    chat_page: overrides.chat_page ?? conversations.current_page,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: ['conversations', 'conversation_filters', 'conversation_stats'],
                    onFinish: () => setIsLoading(false),
                },
            );
        },
        [filters, conversations.current_page, knowledgePreservedQuery],
    );

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        if (chatSearch === (filters.chat_search ?? '')) {
            return;
        }

        const timer = window.setTimeout(() => {
            fetchChats({ chat_search: chatSearch.trim(), chat_page: 1 });
        }, 400);

        return () => window.clearTimeout(timer);
    }, [chatSearch, filters.chat_search, fetchChats]);

    useEffect(() => {
        if (!viewConversation) {
            return;
        }

        const updated = conversations.data.find((row) => row.id === viewConversation.id);
        if (updated) {
            setViewConversation(updated);
        }
    }, [conversations.data, viewConversation?.id]);

    const estadoOptions: readonly FilterChip<ConversationEstadoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: `${t('conversations.filters.all')} (${stats.total})` },
            { value: 'activo', label: `${t('conversations.filters.active')} (${stats.activos})` },
            { value: 'pausado', label: `${t('conversations.filters.paused')} (${stats.pausados})` },
        ],
        [t, stats],
    );

    const pause = (conversation: ConversationEntry) => {
        router.post(
            `${ROUTE_URL}/conversaciones/${conversation.id}/pause`,
            {},
            { preserveScroll: true },
        );
    };

    const resume = (conversation: ConversationEntry) => {
        router.post(
            `${ROUTE_URL}/conversaciones/${conversation.id}/resume`,
            {},
            { preserveScroll: true },
        );
    };

    const formatRelative = (iso: string | null): string => {
        if (!iso) return '—';
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) return '—';
        const diffMs = Date.now() - date.getTime();
        const mins = Math.floor(diffMs / 60000);
        if (mins < 1) return t('conversations.time_now');
        if (mins < 60) return t('conversations.time_minutes', { count: mins });
        const hours = Math.floor(mins / 60);
        if (hours < 24) return t('conversations.time_hours', { count: hours });
        const days = Math.floor(hours / 24);
        return t('conversations.time_days', { count: days });
    };

    const columns = useMemo<DataTableColumn<ConversationEntry>[]>(() => {
        const base: DataTableColumn<ConversationEntry>[] = [
            {
                key: 'contact',
                header: t('conversations.columns.contact'),
                cell: (row) => {
                    const phoneLabel = formatWhatsAppPhone(row.phone);
                    const name = row.client_name?.trim();

                    return (
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">
                                {name || phoneLabel}
                            </p>
                            {name ? (
                                <p className="truncate font-mono text-xs text-muted-foreground">
                                    {phoneLabel}
                                </p>
                            ) : null}
                        </div>
                    );
                },
            },
            {
                key: 'preview',
                header: t('conversations.columns.last_message'),
                cell: (row) => (
                    <span className="line-clamp-2 max-w-md text-xs text-muted-foreground">
                        {row.last_message_preview ?? '—'}
                    </span>
                ),
            },
            {
                key: 'status',
                header: t('conversations.columns.status'),
                cell: (row) => (
                    <StatBadge
                        label={
                            row.bot_active
                                ? t('conversations.status_active')
                                : row.bot_paused_manually
                                  ? t('conversations.status_paused_manual')
                                  : t('conversations.status_paused')
                        }
                        value=""
                        variant={row.bot_active ? 'success' : 'warning'}
                    />
                ),
            },
            {
                key: 'last_message_at',
                header: t('conversations.columns.activity'),
                cell: (row) => (
                    <span className="text-xs text-muted-foreground">
                        {formatRelative(row.last_message_at)}
                    </span>
                ),
            },
        ];

        base.push({
            key: 'actions',
            header: <span className="sr-only">Acciones</span>,
            align: 'right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => setViewConversation(row)}
                        title={t('conversations.actions.view')}
                    >
                        <Eye className="size-4" />
                    </Button>
                    {canManage ? (
                        row.bot_active ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-8 gap-1"
                                onClick={() => pause(row)}
                            >
                                <Pause className="size-3.5" />
                                <span className="hidden sm:inline">
                                    {t('conversations.actions.pause')}
                                </span>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-8 gap-1"
                                onClick={() => resume(row)}
                            >
                                <Play className="size-3.5" />
                                <span className="hidden sm:inline">
                                    {t('conversations.actions.resume')}
                                </span>
                            </Button>
                        )
                    ) : null}
                </div>
            ),
        });

        return base;
    }, [t, canManage]);

    return (
        <>
            <section className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-muted-foreground">
                        {t('conversations.description')}
                        <span className="hidden sm:inline">
                            {' '}
                            · {t('sync.interval_hint')}
                        </span>
                    </p>
                    <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        {autoRefreshEnabled ? (
                            <>
                                <span
                                    className={`inline-block size-2 rounded-full ${
                                        isRefreshing ? 'animate-ping bg-amber-400' : 'bg-emerald-400'
                                    }`}
                                />
                                {isRefreshing
                                    ? t('sync.updating')
                                    : t('sync.updated_ago', { seconds: secondsSince })}
                                <button
                                    type="button"
                                    onClick={refresh}
                                    disabled={isRefreshing}
                                    className="ml-1 cursor-pointer rounded p-0.5 hover:text-foreground disabled:opacity-50"
                                    title={t('sync.refresh_now')}
                                >
                                    <RefreshCw
                                        className={`size-3 ${isRefreshing ? 'animate-spin' : ''}`}
                                    />
                                </button>
                            </>
                        ) : (
                            t('sync.paused')
                        )}
                    </span>
                </div>

                <DataTable
                    columns={columns}
                    data={conversations.data}
                    rowKey={(row) => row.id}
                    isLoading={isLoading || isRefreshing}
                    toolbar={
                        <DataToolbar
                            search={chatSearch}
                            onSearchChange={setChatSearch}
                            isSearching={isLoading}
                            placeholder={t('conversations.search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('conversations.title')}
                                value={filters.chat_estado ?? DEFAULT_ESTADO}
                                onChange={(chat_estado) =>
                                    fetchChats({ chat_estado, chat_page: 1 })
                                }
                                options={estadoOptions}
                            />
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={conversations}
                            pageQueryKey="chat_page"
                            onPerPageChange={(chat_per_page) =>
                                fetchChats({ chat_per_page, chat_page: 1 })
                            }
                            preservedQuery={{
                                ...knowledgePreservedQuery,
                                chat_search: filters.chat_search || undefined,
                                chat_per_page: filters.chat_per_page,
                                chat_estado:
                                    filters.chat_estado !== DEFAULT_ESTADO
                                        ? filters.chat_estado
                                        : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={MessageSquare}
                            title={t('conversations.empty.title')}
                            description={t('conversations.empty.description')}
                        />
                    }
                />
            </section>

            <ConversationChatDialog
                open={viewConversation !== null}
                onOpenChange={(open) => !open && setViewConversation(null)}
                conversation={viewConversation}
            />
        </>
    );
}
