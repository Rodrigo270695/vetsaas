import { router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, ExternalLink, Loader2, Paperclip, SendHorizontal, Smile, X } from 'lucide-react';
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
    participants?: { id: string; name: string }[];
    last_message: { body: string; created_at: string | null } | null;
};

type DockUser = {
    id: string;
    name: string;
    online?: boolean;
};

type DockAttachment = {
    url: string | null;
    name: string;
    is_image: boolean;
};

type DockMessage = {
    id: string;
    body: string;
    user_name: string;
    created_at: string | null;
    mine?: boolean;
    is_deleted?: boolean;
    attachment?: DockAttachment | null;
    attachments?: DockAttachment[];
};

const EMOJIS = [
    '😀', '😁', '😂', '🙂', '😉', '😊', '😍', '🤩',
    '😎', '🤔', '😢', '😭', '😤', '🙌', '👍', '👎',
    '👏', '🙏', '💪', '🔥', '✨', '✅', '❌', '⚠️',
    '📌', '📎', '📷', '🐶', '🐱', '💉', '💊', '🩺',
];

const MAX_FILES = 5;

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
    const [panelPresent, setPanelPresent] = useState(false);
    const [panelShown, setPanelShown] = useState(false);
    const [threadPresent, setThreadPresent] = useState(false);
    const [threadShown, setThreadShown] = useState(false);
    const panelTimer = useRef<number | null>(null);
    const threadTimer = useRef<number | null>(null);
    const [conversations, setConversations] = useState<DockConversation[]>([]);
    const [users, setUsers] = useState<DockUser[]>([]);
    const [loadingList, setLoadingList] = useState(false);
    const [activeId, setActiveId] = useState<string | null>(null);
    const [threadTitle, setThreadTitle] = useState('');
    const [startingId, setStartingId] = useState<string | null>(null);
    const [messages, setMessages] = useState<DockMessage[]>([]);
    const [draft, setDraft] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [emojiOpen, setEmojiOpen] = useState(false);
    const [sending, setSending] = useState(false);
    const scrollerRef = useRef<HTMLDivElement>(null);
    const fileRef = useRef<HTMLInputElement>(null);

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
            users?: DockUser[];
            unread_total?: number;
        };
        setConversations(Array.isArray(json.conversations) ? json.conversations : []);
        setUsers(Array.isArray(json.users) ? json.users : []);
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

    const showPanel = useCallback(() => {
        if (panelTimer.current !== null) {
            window.clearTimeout(panelTimer.current);
            panelTimer.current = null;
        }

        setLoadingList(true);
        setOpen(true);
        setPanelPresent(true);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => setPanelShown(true));
        });
    }, []);

    const hidePanel = useCallback(() => {
        setOpen(false);
        setPanelShown(false);
        setThreadShown(false);

        if (panelTimer.current !== null) {
            window.clearTimeout(panelTimer.current);
        }

        if (threadTimer.current !== null) {
            window.clearTimeout(threadTimer.current);
            threadTimer.current = null;
        }

        panelTimer.current = window.setTimeout(() => {
            setPanelPresent(false);
            setActiveId(null);
            setThreadPresent(false);
            panelTimer.current = null;
        }, 320);
    }, []);

    const showThread = useCallback((id: string) => {
        if (threadTimer.current !== null) {
            window.clearTimeout(threadTimer.current);
            threadTimer.current = null;
        }

        setActiveId(id);
        setDraft('');
        setFiles([]);
        setEmojiOpen(false);
        setThreadPresent(true);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => setThreadShown(true));
        });
    }, []);

    const hideThread = useCallback(() => {
        setThreadShown(false);

        if (threadTimer.current !== null) {
            window.clearTimeout(threadTimer.current);
        }

        threadTimer.current = window.setTimeout(() => {
            setActiveId(null);
            setThreadPresent(false);
            threadTimer.current = null;
        }, 280);
    }, []);

    useEffect(() => {
        setActiveConversationId(open ? activeId : null);
    }, [activeId, open, setActiveConversationId]);

    useEffect(() => {
        if (!allowed) {
            return;
        }

        const onOpen = (event: Event) => {
            const detail = (event as CustomEvent<{ conversationId?: string }>).detail;
            showPanel();

            if (detail?.conversationId) {
                showThread(detail.conversationId);
            }
        };

        window.addEventListener(OPEN_TENANT_CHAT_EVENT, onOpen);

        return () => window.removeEventListener(OPEN_TENANT_CHAT_EVENT, onOpen);
    }, [allowed, showPanel, showThread]);

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
    const directPeerIds = new Set(
        conversations
            .filter((row) => row.type === 'direct')
            .flatMap((row) => row.participants ?? [])
            .map((person) => person.id),
    );
    const directory = users.filter((person) => !directPeerIds.has(person.id));
    const listEmpty = conversations.length === 0 && directory.length === 0;

    const startDirect = async (person: DockUser) => {
        if (startingId) {
            return;
        }

        setStartingId(person.id);

        try {
            const res = await fetch('/comunicaciones/chat/direct', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ user_id: person.id }),
            });
            const json = (await res.json()) as { conversation_id?: string };

            if (res.ok && json.conversation_id) {
                setThreadTitle(person.name);
                showThread(json.conversation_id);
                void loadList();
            }
        } finally {
            setStartingId(null);
        }
    };

    const send = async () => {
        const body = draft.trim();

        if (!activeId || sending || (body === '' && files.length === 0)) {
            return;
        }

        setSending(true);

        try {
            const form = new FormData();
            form.append('body', body);
            files.forEach((file) => form.append('attachments[]', file));
            const res = await fetch(`/comunicaciones/chat/${activeId}/messages`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: form,
            });

            if (res.ok) {
                setDraft('');
                setFiles([]);
                setEmojiOpen(false);
                await loadThread(activeId);
                await loadList();
            }
        } finally {
            setSending(false);
        }
    };

    const thread = activeId ? (
        <section className="flex h-[min(26rem,70vh)] w-[min(20rem,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-t-2xl border border-border/70 bg-white shadow-2xl dark:bg-background">
            <header className="flex items-center gap-2 border-b px-3 py-2.5">
                <PersonMark name={active?.title ?? threadTitle} online={active?.peer_online} />
                <p className="min-w-0 flex-1 truncate text-sm font-semibold">{active?.title ?? threadTitle}</p>
                <button
                    type="button"
                    className="cursor-pointer rounded-md px-1.5 text-muted-foreground hover:bg-muted"
                    onClick={hideThread}
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
                            <MessageFiles message={message} mine={Boolean(message.mine)} />
                            {message.body && !message.is_deleted ? (
                                <p className="whitespace-pre-wrap break-words">{message.body}</p>
                            ) : message.is_deleted ? (
                                <p className="whitespace-pre-wrap break-words">…</p>
                            ) : null}
                            <p className={cn('mt-0.5 text-[10px]', message.mine ? 'text-white/75' : 'text-muted-foreground')}>
                                {timeLabel(message.created_at)}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
            <form
                className="relative border-t px-2 py-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    void send();
                }}
            >
                {emojiOpen ? (
                    <div className="absolute right-2 bottom-full z-10 mb-1 grid w-64 grid-cols-8 gap-0.5 rounded-xl border bg-white p-2 shadow-lg dark:bg-background">
                        {EMOJIS.map((emoji) => (
                            <button
                                key={emoji}
                                type="button"
                                className="cursor-pointer rounded-md p-1 text-lg hover:bg-muted"
                                onClick={() => {
                                    setDraft((current) => `${current}${emoji}`);
                                    setEmojiOpen(false);
                                }}
                            >
                                {emoji}
                            </button>
                        ))}
                    </div>
                ) : null}
                {files.length > 0 ? (
                    <ul className="mb-1.5 flex flex-wrap gap-1">
                        {files.map((file, index) => (
                            <li key={`${file.name}-${index}`} className="flex max-w-full items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[11px]">
                                <span className="truncate">{file.name}</span>
                                <button
                                    type="button"
                                    className="cursor-pointer text-muted-foreground"
                                    aria-label={t('remove_attachment')}
                                    onClick={() => setFiles((current) => current.filter((_, i) => i !== index))}
                                >
                                    <X className="size-3" />
                                </button>
                            </li>
                        ))}
                    </ul>
                ) : null}
                <div className="flex items-center gap-1">
                    <input
                        ref={fileRef}
                        type="file"
                        multiple
                        className="hidden"
                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
                        onChange={(event) => {
                            const picked = Array.from(event.target.files ?? []);
                            setFiles((current) => [...current, ...picked].slice(0, MAX_FILES));
                            event.target.value = '';
                        }}
                    />
                    <button
                        type="button"
                        className="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground hover:bg-muted disabled:opacity-40"
                        aria-label={t('attach')}
                        title={files.length >= MAX_FILES ? t('attachments_max') : t('attach')}
                        disabled={files.length >= MAX_FILES}
                        onClick={() => fileRef.current?.click()}
                    >
                        <Paperclip className="size-4" />
                    </button>
                    <button
                        type="button"
                        className="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
                        aria-label={t('emoji')}
                        onClick={() => setEmojiOpen((current) => !current)}
                    >
                        <Smile className="size-4" />
                    </button>
                    <input
                        value={draft}
                        onChange={(event) => setDraft(event.target.value)}
                        placeholder={t('composer_placeholder')}
                        className="h-9 min-w-0 flex-1 rounded-full border border-amber-100 bg-[#fbf6e4] px-3 text-sm outline-none focus:border-teal-500 dark:border-border dark:bg-muted/40"
                    />
                    <button
                        type="submit"
                        disabled={sending || (draft.trim() === '' && files.length === 0)}
                        className="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full bg-teal-600 text-white disabled:opacity-40"
                        aria-label={t('send')}
                    >
                        {sending ? <Loader2 className="size-4 animate-spin" /> : <SendHorizontal className="size-4" />}
                    </button>
                </div>
            </form>
        </section>
    ) : null;

    const motion =
        'origin-bottom-right transition-[opacity,transform] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none';

    return (
        <div className="pointer-events-none fixed right-3 bottom-0 z-40">
            <div className="relative w-[min(20rem,calc(100vw-1.5rem))]">
                {threadPresent && thread ? (
                    <div
                        className={cn(
                            'pointer-events-auto absolute right-0 bottom-0 sm:right-full sm:mr-2',
                            motion,
                            threadShown
                                ? 'translate-x-0 opacity-100'
                                : 'pointer-events-none translate-x-3 opacity-0',
                        )}
                    >
                        {thread}
                    </div>
                ) : null}
                {panelPresent ? (
                    <section
                        className={cn(
                            'absolute right-0 bottom-0 flex h-[min(28rem,72vh)] w-full flex-col overflow-hidden rounded-t-2xl border border-teal-700/20 bg-white shadow-2xl dark:bg-background',
                            motion,
                            panelShown
                                ? 'pointer-events-auto translate-y-0 scale-100 opacity-100'
                                : 'pointer-events-none translate-y-3 scale-[0.96] opacity-0',
                            threadShown && 'max-sm:pointer-events-none max-sm:opacity-0',
                        )}
                    >
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
                                    onClick={hidePanel}
                                    aria-label={t('dock_collapse')}
                                >
                                    <ChevronDown className="size-4" />
                                </button>
                            </div>
                        </header>
                        <ul className="min-h-0 flex-1 overflow-y-auto">
                            {loadingList && listEmpty ? (
                                <li className="flex justify-center py-8 text-muted-foreground">
                                    <Loader2 className="size-4 animate-spin" />
                                </li>
                            ) : listEmpty ? (
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
                                                setLoadingList(false);
                                                setThreadTitle(row.title);
                                                showThread(row.id);
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
                            {directory.length > 0 && conversations.length > 0 ? (
                                <li className="px-3 pt-2 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                    {t('dock_equipo')}
                                </li>
                            ) : null}
                            {directory.map((person) => (
                                <li key={person.id}>
                                    <button
                                        type="button"
                                        className="flex w-full cursor-pointer items-center gap-2.5 px-3 py-2.5 text-left hover:bg-muted/60"
                                        onClick={() => void startDirect(person)}
                                    >
                                        <PersonMark name={person.name} online={person.online} />
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium">{person.name}</span>
                                        {startingId === person.id ? (
                                            <Loader2 className="size-4 shrink-0 animate-spin text-muted-foreground" />
                                        ) : null}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}
                <div className="flex justify-end">
                    <button
                        type="button"
                        className={cn(
                            'pointer-events-auto mb-3 flex cursor-pointer items-center gap-2 rounded-full bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-teal-700/25 transition-opacity duration-200 hover:bg-teal-700',
                            panelShown && 'pointer-events-none opacity-0',
                        )}
                        onClick={showPanel}
                    >
                        {t('title')}
                        {unreadTotal > 0 ? (
                            <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-white px-1 text-[10px] font-bold text-teal-700">
                                {unreadTotal > 99 ? '99+' : unreadTotal}
                            </span>
                        ) : null}
                        <ChevronUp className="size-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}

function messageFiles(message: DockMessage): DockAttachment[] {
    if (message.attachments && message.attachments.length > 0) {
        return message.attachments;
    }

    if (message.attachment) {
        return [message.attachment];
    }

    return [];
}

function MessageFiles({ message, mine }: { message: DockMessage; mine: boolean }) {
    const files = messageFiles(message);

    if (files.length === 0) {
        return null;
    }

    return (
        <div className="mb-1 space-y-1">
            {files.map((file, index) =>
                file.is_image && file.url ? (
                    <a key={`${file.url}-${index}`} href={file.url} target="_blank" rel="noreferrer">
                        <img src={file.url} alt={file.name} className="max-h-32 rounded-lg" />
                    </a>
                ) : (
                    <a
                        key={`${file.name}-${index}`}
                        href={file.url ?? undefined}
                        target="_blank"
                        rel="noreferrer"
                        className={cn(
                            'block truncate text-xs underline',
                            mine ? 'text-white' : 'text-teal-700',
                        )}
                    >
                        {file.name}
                    </a>
                ),
            )}
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
