import { router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, ExternalLink, Loader2, SendHorizontal } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useTenantChatUnread } from '@/contexts/tenant-chat-unread-context';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';

export const OPEN_TENANT_CHAT_EVENT = 'vetsaas:open-tenant-chat';

type DockConversation = {
    id: string;
    title: string;
    type: 'direct' | 'group';
    unread: number;
    peer_online?: boolean | null;
    last_message: { body: string; created_at: string | null } | null;
};

type DockMessage = {
    id: string;
    body: string;
    user_name: string;
    created_at: string | null;
    mine?: boolean;
    is_deleted?: boolean;
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '·';
}

function timeLabel(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString(undefined, {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function TenantChatDock() {
    const { can } = usePermission();
    const { t } = useTranslation('chat-interno');
    const page = usePage();
    const { unreadTotal, setUnreadTotal, setActiveConversationId } = useTenantChatUnread();
    const allowed = can('comunicaciones-chat.view');
    const onChatPage = page.url.startsWith('/comunicaciones/chat');
    const [open, setOpen] = useState(false);
    const [conversations, setConversations] = useState<DockConversation[]>([]);
    const [loadingList, setLoadingList] = useState(false);
    const [activeId, setActiveId] = useState<string | null>(null);
    const [messages, setMessages] = useState<DockMessage[]>([]);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const scrollerRef = useRef<HTMLDivElement>(null);

    const loadList = useCallback(async () => {
        const res = await fetch('/comunicaciones/chat/dock', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            setLoadingList(false);

            return;
        }

        const json = (await res.json()) as {
            conversations?: DockConversation[];
            unread_total?: number;
        };
        setConversations(Array.isArray(json.conversations) ? json.conversations : []);
        setLoadingList(false);

        if (typeof json.unread_total === 'number') {
            setUnreadTotal(json.unread_total);
        }
    }, [setUnreadTotal]);

    const loadThread = useCallback(async (id: string) => {
        const res = await fetch(`/comunicaciones/chat/${id}/poll`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            return;
        }

        const json = (await res.json()) as {
            active?: { messages?: DockMessage[] };
            unread_total?: number;
        };
        setMessages(Array.isArray(json.active?.messages) ? json.active.messages : []);

        if (typeof json.unread_total === 'number') {
            setUnreadTotal(json.unread_total);
        }
    }, [setUnreadTotal]);

    useEffect(() => {
        setActiveConversationId(open ? activeId : null);
    }, [activeId, open, setActiveConversationId]);

    useEffect(() => {
        if (!allowed) {
            return;
        }

        const onOpen = (event: Event) => {
            const detail = (event as CustomEvent<{ conversationId?: string }>).detail;
            setOpen(true);

            if (detail?.conversationId) {
                setActiveId(detail.conversationId);
            }
        };

        window.addEventListener(OPEN_TENANT_CHAT_EVENT, onOpen);

        return () => window.removeEventListener(OPEN_TENANT_CHAT_EVENT, onOpen);
    }, [allowed]);

    useEffect(() => {
        if (!allowed || !open) {
            return;
        }

        const kick = window.setTimeout(() => {
            void loadList();
        }, 0);
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                void loadList();
            }
        }, 12_000);

        return () => {
            window.clearTimeout(kick);
            window.clearInterval(timer);
        };
    }, [allowed, loadList, open]);

    useEffect(() => {
        if (!open || !activeId) {
            return;
        }

        const kick = window.setTimeout(() => {
            void loadThread(activeId);
        }, 0);
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                void loadThread(activeId);
            }
        }, 5_000);

        return () => {
            window.clearTimeout(kick);
            window.clearInterval(timer);
        };
    }, [activeId, loadThread, open]);

    useEffect(() => {
        const node = scrollerRef.current;

        if (node) {
            node.scrollTop = node.scrollHeight;
        }
    }, [messages, activeId]);

    if (!allowed || onChatPage) {
        return null;
    }

    const active = conversations.find((row) => row.id === activeId) ?? null;

    const send = async () => {
        const body = draft.trim();

        if (!activeId || body === '' || sending) {
            return;
        }

        setSending(true);

        try {
            const res = await fetch(`/comunicaciones/chat/${activeId}/messages`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ body }),
            });

            if (res.ok) {
                setDraft('');
                await loadThread(activeId);
                await loadList();
            }
        } finally {
            setSending(false);
        }
    };

    const thread = active ? (
        <section className="flex h-[min(26rem,70vh)] w-[min(20rem,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-t-2xl border border-border/70 bg-white shadow-2xl dark:bg-background">
            <header className="flex items-center gap-2 border-b px-3 py-2.5">
                <PersonMark name={active.title} online={active.peer_online} />
                <p className="min-w-0 flex-1 truncate text-sm font-semibold">{active.title}</p>
                <button
                    type="button"
                    className="cursor-pointer rounded-md px-1.5 text-muted-foreground hover:bg-muted"
                    onClick={() => setActiveId(null)}
                    aria-label={t('dock_collapse')}
                >
                    –
                </button>
            </header>
            <div ref={scrollerRef} className="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto bg-muted/20 px-3 py-3">
                {messages.map((message) => (
                    <div key={message.id} className={cn('flex', message.mine ? 'justify-end' : 'justify-start')}>
                        <div
                            className={cn(
                                'max-w-[85%] rounded-2xl px-3 py-1.5 text-sm',
                                message.mine
                                    ? 'rounded-br-md bg-teal-600 text-white'
                                    : 'rounded-bl-md bg-muted text-foreground',
                            )}
                        >
                            {!message.mine ? (
                                <p className="mb-0.5 text-[10px] font-medium opacity-70">{message.user_name}</p>
                            ) : null}
                            <p className="whitespace-pre-wrap break-words">
                                {message.is_deleted ? '…' : message.body}
                            </p>
                            <p className={cn('mt-0.5 text-[10px]', message.mine ? 'text-white/75' : 'text-muted-foreground')}>
                                {timeLabel(message.created_at)}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
            <form
                className="flex items-center gap-2 border-t px-2 py-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    void send();
                }}
            >
                <input
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    placeholder={t('composer_placeholder')}
                    className="h-9 min-w-0 flex-1 rounded-full border border-amber-100 bg-[#fbf6e4] px-3 text-sm outline-none focus:border-teal-500 dark:border-border dark:bg-muted/40"
                />
                <button
                    type="submit"
                    disabled={sending || draft.trim() === ''}
                    className="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full bg-teal-600 text-white disabled:opacity-40"
                    aria-label={t('send')}
                >
                    {sending ? <Loader2 className="size-4 animate-spin" /> : <SendHorizontal className="size-4" />}
                </button>
            </form>
        </section>
    ) : null;

    return (
        <div className="pointer-events-none fixed right-3 bottom-0 z-40 flex items-end gap-2">
            {open && active ? <div className="pointer-events-auto">{thread}</div> : null}
            <div className={cn('pointer-events-auto', active && 'hidden sm:block')}>
                {open ? (
                    <section className="flex h-[min(28rem,72vh)] w-[min(20rem,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-t-2xl border border-teal-700/20 bg-white shadow-2xl dark:bg-background">
                        <header className="flex items-center justify-between bg-teal-600 px-3 py-2.5 text-white">
                            <span className="text-sm font-semibold">{t('title')}</span>
                            <div className="flex items-center gap-1">
                                <button
                                    type="button"
                                    className="cursor-pointer rounded-md p-1 hover:bg-white/15"
                                    title={t('dock_open_page')}
                                    aria-label={t('dock_open_page')}
                                    onClick={() => router.visit('/comunicaciones/chat')}
                                >
                                    <ExternalLink className="size-4" />
                                </button>
                                <button
                                    type="button"
                                    className="cursor-pointer rounded-md p-1 hover:bg-white/15"
                                    onClick={() => setOpen(false)}
                                    aria-label={t('dock_collapse')}
                                >
                                    <ChevronDown className="size-4" />
                                </button>
                            </div>
                        </header>
                        <ul className="min-h-0 flex-1 overflow-y-auto">
                            {loadingList && conversations.length === 0 ? (
                                <li className="flex justify-center py-8 text-muted-foreground">
                                    <Loader2 className="size-4 animate-spin" />
                                </li>
                            ) : conversations.length === 0 ? (
                                <li className="px-4 py-8 text-center text-sm text-muted-foreground">{t('empty_list')}</li>
                            ) : (
                                conversations.map((row) => (
                                    <li key={row.id}>
                                        <button
                                            type="button"
                                            className={cn(
                                                'flex w-full cursor-pointer items-center gap-2.5 px-3 py-2.5 text-left hover:bg-muted/60',
                                                row.id === activeId && 'bg-teal-50 dark:bg-teal-950/30',
                                            )}
                                            onClick={() => {
                                                setActiveId(row.id);
                                                setLoadingList(false);
                                            }}
                                        >
                                            <PersonMark name={row.title} online={row.peer_online} />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium">{row.title}</span>
                                                {row.last_message?.body ? (
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {row.last_message.body}
                                                    </span>
                                                ) : null}
                                            </span>
                                            {row.unread > 0 ? (
                                                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">
                                                    {row.unread > 99 ? '99+' : row.unread}
                                                </span>
                                            ) : null}
                                        </button>
                                    </li>
                                ))
                            )}
                        </ul>
                    </section>
                ) : (
                    <button
                        type="button"
                        className="mb-3 flex cursor-pointer items-center gap-2 rounded-full bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-teal-700/25 hover:bg-teal-700"
                        onClick={() => {
                            setLoadingList(true);
                            setOpen(true);
                        }}
                    >
                        {t('title')}
                        {unreadTotal > 0 ? (
                            <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1 text-[10px] font-bold text-teal-700">
                                {unreadTotal > 99 ? '99+' : unreadTotal}
                            </span>
                        ) : null}
                        <ChevronUp className="size-4" />
                    </button>
                )}
            </div>
        </div>
    );
}

function PersonMark({ name, online }: { name: string; online?: boolean | null }) {
    return (
        <span className="relative flex size-9 shrink-0 items-center justify-center rounded-full bg-sky-600 text-xs font-semibold text-white">
            {initials(name)}
            {online === true ? (
                <span className="absolute right-0 bottom-0 size-2.5 rounded-full border-2 border-white bg-emerald-500" />
            ) : online === false ? (
                <span className="absolute right-0 bottom-0 size-2.5 rounded-full border-2 border-white bg-rose-500" />
            ) : null}
        </span>
    );
}
