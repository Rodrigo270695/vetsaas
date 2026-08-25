import { Head, router } from '@inertiajs/react';
import {
    Building2,
    ChevronLeft,
    FileText,
    Loader2,
    Megaphone,
    Paperclip,
    Search,
    SendHorizonal,
    Smile,
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
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';

const ROUTE_URL = '/plataforma/chat-soporte';
const MAX_ATTACHMENTS = 5;
const EMOJIS = [
    '😀', '😁', '😂', '🙂', '😉', '😊', '😍', '🤩',
    '😎', '🤔', '😢', '😭', '😤', '🙌', '👍', '👎',
    '👏', '🙏', '💪', '🔥', '✨', '✅', '❌', '⚠️',
    '📌', '📎', '📷', '🐶', '🐱', '💉', '💊', '🩺',
];

type PlanFilter = 'all' | 'free' | 'paid';

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
    } | null;
};

type ChatAttachment = {
    url: string;
    name: string;
    mime: string;
    size?: number;
};

type ChatMessage = {
    id: string;
    body: string;
    mine: boolean;
    user_name?: string;
    created_at: string | null;
    attachments?: ChatAttachment[];
    attachment?: ChatAttachment | null;
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

const initials = (name: string): string => {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
};

const messageAttachments = (m: ChatMessage): ChatAttachment[] => {
    if (m.attachments && m.attachments.length > 0) return m.attachments;
    if (m.attachment) return [m.attachment];
    return [];
};

export default function PlataformaChatSoportePage({
    tenants: initialTenants,
    filters,
}: Props) {
    const { t } = useTranslation('plataforma-chat-soporte');
    const { can } = usePermission();
    const canManage = can('plataforma-chat-soporte.manage');

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

    const bottomRef = useRef<HTMLDivElement | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);
    const composerRef = useRef<HTMLTextAreaElement | null>(null);
    const messageRefs = useRef<Map<string, HTMLDivElement>>(new Map());
    const selectedIdRef = useRef<string | null>(null);
    const lastMessageIdRef = useRef<string | null>(null);
    selectedIdRef.current = selectedId;
    lastMessageIdRef.current =
        messages.length > 0 ? (messages[messages.length - 1]?.id ?? null) : null;

    useEffect(() => {
        setTenants(initialTenants);
    }, [initialTenants]);

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
        setSearchOpen(false);
        setThreadQuery('');
        setLoadingThread(true);
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
        if (!selectedId) return;

        const tick = async () => {
            const id = selectedIdRef.current;
            if (!id) return;
            const last = lastMessageIdRef.current;
            try {
                const url = last
                    ? `/plataforma/chat-soporte/tenants/${id}/messages?after=${encodeURIComponent(last)}`
                    : `/plataforma/chat-soporte/tenants/${id}/messages`;
                const data = await apiJson<{ messages: ChatMessage[] }>(url);
                if (!data.messages?.length) return;
                if (last) {
                    setMessages((prev) => {
                        const seen = new Set(prev.map((m) => m.id));
                        const next = data.messages.filter((m) => !seen.has(m.id));
                        return next.length ? [...prev, ...next] : prev;
                    });
                } else {
                    setMessages(data.messages);
                }
                requestAnimationFrame(() => {
                    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
                });
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

    const sendMessage = async (e?: FormEvent) => {
        e?.preventDefault();
        if (!selectedId || !canManage) return;
        const body = composer.trim();
        if ((!body && files.length === 0) || sending) return;

        setSending(true);
        try {
            const fd = new FormData();
            if (body) fd.append('body', body);
            files.forEach((file) => fd.append('attachments[]', file));

            const data = await apiJson<{ message: ChatMessage }>(
                `/plataforma/chat-soporte/tenants/${selectedId}/messages`,
                { method: 'POST', formData: fd },
            );
            setComposer('');
            setFiles([]);
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

    const closeThreadMobile = () => {
        setSelectedId(null);
        setMessages([]);
        setComposer('');
        setFiles([]);
        setSearchOpen(false);
        setThreadQuery('');
        setMobileListOpen(true);
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
                                                        : 'border-l-transparent hover:bg-background/80',
                                                )}
                                            >
                                                <Avatar className="mt-0.5 size-11 border border-border/50 shadow-sm lg:size-10">
                                                    <AvatarFallback className="bg-sky-100 text-xs font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200">
                                                        <Building2 className="size-3.5" />
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="truncate text-sm font-medium">
                                                            {row.nombre}
                                                        </span>
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
                                    <Building2 className="size-7" aria-hidden />
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
                                            className="size-9 shrink-0 lg:hidden"
                                            onClick={closeThreadMobile}
                                            aria-label="Volver"
                                        >
                                            <ChevronLeft className="size-5" />
                                        </Button>
                                        <Avatar className="size-9 border border-border/50 shadow-sm sm:size-10">
                                            <AvatarFallback className="bg-sky-100 text-xs font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200">
                                                {initials(selected.nombre)}
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
                                            const atts = messageAttachments(m);
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
                                                        'flex',
                                                        m.mine
                                                            ? 'justify-end'
                                                            : 'justify-start',
                                                        highlightId === m.id &&
                                                            'animate-pulse',
                                                    )}
                                                >
                                                    <div
                                                        className={cn(
                                                            'max-w-[min(85%,28rem)] overflow-hidden rounded-2xl text-sm shadow-sm',
                                                            m.mine
                                                                ? 'rounded-br-md bg-emerald-600 text-white'
                                                                : 'rounded-bl-md border border-border/50 bg-card text-foreground',
                                                        )}
                                                    >
                                                        <div className="space-y-1.5 px-3.5 py-2">
                                                            {!m.mine &&
                                                            m.user_name ? (
                                                                <p className="text-[11px] font-medium text-emerald-700 dark:text-emerald-300">
                                                                    {m.user_name}
                                                                </p>
                                                            ) : null}
                                                            {m.body ? (
                                                                <p className="wrap-break-word whitespace-pre-wrap">
                                                                    {m.body}
                                                                </p>
                                                            ) : null}
                                                            {atts.map((att) =>
                                                                att.mime.startsWith(
                                                                    'image/',
                                                                ) ? (
                                                                    <a
                                                                        key={att.url}
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
                                                                        key={att.url}
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
                                                            <p
                                                                className={cn(
                                                                    'text-right text-[10px]',
                                                                    m.mine
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
                                        {files.length > 0 ? (
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
                                                placeholder={t(
                                                    'composer_placeholder',
                                                )}
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
                                                    (!composer.trim() &&
                                                        files.length === 0)
                                                }
                                                className="size-10 shrink-0 bg-emerald-600 hover:bg-emerald-700"
                                            >
                                                {sending ? (
                                                    <Loader2 className="size-4 animate-spin" />
                                                ) : (
                                                    <SendHorizonal className="size-4" />
                                                )}
                                                <span className="sr-only">
                                                    {t('send')}
                                                </span>
                                            </Button>
                                        </div>
                                        <p className="mt-1.5 text-[10px] text-muted-foreground">
                                            {t('attach_hint')}
                                        </p>
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
                            Cancelar
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
