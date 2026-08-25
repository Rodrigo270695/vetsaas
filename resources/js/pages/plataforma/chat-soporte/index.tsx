import { Head, router } from '@inertiajs/react';
import {
    Building2,
    ChevronLeft,
    Loader2,
    Megaphone,
    MessagesSquare,
    Search,
    SendHorizonal,
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
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';

const ROUTE_URL = '/plataforma/chat-soporte';

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

type ChatMessage = {
    id: string;
    body: string;
    mine: boolean;
    user_name?: string;
    created_at: string | null;
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
    init?: RequestInit & { json?: Record<string, unknown> },
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': readXsrfToken(),
        ...(init?.headers as Record<string, string> | undefined),
    };

    let body = init?.body;
    if (init?.json !== undefined) {
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

export default function PlataformaChatSoportePage({
    tenants: initialTenants,
    filters,
}: Props) {
    const { t } = useTranslation('plataforma-chat-soporte');
    const { can } = usePermission();
    const canManage = can('plataforma-chat-soporte.manage');

    const [tenants, setTenants] = useState(initialTenants);
    const [plan, setPlan] = useState<PlanFilter>(filters.plan ?? 'all');
    const [search, setSearch] = useState(filters.q ?? '');
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [loadingThread, setLoadingThread] = useState(false);
    const [composer, setComposer] = useState('');
    const [sending, setSending] = useState(false);
    const [broadcastOpen, setBroadcastOpen] = useState(false);
    const [broadcastBody, setBroadcastBody] = useState('');
    const [broadcasting, setBroadcasting] = useState(false);
    const [mobileListOpen, setMobileListOpen] = useState(true);

    const bottomRef = useRef<HTMLDivElement | null>(null);
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

    const planOptions: { value: PlanFilter; label: string }[] = [
        { value: 'all', label: t('plan_all') },
        { value: 'free', label: t('plan_free') },
        { value: 'paid', label: t('plan_paid') },
    ];

    const applyFilters = (nextPlan: PlanFilter, nextQ: string) => {
        router.get(
            ROUTE_URL,
            { plan: nextPlan, q: nextQ || undefined },
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

    const sendMessage = async (e?: FormEvent) => {
        e?.preventDefault();
        if (!selectedId || !canManage) return;
        const body = composer.trim();
        if (!body || sending) return;

        setSending(true);
        try {
            const data = await apiJson<{ message: ChatMessage }>(
                `/plataforma/chat-soporte/tenants/${selectedId}/messages`,
                { method: 'POST', json: { body } },
            );
            setComposer('');
            if (data.message) {
                setMessages((prev) =>
                    prev.some((m) => m.id === data.message.id)
                        ? prev
                        : [...prev, data.message],
                );
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
                                      last_preview: body.slice(0, 280),
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
        setMobileListOpen(true);
    };

    return (
        <>
            <Head title={t('title')} />

            <div
                data-fixed-viewport
                className="flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-card max-lg:rounded-none max-lg:border-0 max-lg:shadow-none lg:m-3 lg:rounded-2xl lg:border lg:border-border/60 lg:shadow-sm"
            >
                <div
                    className={cn(
                        'flex items-center justify-between gap-3 border-b border-border/60 bg-linear-to-r from-emerald-50/90 via-card to-teal-50/40 px-4 py-3 dark:from-emerald-950/40 dark:via-card dark:to-teal-950/20',
                        selected && 'max-lg:hidden',
                    )}
                >
                    <div className="flex min-w-0 items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30">
                            <MessagesSquare className="size-4" aria-hidden />
                        </span>
                        <div className="min-w-0">
                            <h1 className="truncate text-base font-semibold tracking-tight">
                                {t('title')}
                            </h1>
                            <p className="truncate text-xs text-muted-foreground max-sm:hidden">
                                {t('subtitle')}
                            </p>
                        </div>
                    </div>

                    {canManage ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-8 shrink-0 gap-1.5 text-xs"
                            onClick={() => setBroadcastOpen(true)}
                            disabled={tenants.length === 0}
                        >
                            <Megaphone className="size-3.5" />
                            <span className="hidden sm:inline">{t('broadcast')}</span>
                        </Button>
                    ) : null}
                </div>

                <div className="relative min-h-0 flex-1 overflow-hidden lg:grid lg:grid-cols-[minmax(17rem,21rem)_1fr]">
                    <button
                        type="button"
                        aria-label="Cerrar lista"
                        tabIndex={mobileListOpen && selected ? 0 : -1}
                        onClick={() => setMobileListOpen(false)}
                        className={cn(
                            'absolute inset-0 z-30 bg-slate-950/40 backdrop-blur-[3px] transition-opacity duration-500 lg:pointer-events-none lg:hidden',
                            selected && mobileListOpen
                                ? 'opacity-100'
                                : 'pointer-events-none opacity-0',
                        )}
                    />

                    <aside
                        className={cn(
                            'z-40 flex min-h-0 flex-col bg-muted/20 lg:relative lg:z-auto lg:translate-x-0 lg:border-r lg:border-border/60 lg:shadow-none',
                            'max-lg:absolute max-lg:inset-y-0 max-lg:left-0 max-lg:bg-card max-lg:transition-transform max-lg:duration-500 max-lg:will-change-transform',
                            !selected && 'max-lg:inset-0 max-lg:w-full max-lg:translate-x-0',
                            selected &&
                                'max-lg:w-[min(20.5rem,82vw)] max-lg:border-r max-lg:border-border/50 max-lg:shadow-[12px_0_40px_-12px_rgba(15,23,42,0.35)]',
                            selected &&
                                (mobileListOpen
                                    ? 'max-lg:translate-x-0'
                                    : 'max-lg:translate-x-[-105%]'),
                        )}
                    >
                        <div className="flex items-center gap-2 border-b border-border/50 px-3 py-3 lg:hidden">
                            <span className="flex size-8 items-center justify-center rounded-lg bg-emerald-600/10 text-emerald-700 dark:text-emerald-300">
                                <MessagesSquare className="size-3.5" aria-hidden />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold">
                                    {t('title')}
                                </p>
                                <p className="truncate text-[10px] text-muted-foreground">
                                    {tenants.length} clínicas
                                </p>
                            </div>
                            {selected ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 shrink-0"
                                    onClick={() => setMobileListOpen(false)}
                                >
                                    <X className="size-4" />
                                </Button>
                            ) : null}
                        </div>

                        <div className="space-y-2 border-b border-border/50 p-3">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            applyFilters(plan, search.trim());
                                        }
                                    }}
                                    onBlur={() => {
                                        if (search.trim() !== (filters.q ?? '')) {
                                            applyFilters(plan, search.trim());
                                        }
                                    }}
                                    placeholder={t('search_placeholder')}
                                    className="h-9 border-border/60 bg-background/80 pl-8 text-sm"
                                />
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {planOptions.map((opt) => (
                                    <Button
                                        key={opt.value}
                                        type="button"
                                        size="sm"
                                        variant={
                                            plan === opt.value ? 'default' : 'outline'
                                        }
                                        className={cn(
                                            'h-7 rounded-full px-3 text-xs',
                                            plan === opt.value &&
                                                'bg-emerald-600 hover:bg-emerald-700',
                                        )}
                                        onClick={() => {
                                            setPlan(opt.value);
                                            applyFilters(opt.value, search.trim());
                                        }}
                                    >
                                        {opt.label}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                            {tenants.length === 0 ? (
                                <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                                    {t('empty_tenants')}
                                </p>
                            ) : (
                                <ul className="divide-y divide-border/40">
                                    {tenants.map((row) => {
                                        const active = row.id === selectedId;
                                        return (
                                            <li key={row.id}>
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
                        </div>
                    </aside>

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
                                    <MessagesSquare className="size-7" aria-hidden />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold">
                                        {t('empty_thread')}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <>
                                <header className="flex items-center gap-2 border-b border-border/60 bg-card/90 px-2 py-2.5 backdrop-blur-md sm:gap-3 sm:px-4 sm:py-3">
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
                                            {selected.plan_nombre ?? selected.slug}
                                        </p>
                                    </div>
                                    {selected.is_free === true ? (
                                        <Badge variant="secondary" className="rounded-full">
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
                                        className="size-8 shrink-0 lg:hidden"
                                        onClick={() => setMobileListOpen(true)}
                                        aria-label="Lista"
                                    >
                                        <Building2 className="size-4" />
                                    </Button>
                                </header>

                                <div className="min-h-0 flex-1 space-y-2.5 overflow-y-auto overscroll-contain px-3 py-4 sm:px-4">
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
                                        messages.map((m) => (
                                            <div
                                                key={m.id}
                                                className={cn(
                                                    'flex',
                                                    m.mine
                                                        ? 'justify-end'
                                                        : 'justify-start',
                                                )}
                                            >
                                                <div
                                                    className={cn(
                                                        'max-w-[min(85%,28rem)] rounded-2xl px-3.5 py-2 text-sm shadow-sm',
                                                        m.mine
                                                            ? 'rounded-br-md bg-emerald-600 text-white'
                                                            : 'rounded-bl-md border border-border/50 bg-card text-foreground',
                                                    )}
                                                >
                                                    {!m.mine && m.user_name ? (
                                                        <p className="mb-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300">
                                                            {m.user_name}
                                                        </p>
                                                    ) : null}
                                                    <p className="wrap-break-word whitespace-pre-wrap">
                                                        {m.body}
                                                    </p>
                                                    <p
                                                        className={cn(
                                                            'mt-1 text-right text-[10px]',
                                                            m.mine
                                                                ? 'text-white/70'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {formatListTime(m.created_at)}
                                                    </p>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                    <div ref={bottomRef} />
                                </div>

                                {canManage ? (
                                    <form
                                        onSubmit={(e) => void sendMessage(e)}
                                        className="flex items-end gap-2 border-t border-border/60 bg-card/95 p-3 backdrop-blur-sm"
                                    >
                                        <Textarea
                                            value={composer}
                                            onChange={(e) =>
                                                setComposer(e.target.value)
                                            }
                                            placeholder={t('composer_placeholder')}
                                            rows={1}
                                            className="max-h-32 min-h-11 resize-none"
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
                                            disabled={sending || !composer.trim()}
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
                                    </form>
                                ) : null}
                            </>
                        )}
                    </section>
                </div>
            </div>

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
                            {t('broadcast_confirm', { count: tenants.length })}
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
