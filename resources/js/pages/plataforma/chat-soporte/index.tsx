import { Head, router, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    FileText,
    Forward,
    Loader2,
    Megaphone,
    MoreHorizontal,
    Paperclip,
    Pencil,
    Reply,
    Search,
    SendHorizonal,
    Smile,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type FormEvent,
    type ReactNode,
} from 'react';
import { useTranslation } from 'react-i18next';
import {
    ChatListAside,
    ChatMessageScroller,
    ChatPageHeader,
    ChatSearchInput,
    ChatShell,
} from '@/components/chat';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { usePlatformSupportChatUnread } from '@/contexts/platform-support-chat-unread-context';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';

const ROUTE_URL = '/plataforma/chat-soporte';
const MAX_ATTACHMENTS = 5;
const REACTION_EMOJIS = ['👍', '✅', '❤️', '😂', '🎉'] as const;
const EMOJIS = [
    '😀', '😁', '😂', '🙂', '😉', '😊', '😍', '🤩',
    '😎', '🤔', '😢', '😭', '😤', '🙌', '👍', '👎',
    '👏', '🙏', '💪', '🔥', '✨', '✅', '❌', '⚠️',
    '📌', '📎', '📷', '🐶', '🐱', '💉', '💊', '🩺',
];

type PlanFilter = 'all' | 'free' | 'paid';
type ReactionEmoji = (typeof REACTION_EMOJIS)[number];

type TenantRow = {
    id: string;
    slug: string;
    nombre: string;
    estado: string;
    plan_codigo: string | null;
    plan_nombre: string | null;
    is_free: boolean | null;
    thread: {
        conversation_id: string;
        last_message_at: string | null;
        last_preview: string | null;
        unread?: boolean;
        from_clinic?: boolean;
    } | null;
};

type ChatAttachment = {
    url: string;
    name: string;
    mime: string;
    size?: number;
};

type ChatReaction = {
    emoji: string;
    count: number;
    reacted?: boolean;
};

type ReplyPreview = {
    id: string;
    body: string;
    user_id?: string;
    user_name: string;
};

type ChatMessage = {
    id: string;
    body: string;
    mine: boolean;
    user_id?: string;
    user_name?: string;
    created_at: string | null;
    edited_at?: string | null;
    deleted?: boolean;
    is_deleted?: boolean;
    attachments?: ChatAttachment[];
    attachment?: ChatAttachment | null;
    reply_to?: ReplyPreview | null;
    reactions?: ChatReaction[];
};

type ForwardTarget = {
    id: string;
    title: string;
    type: string;
};

type Props = {
    tenants: TenantRow[];
    filters: {
        plan: PlanFilter;
        q: string;
    };
};

const POLL_MS = 4000;

function readXsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

async function apiJson<T>(
    url: string,
    init?: RequestInit & { json?: Record<string, unknown>; formData?: FormData },
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': readXsrfToken(),
        ...(init?.headers as Record<string, string> | undefined),
    };

    let body = init?.body;
    if (init?.formData !== undefined) {
        body = init.formData;
        delete headers['Content-Type'];
    } else if (init?.json !== undefined) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(init.json);
    }

    const res = await fetch(url, {
        ...init,
        credentials: 'same-origin',
        headers,
        body,
    });

    if (!res.ok) {
        const err = (await res.json().catch(() => null)) as {
            message?: string;
        } | null;
        throw new Error(err?.message ?? `HTTP ${res.status}`);
    }

    return (await res.json()) as T;
}

const formatListTime = (iso: string | null): string => {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const now = new Date();
    const sameDay =
        d.getDate() === now.getDate() &&
        d.getMonth() === now.getMonth() &&
        d.getFullYear() === now.getFullYear();
    if (sameDay) {
        return d.toLocaleTimeString('es-PE', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }
    return d.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
};

const messageAttachments = (m: ChatMessage): ChatAttachment[] => {
    if (m.attachments && m.attachments.length > 0) return m.attachments;
    if (m.attachment) return [m.attachment];
    return [];
};

const isMessageDeleted = (m: ChatMessage): boolean =>
    Boolean(m.deleted || m.is_deleted);

const stripActorPrefix = (body: string): string =>
    body.replace(/^\[[^\]]+\]\s*/, '');

const upsertMessage = (
    prev: ChatMessage[],
    msg: ChatMessage,
): ChatMessage[] => {
    const idx = prev.findIndex((m) => m.id === msg.id);
    if (idx === -1) return [...prev, msg];
    const next = [...prev];
    next[idx] = { ...prev[idx], ...msg };
    return next;
};

