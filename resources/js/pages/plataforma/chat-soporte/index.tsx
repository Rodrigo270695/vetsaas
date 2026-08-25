import { Head, router } from '@inertiajs/react';
import { Loader2, Megaphone, MessageSquare, Search, SendHorizonal } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState, PageHeader } from '@/components/data-page';
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

    const bottomRef = useRef<HTMLDivElement | null>(null);
    const selectedIdRef = useRef<string | null>(null);
    const lastMessageIdRef = useRef<string | null>(null);
    selectedIdRef.current = selectedId;
    lastMessageIdRef.current =
        messages.length > 0 ? messages[messages.length - 1]?.id ?? null : null;

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
            '/plataforma/chat-soporte',
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
                const data = await apiJson<{
                    messages: ChatMessage[];
                }>(url);
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

    return (
        <AppLayout>
            <Head title={t('title')} />
            <div className="flex h-[calc(100dvh-4rem)] flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('subtitle')}
                    action={
                        canManage ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setBroadcastOpen(true)}
                                disabled={tenants.length === 0}
                            >
                                <Megaphone className="size-4" />
                                {t('broadcast')}
                            </Button>
                        ) : null
                    }
                />

                <div className="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(280px,360px)_1fr]">
                    <aside className="flex min-h-0 flex-col overflow-hidden rounded-xl border border-border/70 bg-card">
                        <div className="space-y-3 border-b border-border/60 p-3">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
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
                                    className="pl-8"
                                />
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {planOptions.map((opt) => (
                                    <Button
                                        key={opt.value}
                                        type="button"
                                        size="sm"
                                        variant={plan === opt.value ? 'default' : 'outline'}
                                        className="h-7 rounded-full px-3 text-xs"
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
                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {tenants.length === 0 ? (
                                <EmptyState
                                    title={t('empty_tenants')}
                                    className="m-4"
                                />
                            ) : (
                                <ul className="divide-y divide-border/50">
                                    {tenants.map((row) => {
                                        const active = row.id === selectedId;
                                        return (
                                            <li key={row.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => void openTenant(row.id)}
                                                    className={cn(
                                                        'flex w-full flex-col gap-1 px-3 py-3 text-left transition-colors',
                                                        active
                                                            ? 'bg-emerald-50/90 dark:bg-emerald-950/40'
                                                            : 'hover:bg-muted/50',
                                                    )}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span className="truncate text-sm font-medium">
                                                            {row.nombre}
                                                        </span>
                                                        {row.is_free === true ? (
                                                            <Badge
                                                                variant="secondary"
                                                                className="h-5 shrink-0 px-1.5 text-[10px]"
                                                            >
                                                                {t('badge_free')}
                                                            </Badge>
                                                        ) : row.is_free === false ? (
                                                            <Badge className="h-5 shrink-0 bg-sky-600 px-1.5 text-[10px] hover:bg-sky-600">
                                                                {t('badge_paid')}
                                                            </Badge>
                                                        ) : null}
                                                        <span className="ml-auto shrink-0 text-[10px] text-muted-foreground">
                                                            {formatListTime(
                                                                row.thread?.last_message_at ??
                                                                    null,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {row.thread?.last_preview ||
                                                            row.slug}
                                                    </p>
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>
                    </aside>

                    <section className="flex min-h-0 flex-col overflow-hidden rounded-xl border border-border/70 bg-card">
                        {!selected ? (
                            <EmptyState
                                icon={MessageSquare}
                                title={t('empty_thread')}
                                className="m-auto"
                            />
                        ) : (
                            <>
                                <header className="flex items-center gap-3 border-b border-border/60 px-4 py-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold">
                                            {selected.nombre}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {selected.plan_nombre ?? selected.slug}
                                        </p>
                                    </div>
                                    {selected.is_free === true ? (
                                        <Badge variant="secondary">{t('badge_free')}</Badge>
                                    ) : selected.is_free === false ? (
                                        <Badge className="bg-sky-600 hover:bg-sky-600">
                                            {t('badge_paid')}
                                        </Badge>
                                    ) : null}
                                </header>

                                <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
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
                                                    m.mine ? 'justify-end' : 'justify-start',
                                                )}
                                            >
                                                <div
                                                    className={cn(
                                                        'max-w-[85%] rounded-2xl px-3 py-2 text-sm shadow-sm',
                                                        m.mine
                                                            ? 'bg-emerald-600 text-white'
                                                            : 'bg-muted text-foreground',
                                                    )}
                                                >
                                                    {!m.mine && m.user_name ? (
                                                        <p className="mb-0.5 text-[11px] font-medium opacity-80">
                                                            {m.user_name}
                                                        </p>
                                                    ) : null}
                                                    <p className="wrap-break-word whitespace-pre-wrap">
                                                        {m.body}
                                                    </p>
                                                    <p
                                                        className={cn(
                                                            'mt-1 text-[10px]',
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
                                        className="flex items-end gap-2 border-t border-border/60 p-3"
                                    >
                                        <Textarea
                                            value={composer}
                                            onChange={(e) => setComposer(e.target.value)}
                                            placeholder={t('composer_placeholder')}
                                            rows={2}
                                            className="min-h-11 resize-none"
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' && !e.shiftKey) {
                                                    e.preventDefault();
                                                    void sendMessage();
                                                }
                                            }}
                                        />
                                        <Button
                                            type="submit"
                                            size="icon"
                                            disabled={sending || !composer.trim()}
                                            className="size-10 shrink-0"
                                        >
                                            {sending ? (
                                                <Loader2 className="size-4 animate-spin" />
                                            ) : (
                                                <SendHorizonal className="size-4" />
                                            )}
                                            <span className="sr-only">{t('send')}</span>
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
                            {t('broadcast_description', { count: tenants.length })}
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
        </AppLayout>
    );
}