export default function PlataformaChatSoportePage({
    tenants: initialTenants,
    filters,
}: Props) {
    const { t } = useTranslation('plataforma-chat-soporte');
    const { can } = usePermission();
    const canManage = can('plataforma-chat-soporte.manage');
    const page = usePage();
    const { setActiveTenantId } = usePlatformSupportChatUnread();
    const deepLinkTenant = useMemo(() => {
        const raw = (page.url.match(/[?&]tenant=([^&]+)/) ?? [])[1];
        return raw ? decodeURIComponent(raw) : null;
    }, [page.url]);

    const [tenants, setTenants] = useState(initialTenants);
    const [plan, setPlan] = useState<PlanFilter>(filters.plan ?? 'all');
    const [listQuery, setListQuery] = useState(filters.q ?? '');
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [loadingThread, setLoadingThread] = useState(false);
    const [composer, setComposer] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [emojiOpen, setEmojiOpen] = useState(false);
    const [sending, setSending] = useState(false);
    const [broadcastOpen, setBroadcastOpen] = useState(false);
    const [broadcastBody, setBroadcastBody] = useState('');
    const [broadcasting, setBroadcasting] = useState(false);
    const [mobileListOpen, setMobileListOpen] = useState(true);
    const [searchOpen, setSearchOpen] = useState(false);
    const [threadQuery, setThreadQuery] = useState('');
    const [highlightId, setHighlightId] = useState<string | null>(null);
    const [replyTo, setReplyTo] = useState<ReplyPreview | null>(null);
    const [editingMessage, setEditingMessage] = useState<ChatMessage | null>(
        null,
    );
    const [deleteMessage, setDeleteMessage] = useState<ChatMessage | null>(
        null,
    );
    const [deleting, setDeleting] = useState(false);
    const [forwardMessage, setForwardMessage] = useState<ChatMessage | null>(
        null,
    );
    const [forwardTargets, setForwardTargets] = useState<ForwardTarget[]>([]);
    const [forwardTargetId, setForwardTargetId] = useState('');
    const [forwardLoading, setForwardLoading] = useState(false);
    const [forwarding, setForwarding] = useState(false);

    const bottomRef = useRef<HTMLDivElement | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);
    const composerRef = useRef<HTMLTextAreaElement | null>(null);
    const messageRefs = useRef<Map<string, HTMLDivElement>>(new Map());
    const selectedIdRef = useRef<string | null>(null);
    const lastMessageIdRef = useRef<string | null>(null);
    const pollTickRef = useRef(0);
    selectedIdRef.current = selectedId;
    lastMessageIdRef.current =
        messages.length > 0 ? (messages[messages.length - 1]?.id ?? null) : null;

    useEffect(() => {
        setTenants(initialTenants);
    }, [initialTenants]);

    useEffect(() => {
        setActiveTenantId(selectedId);
        return () => setActiveTenantId(null);
    }, [selectedId, setActiveTenantId]);

    // Refrescar lista (previews/unread) mientras hay página abierta.
    useEffect(() => {
        const timer = window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            router.reload({ only: ['tenants', 'filters'] });
        }, 15_000);
        return () => window.clearInterval(timer);
    }, []);

    const selected = useMemo(
        () => tenants.find((row) => row.id === selectedId) ?? null,
        [tenants, selectedId],
    );

    const filteredTenants = useMemo(() => {
        const q = listQuery.trim().toLowerCase();
        if (!q) return tenants;
        return tenants.filter((row) => {
            const hay = `${row.nombre} ${row.slug} ${row.plan_nombre ?? ''}`.toLowerCase();
            return hay.includes(q);
        });
    }, [tenants, listQuery]);

    const threadHits = useMemo(() => {
        const q = threadQuery.trim().toLowerCase();
        if (q.length < 2) return [];
        return messages.filter((m) => m.body.toLowerCase().includes(q));
    }, [messages, threadQuery]);

    const planOptions: { value: PlanFilter; label: string }[] = [
        { value: 'all', label: t('plan_all') },
        { value: 'free', label: t('plan_free') },
        { value: 'paid', label: t('plan_paid') },
    ];

    const applyPlan = (nextPlan: PlanFilter) => {
        setPlan(nextPlan);
        router.get(
            ROUTE_URL,
            { plan: nextPlan, q: listQuery.trim() || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['tenants', 'filters'],
            },
        );
    };

    const openTenant = async (tenantId: string) => {
        setSelectedId(tenantId);
        setMobileListOpen(false);
        setMessages([]);
        setComposer('');
        setFiles([]);
        setReplyTo(null);
        setEditingMessage(null);
        setDeleteMessage(null);
        setForwardMessage(null);
        setSearchOpen(false);
        setThreadQuery('');
        setLoadingThread(true);
        pollTickRef.current = 0;
        try {
            if (canManage) {
                await apiJson(`/plataforma/chat-soporte/tenants/${tenantId}/ensure`, {
                    method: 'POST',
                });
            }
            const data = await apiJson<{
                conversation_id: string | null;
                messages: ChatMessage[];
            }>(`/plataforma/chat-soporte/tenants/${tenantId}/messages`);
            setMessages(data.messages ?? []);
            setTenants((prev) =>
                prev.map((row) =>
                    row.id === tenantId && row.thread
                        ? {
                              ...row,
                              thread: { ...row.thread, unread: false },
                          }
                        : row,
                ),
            );
            router.reload({ only: ['tenants', 'filters'] });
            requestAnimationFrame(() => {
                bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
            });
        } catch {
            toastManager.error({ title: t('ensure_error') });
            setSelectedId(null);
            setMobileListOpen(true);
        } finally {
            setLoadingThread(false);
        }
    };

    useEffect(() => {
        if (!deepLinkTenant) return;
        if (selectedIdRef.current === deepLinkTenant) return;
        void openTenant(deepLinkTenant);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [deepLinkTenant]);

    useEffect(() => {
        if (!selectedId) return;

        const tick = async () => {
            const id = selectedIdRef.current;
            if (!id) return;
            pollTickRef.current += 1;
            const fullRefresh = pollTickRef.current % 4 === 0;
            const last = fullRefresh ? null : lastMessageIdRef.current;
            try {
                const url = last
                    ? `/plataforma/chat-soporte/tenants/${id}/messages?after=${encodeURIComponent(last)}`
                    : `/plataforma/chat-soporte/tenants/${id}/messages`;
                const data = await apiJson<{ messages: ChatMessage[] }>(url);
                if (!data.messages?.length && !fullRefresh) return;
                if (fullRefresh || !last) {
                    setMessages(data.messages ?? []);
                    return;
                }
                setMessages((prev) => {
                    const seen = new Set(prev.map((m) => m.id));
                    const next = data.messages.filter((m) => !seen.has(m.id));
                    return next.length ? [...prev, ...next] : prev;
                });
                if (data.messages.length) {
                    requestAnimationFrame(() => {
                        bottomRef.current?.scrollIntoView({
                            behavior: 'smooth',
                        });
                    });
                }
            } catch {
                // silent poll
            }
        };

        const timer = window.setInterval(() => {
            void tick();
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [selectedId]);

    const jumpToMessage = (id: string) => {
        setHighlightId(id);
        setSearchOpen(false);
        requestAnimationFrame(() => {
            messageRefs.current
                .get(id)
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
        window.setTimeout(() => setHighlightId(null), 1600);
    };

    const insertEmoji = (emoji: string) => {
        const el = composerRef.current;
        if (!el) {
            setComposer((prev) => prev + emoji);
            return;
        }
        const start = el.selectionStart ?? composer.length;
        const end = el.selectionEnd ?? composer.length;
        const next = composer.slice(0, start) + emoji + composer.slice(end);
        setComposer(next);
        requestAnimationFrame(() => {
            el.focus();
            const pos = start + emoji.length;
            el.setSelectionRange(pos, pos);
        });
        setEmojiOpen(false);
    };

    const cancelEditing = () => {
        setEditingMessage(null);
        setComposer('');
    };

    const startEditMessage = (m: ChatMessage) => {
        setReplyTo(null);
        setEditingMessage(m);
        setComposer(stripActorPrefix(m.body || ''));
        setFiles([]);
        requestAnimationFrame(() => composerRef.current?.focus());
    };

    const toggleReaction = async (message: ChatMessage, emoji: ReactionEmoji) => {
        if (!selectedId || !canManage || isMessageDeleted(message)) return;
        try {
            const data = await apiJson<{ message: ChatMessage }>(
                `/plataforma/chat-soporte/tenants/${selectedId}/messages/${message.id}/reaction`,
                { method: 'POST', json: { emoji } },
            );
            if (data.message) {
                setMessages((prev) => upsertMessage(prev, data.message));
            }
        } catch {
            toastManager.error({ title: t('action_error') });
        }
    };

    const confirmDelete = async () => {
        if (!selectedId || !deleteMessage || deleting) return;
        setDeleting(true);
        try {
            const data = await apiJson<{ message: ChatMessage }>(
                `/plataforma/chat-soporte/tenants/${selectedId}/messages/${deleteMessage.id}`,
                { method: 'DELETE' },
            );
            if (data.message) {
                setMessages((prev) => upsertMessage(prev, data.message));
            }
            setDeleteMessage(null);
        } catch {
            toastManager.error({ title: t('action_error') });
        } finally {
            setDeleting(false);
        }
    };

    const openForward = async (m: ChatMessage) => {
        if (!selectedId) return;
        setForwardMessage(m);
        setForwardTargetId('');
        setForwardTargets([]);
        setForwardLoading(true);
        try {
            const data = await apiJson<{ conversations: ForwardTarget[] }>(
                `/plataforma/chat-soporte/tenants/${selectedId}/forward-targets`,
            );
            setForwardTargets(data.conversations ?? []);
        } catch {
            toastManager.error({ title: t('action_error') });
            setForwardMessage(null);
        } finally {
            setForwardLoading(false);
        }
    };

    const submitForward = async () => {
        if (!selectedId || !forwardMessage || !forwardTargetId || forwarding) {
            return;
        }
        setForwarding(true);
        try {
            await apiJson(
                `/plataforma/chat-soporte/tenants/${selectedId}/messages/${forwardMessage.id}/forward`,
                {
                    method: 'POST',
                    json: { target_conversation_id: forwardTargetId },
                },
            );
            setForwardMessage(null);
            setForwardTargetId('');
            toastManager.success({ title: t('forward_ok') });
        } catch {
            toastManager.error({ title: t('action_error') });
        } finally {
            setForwarding(false);
        }
    };

    const sendMessage = async (e?: FormEvent) => {
        e?.preventDefault();
        if (!selectedId || !canManage) return;
        const body = composer.trim();
        if (sending) return;

        if (editingMessage) {
            if (!body) return;
            setSending(true);
            try {
                const data = await apiJson<{ message: ChatMessage }>(
                    `/plataforma/chat-soporte/tenants/${selectedId}/messages/${editingMessage.id}`,
                    { method: 'PATCH', json: { body } },
                );
                if (data.message) {
                    setMessages((prev) => upsertMessage(prev, data.message));
                }
                setEditingMessage(null);
                setComposer('');
            } catch {
                toastManager.error({ title: t('send_error') });
            } finally {
                setSending(false);
            }
            return;
        }

        if ((!body && files.length === 0) || sending) return;

        setSending(true);
        try {
            const fd = new FormData();
            if (body) fd.append('body', body);
            if (replyTo?.id) fd.append('reply_to_id', replyTo.id);
            files.forEach((file) => fd.append('attachments[]', file));

            const data = await apiJson<{ message: ChatMessage }>(
                `/plataforma/chat-soporte/tenants/${selectedId}/messages`,
                { method: 'POST', formData: fd },
            );
            setComposer('');
            setFiles([]);
            setReplyTo(null);
            if (data.message) {
                setMessages((prev) =>
                    prev.some((m) => m.id === data.message.id)
                        ? prev
                        : [...prev, data.message],
                );
                const preview =
                    body.slice(0, 280) ||
                    data.message.body?.slice(0, 280) ||
                    '📎';
                setTenants((prev) =>
                    prev.map((row) =>
                        row.id === selectedId
                            ? {
                                  ...row,
                                  thread: {
                                      conversation_id:
                                          row.thread?.conversation_id ?? '',
                                      last_message_at:
                                          data.message.created_at ??
                                          new Date().toISOString(),
                                      last_preview: preview,
                                  },
                              }
                            : row,
                    ),
                );
            }
            requestAnimationFrame(() => {
                bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
            });
        } catch {
            toastManager.error({ title: t('send_error') });
        } finally {
            setSending(false);
        }
    };

    const runBroadcast = async () => {
        const body = broadcastBody.trim();
        if (!body || !canManage || broadcasting) return;
        setBroadcasting(true);
        try {
            const data = await apiJson<{
                queued: boolean;
                target_count: number;
                sent?: number;
                failed?: unknown[];
            }>('/plataforma/chat-soporte/broadcast', {
                method: 'POST',
                json: { body, plan },
            });
            setBroadcastOpen(false);
            setBroadcastBody('');
            if (data.queued) {
                toastManager.success({
                    title: t('broadcast_queued', { count: data.target_count }),
                });
            } else {
                const failed = data.failed?.length ?? 0;
                if (failed > 0) {
                    toastManager.warning({
                        title: t('broadcast_partial', {
                            sent: data.sent ?? 0,
                            failed,
                        }),
                    });
                } else {
                    toastManager.success({
                        title: t('broadcast_done', { sent: data.sent ?? 0 }),
                    });
                }
            }
            router.reload({ only: ['tenants', 'filters'] });
        } catch {
            toastManager.error({ title: t('send_error') });
        } finally {
            setBroadcasting(false);
        }
    };

    return (
        <>
            <Head title={t('title')} />

            <ChatShell>
                <ChatPageHeader
                    title={t('title')}
                    subtitle={t('subtitle')}
                    hideOnMobileWhenThread={selected !== null}
                    actions={
                        canManage ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-8 shrink-0 gap-1.5 text-xs"
                                onClick={() => setBroadcastOpen(true)}
                                disabled={tenants.length === 0}
                            >
                                <Megaphone className="size-3.5" />
                                <span className="hidden sm:inline">
                                    {t('broadcast')}
                                </span>
                            </Button>
                        ) : null
                    }
                />

                <div className="relative min-h-0 flex-1 overflow-hidden lg:grid lg:grid-cols-[minmax(17rem,21rem)_1fr]">
                    <ChatListAside
                        mobileTitle={t('list_title')}
                        mobileSubtitle={t('list_hint')}
                        hasActiveThread={selected !== null}
                        mobileListOpen={mobileListOpen}
                        onCloseMobileList={() => setMobileListOpen(false)}
                        onBackdropClick={() => setMobileListOpen(false)}
                        toolbar={
                            <>
                                <ChatSearchInput
                                    value={listQuery}
                                    onChange={(e) => setListQuery(e.target.value)}
                                    placeholder={t('search_placeholder')}
                                />
                                <div className="flex flex-wrap gap-1.5">
                                    {planOptions.map((opt) => (
                                        <Button
                                            key={opt.value}
                                            type="button"
                                            size="sm"
                                            variant={
                                                plan === opt.value
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className={cn(
                                                'h-7 rounded-full px-3 text-xs',
                                                plan === opt.value &&
                                                    'bg-emerald-600 hover:bg-emerald-700',
                                            )}
                                            onClick={() => applyPlan(opt.value)}
                                        >
                                            {opt.label}
                                        </Button>
                                    ))}
                                </div>
                            </>
                        }
                    >
                        {filteredTenants.length === 0 ? (
                            <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                                {t('empty_tenants')}
                            </p>
                        ) : (
                            <ul className="divide-y divide-border/40">
                                {filteredTenants.map((row, index) => {
                                    const active = row.id === selectedId;
                                    return (
                                        <li
                                            key={row.id}
                                            className="animate-in fade-in slide-in-from-left-2 fill-mode-both duration-300"
                                            style={{
                                                animationDelay: `${Math.min(index, 8) * 35}ms`,
                                            }}
                                        >
                                            <button
                                                type="button"
                                                onClick={() => void openTenant(row.id)}
                                                    className={cn(
                                                        'flex w-full cursor-pointer items-start gap-3 border-l-2 px-3 py-3.5 text-left transition-colors active:bg-emerald-50/70 lg:py-3',
                                                        active
                                                            ? 'border-l-emerald-600 bg-emerald-50/80 dark:bg-emerald-950/35'
                                                            : row.thread?.unread
                                                              ? 'border-l-emerald-500/70 bg-emerald-50/40 dark:bg-emerald-950/20'
                                                              : 'border-l-transparent hover:bg-background/80',
                                                    )}
                                                >
                                                <Avatar className="mt-0.5 size-11 border border-border/50 shadow-sm lg:size-10">
                                                    <AvatarFallback className="bg-sky-100 text-xs font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200">
                                                        <Users className="size-3.5" />
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0 flex-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="truncate text-sm font-medium">
                                                                {row.nombre}
                                                            </span>
                                                            {row.thread?.unread ? (
                                                                <Badge className="h-5 shrink-0 rounded-full bg-emerald-600 px-1.5 text-[10px] text-white hover:bg-emerald-600">
                                                                    1
                                                                </Badge>
                                                            ) : null}
                                                            {row.is_free === true ? (
                                                            <Badge
                                                                variant="secondary"
                                                                className="h-5 shrink-0 rounded-full px-1.5 text-[10px]"
                                                            >
                                                                {t('badge_free')}
                                                            </Badge>
                                                        ) : row.is_free ===
                                                          false ? (
                                                            <Badge className="h-5 shrink-0 rounded-full bg-sky-600 px-1.5 text-[10px] text-white hover:bg-sky-600">
                                                                {t('badge_paid')}
                                                            </Badge>
                                                        ) : null}
                                                        <span className="ml-auto shrink-0 text-[10px] text-muted-foreground">
                                                            {formatListTime(
                                                                row.thread
                                                                    ?.last_message_at ??
                                                                    null,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                        {row.thread?.last_preview ||
                                                            row.slug}
                                                    </p>
                                                </div>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </ChatListAside>

                    <section
                        className={cn(
                            'flex min-h-0 flex-col bg-card bg-[radial-gradient(ellipse_at_top,rgba(16,185,129,0.06),transparent_55%)] lg:relative',
                            'max-lg:absolute max-lg:inset-0 max-lg:z-10 max-lg:transition-[transform,filter] max-lg:duration-500',
                            !selected && 'max-lg:hidden',
                            selected &&
                                mobileListOpen &&
                                'max-lg:scale-[0.985] max-lg:brightness-[0.92]',
                        )}
                    >
                        {!selected ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
                                <div className="flex size-16 items-center justify-center rounded-2xl bg-emerald-600/10 text-emerald-700 ring-1 ring-emerald-600/15 dark:text-emerald-300">
                                    <Users className="size-7" aria-hidden />
                                </div>
                                <p className="text-sm font-semibold">
                                    {t('empty_thread')}
                                </p>
                            </div>
                        ) : (
                            <>
                                <header className="flex flex-col gap-2 border-b border-border/60 bg-card/90 px-2 py-2.5 backdrop-blur-md sm:px-4 sm:py-3">
                                    <div className="flex items-center gap-2 sm:gap-3">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-9 shrink-0 cursor-pointer lg:hidden"
                                            onClick={() => setMobileListOpen(true)}
                                            aria-label={t('list_title')}
                                        >
                                            <ChevronLeft className="size-5" />
                                        </Button>
                                        <Avatar className="size-9 border border-border/50 shadow-sm sm:size-10">
                                            <AvatarFallback className="bg-sky-100 text-xs font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200">
                                                <Users className="size-3.5" />
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold">
                                                {selected.nombre}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {selected.plan_nombre ??
                                                    selected.slug}
                                            </p>
                                        </div>
                                        {selected.is_free === true ? (
                                            <Badge
                                                variant="secondary"
                                                className="rounded-full"
                                            >
                                                {t('badge_free')}
                                            </Badge>
                                        ) : selected.is_free === false ? (
                                            <Badge className="rounded-full bg-sky-600 hover:bg-sky-600">
                                                {t('badge_paid')}
                                            </Badge>
                                        ) : null}
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className={cn(
                                                'size-8 shrink-0 text-muted-foreground',
                                                searchOpen && 'bg-muted',
                                            )}
                                            onClick={() =>
                                                setSearchOpen((v) => !v)
                                            }
                                            aria-label={t('search_thread')}
                                        >
                                            <Search className="size-4" />
                                        </Button>
                                    </div>

                                    {searchOpen ? (
                                        <div className="relative px-1 sm:px-0">
                                            <ChatSearchInput
                                                value={threadQuery}
                                                onChange={(e) =>
                                                    setThreadQuery(e.target.value)
                                                }
                                                placeholder={t('search_thread')}
                                                autoFocus
                                            />
                                            {threadQuery.trim().length >= 2 ? (
                                                <div className="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-border/60 bg-card shadow-lg">
                                                    {threadHits.length === 0 ? (
                                                        <p className="px-3 py-2 text-xs text-muted-foreground">
                                                            {t(
                                                                'search_thread_empty',
                                                            )}
                                                        </p>
                                                    ) : (
                                                        threadHits.map((m) => (
                                                            <button
                                                                key={m.id}
                                                                type="button"
                                                                className="flex w-full flex-col gap-0.5 border-b border-border/40 px-3 py-2 text-left last:border-0 hover:bg-muted/60"
                                                                onClick={() =>
                                                                    jumpToMessage(
                                                                        m.id,
                                                                    )
                                                                }
                                                            >
                                                                <span className="text-[10px] font-medium text-emerald-700 dark:text-emerald-300">
                                                                    {m.user_name ||
                                                                        t('you')}{' '}
                                                                    ·{' '}
                                                                    {formatListTime(
                                                                        m.created_at,
                                                                    )}
                                                                </span>
                                                                <span className="line-clamp-2 text-xs">
                                                                    {m.body}
                                                                </span>
                                                            </button>
                                                        ))
                                                    )}
                                                </div>
                                            ) : (
                                                <p className="mt-1 px-1 text-[10px] text-muted-foreground">
                                                    {t('search_thread_hint')}
                                                </p>
                                            )}
                                        </div>
                                    ) : null}
                                </header>

                                <ChatMessageScroller>
                                    {loadingThread ? (
                                        <div className="flex items-center justify-center gap-2 py-12 text-sm text-muted-foreground">
                                            <Loader2 className="size-4 animate-spin" />
                                            {t('loading')}
                                        </div>
                                    ) : messages.length === 0 ? (
                                        <p className="py-12 text-center text-sm text-muted-foreground">
                                            {t('no_messages')}
                                        </p>
                                    ) : (
                                        messages.map((m) => {
                                            const deleted = isMessageDeleted(m);
                                            const atts = deleted
                                                ? []
                                                : messageAttachments(m);
                                            const reactions = m.reactions ?? [];
                                            return (
                                                <div
                                                    key={m.id}
                                                    ref={(el) => {
                                                        if (el) {
                                                            messageRefs.current.set(
                                                                m.id,
                                                                el,
                                                            );
                                                        } else {
                                                            messageRefs.current.delete(
                                                                m.id,
                                                            );
                                                        }
                                                    }}
                                                    className={cn(
                                                        'group flex',
                                                        m.mine
                                                            ? 'justify-end'
                                                            : 'justify-start',
                                                        highlightId === m.id &&
                                                            'animate-pulse',
                                                    )}
                                                >
                                                    <div className="flex max-w-[min(85%,28rem)] flex-col gap-0.5">
                                                        <div
                                                            className={cn(
                                                                'relative overflow-hidden rounded-2xl text-sm shadow-sm',
                                                                deleted
                                                                    ? 'border border-dashed border-border/70 bg-muted/40 text-muted-foreground'
                                                                    : m.mine
                                                                      ? 'rounded-br-md bg-emerald-600 text-white'
                                                                      : 'rounded-bl-md border border-border/50 bg-card text-foreground',
                                                                highlightId ===
                                                                    m.id &&
                                                                    'ring-2 ring-amber-400/80 ring-offset-2 ring-offset-background',
                                                            )}
                                                        >
                                                            {!m.mine &&
                                                            !deleted &&
                                                            m.user_name ? (
                                                                <p className="px-3.5 pt-2 text-[10px] font-semibold tracking-wide text-emerald-700 uppercase dark:text-emerald-300">
                                                                    {
                                                                        m.user_name
                                                                    }
                                                                </p>
                                                            ) : null}

                                                            {!deleted &&
                                                            m.reply_to ? (
                                                                <button
                                                                    type="button"
                                                                    className={cn(
                                                                        'mx-2 mt-2 block w-[calc(100%-1rem)] rounded-lg border-l-2 px-2.5 py-1.5 text-left text-xs',
                                                                        m.mine
                                                                            ? 'border-emerald-200/70 bg-emerald-700/40'
                                                                            : 'border-emerald-500/50 bg-muted/60',
                                                                    )}
                                                                    onClick={() =>
                                                                        jumpToMessage(
                                                                            m
                                                                                .reply_to!
                                                                                .id,
                                                                        )
                                                                    }
                                                                >
                                                                    <span className="font-semibold">
                                                                        {
                                                                            m
                                                                                .reply_to
                                                                                .user_name
                                                                        }
                                                                    </span>
                                                                    <span className="mt-0.5 line-clamp-2 block opacity-90">
                                                                        {
                                                                            m
                                                                                .reply_to
                                                                                .body
                                                                        }
                                                                    </span>
                                                                </button>
                                                            ) : null}

                                                            <div className="space-y-1.5 px-3.5 py-2">
                                                                {deleted ? (
                                                                    <p className="italic opacity-80">
                                                                        {t(
                                                                            'message_deleted',
                                                                        )}
                                                                    </p>
                                                                ) : m.body ? (
                                                                    <p className="wrap-break-word whitespace-pre-wrap">
                                                                        {
                                                                            m.body
                                                                        }
                                                                    </p>
                                                                ) : null}
                                                                {atts.map(
                                                                    (att) =>
                                                                        att.mime.startsWith(
                                                                            'image/',
                                                                        ) ? (
                                                                            <a
                                                                                key={
                                                                                    att.url
                                                                                }
                                                                                href={
                                                                                    att.url
                                                                                }
                                                                                target="_blank"
                                                                                rel="noreferrer"
                                                                                className="block overflow-hidden rounded-lg"
                                                                            >
                                                                                <img
                                                                                    src={
                                                                                        att.url
                                                                                    }
                                                                                    alt={
                                                                                        att.name
                                                                                    }
                                                                                    className="max-h-56 w-full object-cover"
                                                                                />
                                                                            </a>
                                                                        ) : (
                                                                            <a
                                                                                key={
                                                                                    att.url
                                                                                }
                                                                                href={
                                                                                    att.url
                                                                                }
                                                                                target="_blank"
                                                                                rel="noreferrer"
                                                                                className={cn(
                                                                                    'flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs',
                                                                                    m.mine
                                                                                        ? 'bg-white/15'
                                                                                        : 'bg-muted',
                                                                                )}
                                                                            >
                                                                                <FileText className="size-3.5 shrink-0" />
                                                                                <span className="truncate">
                                                                                    {
                                                                                        att.name
                                                                                    }
                                                                                </span>
                                                                            </a>
                                                                        ),
                                                                )}
                                                            </div>

                                                            <div
                                                                className={cn(
                                                                    'flex items-center gap-1 px-3.5 pb-1.5',
                                                                    m.mine
                                                                        ? 'justify-end text-emerald-100/90'
                                                                        : 'justify-end text-muted-foreground',
                                                                    deleted &&
                                                                        'text-muted-foreground',
                                                                )}
                                                            >
                                                                {!deleted &&
                                                                canManage ? (
                                                                    <>
                                                                        <button
                                                                            type="button"
                                                                            className={cn(
                                                                                'mr-auto rounded p-0.5 touch-manipulation',
                                                                                m.mine
                                                                                    ? 'hover:bg-emerald-700/50 active:bg-emerald-700/60'
                                                                                    : 'hover:bg-muted active:bg-muted',
                                                                            )}
                                                                            onClick={() => {
                                                                                setEditingMessage(
                                                                                    null,
                                                                                );
                                                                                setReplyTo(
                                                                                    {
                                                                                        id: m.id,
                                                                                        body:
                                                                                            m.body
                                                                                            || (atts[0]
                                                                                                ?.name
                                                                                                ?? ''),
                                                                                        user_id:
                                                                                            m.user_id,
                                                                                        user_name:
                                                                                            m.user_name
                                                                                            || t(
                                                                                                'you',
                                                                                            ),
                                                                                    },
                                                                                );
                                                                                composerRef.current?.focus();
                                                                            }}
                                                                            aria-label={t(
                                                                                'reply',
                                                                            )}
                                                                            title={t(
                                                                                'reply',
                                                                            )}
                                                                        >
                                                                            <Reply className="size-3.5" />
                                                                        </button>
                                                                        <Popover>
                                                                            <PopoverTrigger
                                                                                asChild
                                                                            >
                                                                                <button
                                                                                    type="button"
                                                                                    className={cn(
                                                                                        'rounded p-0.5 touch-manipulation',
                                                                                        m.mine
                                                                                            ? 'hover:bg-emerald-700/50 active:bg-emerald-700/60'
                                                                                            : 'hover:bg-muted active:bg-muted',
                                                                                    )}
                                                                                    aria-label={t(
                                                                                        'react',
                                                                                    )}
                                                                                >
                                                                                    <Smile className="size-3.5" />
                                                                                </button>
                                                                            </PopoverTrigger>
                                                                            <PopoverContent
                                                                                className="w-auto p-1.5"
                                                                                side="top"
                                                                                align="end"
                                                                            >
                                                                                <div className="flex gap-0.5">
                                                                                    {REACTION_EMOJIS.map(
                                                                                        (
                                                                                            emoji,
                                                                                        ) => (
                                                                                            <button
                                                                                                key={
                                                                                                    emoji
                                                                                                }
                                                                                                type="button"
                                                                                                className="rounded-md px-1.5 py-1 text-base hover:bg-muted"
                                                                                                onClick={() =>
                                                                                                    void toggleReaction(
                                                                                                        m,
                                                                                                        emoji,
                                                                                                    )
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    emoji
                                                                                                }
                                                                                            </button>
                                                                                        ),
                                                                                    )}
                                                                                </div>
                                                                            </PopoverContent>
                                                                        </Popover>
                                                                        <DropdownMenu>
                                                                            <DropdownMenuTrigger
                                                                                asChild
                                                                            >
                                                                                <button
                                                                                    type="button"
                                                                                    className={cn(
                                                                                        'rounded p-0.5 touch-manipulation',
                                                                                        m.mine
                                                                                            ? 'hover:bg-emerald-700/50 active:bg-emerald-700/60'
                                                                                            : 'hover:bg-muted active:bg-muted',
                                                                                    )}
                                                                                    aria-label={t(
                                                                                        'message_actions',
                                                                                    )}
                                                                                >
                                                                                    <MoreHorizontal className="size-3.5" />
                                                                                </button>
                                                                            </DropdownMenuTrigger>
                                                                            <DropdownMenuContent
                                                                                align="end"
                                                                                className="w-44"
                                                                            >
                                                                                <DropdownMenuItem
                                                                                    onSelect={() =>
                                                                                        void openForward(
                                                                                            m,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Forward className="size-4" />
                                                                                    {t(
                                                                                        'forward',
                                                                                    )}
                                                                                </DropdownMenuItem>
                                                                                {m.mine ? (
                                                                                    <>
                                                                                        <DropdownMenuItem
                                                                                            onSelect={() =>
                                                                                                startEditMessage(
                                                                                                    m,
                                                                                                )
                                                                                            }
                                                                                        >
                                                                                            <Pencil className="size-4" />
                                                                                            {t(
                                                                                                'edit',
                                                                                            )}
                                                                                        </DropdownMenuItem>
                                                                                        <DropdownMenuSeparator />
                                                                                        <DropdownMenuItem
                                                                                            className="text-destructive focus:text-destructive"
                                                                                            onSelect={() =>
                                                                                                setDeleteMessage(
                                                                                                    m,
                                                                                                )
                                                                                            }
                                                                                        >
                                                                                            <Trash2 className="size-4" />
                                                                                            {t(
                                                                                                'delete',
                                                                                            )}
                                                                                        </DropdownMenuItem>
                                                                                    </>
                                                                                ) : null}
                                                                            </DropdownMenuContent>
                                                                        </DropdownMenu>
                                                                    </>
                                                                ) : (
                                                                    <span className="mr-auto" />
                                                                )}
                                                                {m.edited_at &&
                                                                !deleted ? (
                                                                    <span className="text-[10px] opacity-80">
                                                                        {t(
                                                                            'edited',
                                                                        )}
                                                                    </span>
                                                                ) : null}
                                                                <p
                                                                    className={cn(
                                                                        'text-[10px]',
                                                                        deleted
                                                                            ? 'text-muted-foreground'
                                                                            : m.mine
                                                                              ? 'text-white/70'
                                                                              : 'text-muted-foreground',
                                                                    )}
                                                                >
                                                                    {formatListTime(
                                                                        m.created_at,
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        {!deleted &&
                                                        reactions.length >
                                                            0 ? (
                                                            <div
                                                                className={cn(
                                                                    'flex flex-wrap gap-1 px-1',
                                                                    m.mine
                                                                        ? 'justify-end'
                                                                        : 'justify-start',
                                                                )}
                                                            >
                                                                {reactions.map(
                                                                    (r) => (
                                                                        <button
                                                                            key={`${m.id}-${r.emoji}`}
                                                                            type="button"
                                                                            className={cn(
                                                                                'inline-flex items-center gap-1 rounded-full border px-1.5 py-0.5 text-[11px] transition-colors',
                                                                                r.reacted
                                                                                    ? 'border-emerald-500/50 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
                                                                                    : 'border-border/60 bg-card text-foreground hover:bg-muted/70',
                                                                            )}
                                                                            disabled={
                                                                                !canManage
                                                                            }
                                                                            onClick={() =>
                                                                                void toggleReaction(
                                                                                    m,
                                                                                    r.emoji as ReactionEmoji,
                                                                                )
                                                                            }
                                                                        >
                                                                            <span>
                                                                                {
                                                                                    r.emoji
                                                                                }
                                                                            </span>
                                                                            {r.count >
                                                                            1 ? (
                                                                                <span className="tabular-nums text-muted-foreground">
                                                                                    {
                                                                                        r.count
                                                                                    }
                                                                                </span>
                                                                            ) : null}
                                                                        </button>
                                                                    ),
                                                                )}
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    )}
                                    <div ref={bottomRef} />
                                </ChatMessageScroller>

                                {canManage ? (
                                    <form
                                        onSubmit={(e) => void sendMessage(e)}
                                        className="border-t border-border/60 bg-card/95 p-3 backdrop-blur-sm"
                                    >
                                        {editingMessage ? (
                                            <div className="mb-2 flex items-start gap-2 rounded-xl border border-amber-600/20 bg-amber-50/70 px-2.5 py-2 dark:bg-amber-950/30">
                                                <Pencil className="mt-0.5 size-3.5 shrink-0 text-amber-700 dark:text-amber-300" />
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-[10px] font-semibold text-amber-800 dark:text-amber-200">
                                                        {t('edit_banner')}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {editingMessage.body}
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-7"
                                                    onClick={cancelEditing}
                                                    aria-label={t('edit_cancel')}
                                                >
                                                    <X className="size-3.5" />
                                                </Button>
                                            </div>
                                        ) : null}

                                        {replyTo && !editingMessage ? (
                                            <div className="mb-2 flex items-start gap-2 rounded-xl border border-emerald-600/20 bg-emerald-50/70 px-2.5 py-2 dark:bg-emerald-950/30">
                                                <Reply className="mt-0.5 size-3.5 shrink-0 text-emerald-700 dark:text-emerald-300" />
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-[10px] font-semibold text-emerald-800 dark:text-emerald-200">
                                                        {t('reply_to', {
                                                            name: replyTo.user_name,
                                                        })}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {replyTo.body}
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-7"
                                                    onClick={() =>
                                                        setReplyTo(null)
                                                    }
                                                    aria-label={t(
                                                        'reply_cancel',
                                                    )}
                                                >
                                                    <X className="size-3.5" />
                                                </Button>
                                            </div>
                                        ) : null}

                                        {files.length > 0 && !editingMessage ? (
                                            <div className="mb-2 flex flex-wrap gap-2">
                                                {files.map((file, idx) => (
                                                    <div
                                                        key={`${file.name}-${idx}`}
                                                        className="flex max-w-48 items-center gap-2 rounded-xl border border-border/60 bg-muted/40 px-2 py-1.5"
                                                    >
                                                        <FileText className="size-3.5 shrink-0 text-muted-foreground" />
                                                        <span className="truncate text-[11px]">
                                                            {file.name}
                                                        </span>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-6 shrink-0"
                                                            onClick={() =>
                                                                setFiles((prev) =>
                                                                    prev.filter(
                                                                        (_, i) =>
                                                                            i !==
                                                                            idx,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <X className="size-3" />
                                                        </Button>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : null}

                                        <div className="flex items-end gap-1.5">
                                            <input
                                                ref={fileRef}
                                                type="file"
                                                multiple
                                                className="hidden"
                                                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,image/*"
                                                onChange={(e) => {
                                                    const picked = Array.from(
                                                        e.target.files ?? [],
                                                    );
                                                    setFiles((prev) =>
                                                        [
                                                            ...prev,
                                                            ...picked,
                                                        ].slice(
                                                            0,
                                                            MAX_ATTACHMENTS,
                                                        ),
                                                    );
                                                    e.target.value = '';
                                                }}
                                            />
                                            {!editingMessage ? (
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-9 shrink-0 text-muted-foreground"
                                                    onClick={() =>
                                                        fileRef.current?.click()
                                                    }
                                                    disabled={
                                                        files.length >=
                                                        MAX_ATTACHMENTS
                                                    }
                                                    aria-label={t('attach')}
                                                >
                                                    <Paperclip className="size-4" />
                                                </Button>
                                            ) : null}
                                            <Popover
                                                open={emojiOpen}
                                                onOpenChange={setEmojiOpen}
                                            >
                                                <PopoverTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-9 shrink-0 text-muted-foreground"
                                                        aria-label={t('emoji')}
                                                    >
                                                        <Smile className="size-4" />
                                                    </Button>
                                                </PopoverTrigger>
                                                <PopoverContent
                                                    className="w-64 p-2"
                                                    align="start"
                                                    side="top"
                                                >
                                                    <div className="grid grid-cols-8 gap-0.5">
                                                        {EMOJIS.map((emoji) => (
                                                            <button
                                                                key={emoji}
                                                                type="button"
                                                                className="rounded-md p-1 text-lg hover:bg-muted"
                                                                onClick={() =>
                                                                    insertEmoji(
                                                                        emoji,
                                                                    )
                                                                }
                                                            >
                                                                {emoji}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </PopoverContent>
                                            </Popover>
                                            <Textarea
                                                ref={composerRef}
                                                value={composer}
                                                onChange={(e) =>
                                                    setComposer(e.target.value)
                                                }
                                                placeholder={
                                                    editingMessage
                                                        ? t(
                                                              'composer_edit_placeholder',
                                                          )
                                                        : t(
                                                              'composer_placeholder',
                                                          )
                                                }
                                                rows={1}
                                                className="max-h-32 min-h-11 flex-1 resize-none"
                                                onKeyDown={(e) => {
                                                    if (
                                                        e.key === 'Enter' &&
                                                        !e.shiftKey
                                                    ) {
                                                        e.preventDefault();
                                                        void sendMessage();
                                                    }
                                                }}
                                            />
                                            <Button
                                                type="submit"
                                                size="icon"
                                                disabled={
                                                    sending ||
                                                    (editingMessage
                                                        ? !composer.trim()
                                                        : !composer.trim() &&
                                                          files.length === 0)
                                                }
                                                className="size-10 shrink-0 bg-emerald-600 hover:bg-emerald-700"
                                            >
                                                {sending ? (
                                                    <Loader2 className="size-4 animate-spin" />
                                                ) : editingMessage ? (
                                                    <Check className="size-4" />
                                                ) : (
                                                    <SendHorizonal className="size-4" />
                                                )}
                                                <span className="sr-only">
                                                    {editingMessage
                                                        ? t('save_edit')
                                                        : t('send')}
                                                </span>
                                            </Button>
                                        </div>
                                        {!editingMessage ? (
                                            <p className="mt-1.5 text-[10px] text-muted-foreground">
                                                {t('attach_hint')}
                                            </p>
                                        ) : null}
                                    </form>
                                ) : null}
                            </>
                        )}
                    </section>
                </div>
            </ChatShell>

            <Dialog open={broadcastOpen} onOpenChange={setBroadcastOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('broadcast_title')}</DialogTitle>
                        <DialogDescription>
                            {t('broadcast_description', {
                                count: tenants.length,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <Textarea
                        value={broadcastBody}
                        onChange={(e) => setBroadcastBody(e.target.value)}
                        placeholder={t('broadcast_placeholder')}
                        rows={5}
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setBroadcastOpen(false)}
                            disabled={broadcasting}
                        >
                            {t('cancel')}
                        </Button>
                        <Button
                            type="button"
                            className="bg-emerald-600 hover:bg-emerald-700"
                            onClick={() => void runBroadcast()}
                            disabled={broadcasting || !broadcastBody.trim()}
                        >
                            {broadcasting ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : null}
                            {t('broadcast_confirm', {
                                count: tenants.length,
                            })}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={!!deleteMessage}
                onOpenChange={(open) => {
                    if (!open) setDeleteMessage(null);
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('delete_confirm')}</DialogTitle>
                        <DialogDescription>
                            {t('delete_confirm_hint')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteMessage(null)}
                            disabled={deleting}
                        >
                            {t('cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => void confirmDelete()}
                            disabled={deleting}
                        >
                            {deleting ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : null}
                            {t('delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={!!forwardMessage}
                onOpenChange={(open) => {
                    if (!open) {
                        setForwardMessage(null);
                        setForwardTargetId('');
                        setForwardTargets([]);
                    }
                }}
            >
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                    <DialogHeader className="border-b border-border/60 px-5 py-4">
                        <DialogTitle className="text-base">
                            {t('forward_title')}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {t('forward_hint')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-72 space-y-1 overflow-y-auto p-3">
                        {forwardLoading ? (
                            <p className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" />
                                {t('loading')}
                            </p>
                        ) : forwardTargets.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                {t('forward_empty')}
                            </p>
                        ) : (
                            forwardTargets.map((c) => (
                                <button
                                    key={c.id}
                                    type="button"
                                    onClick={() => setForwardTargetId(c.id)}
                                    className={cn(
                                        'flex w-full items-center gap-3 rounded-lg px-2.5 py-2.5 text-left transition-colors',
                                        forwardTargetId === c.id
                                            ? 'bg-emerald-50 dark:bg-emerald-950/40'
                                            : 'hover:bg-muted/60',
                                    )}
                                >
                                    <Avatar className="size-9 border border-border/50">
                                        <AvatarFallback className="text-xs font-semibold">
                                            {c.type === 'group' ? (
                                                <Users className="size-3.5" />
                                            ) : (
                                                c.title.slice(0, 2).toUpperCase()
                                            )}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                        {c.title}
                                    </span>
                                    {forwardTargetId === c.id ? (
                                        <Check className="size-4 shrink-0 text-emerald-600" />
                                    ) : null}
                                </button>
                            ))
                        )}
                    </div>
                    <DialogFooter className="gap-2 border-t border-border/60 bg-muted/20 px-4 py-3 sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setForwardMessage(null);
                                setForwardTargetId('');
                            }}
                            disabled={forwarding}
                        >
                            {t('cancel')}
                        </Button>
                        <Button
                            type="button"
                            className="bg-emerald-600 hover:bg-emerald-700"
                            disabled={!forwardTargetId || forwarding}
                            onClick={() => void submitForward()}
                        >
                            {forwarding ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : null}
                            {t('forward_submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PlataformaChatSoportePage.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Chat soporte', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
