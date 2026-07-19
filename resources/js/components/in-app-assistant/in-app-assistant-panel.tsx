import { router, usePage } from '@inertiajs/react';
import { ArrowUpRight, Bot, Loader2, SendHorizontal, Sparkles, Trash2, UserRound } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { resolveAssistantPageContext } from '@/components/in-app-assistant/resolve-page-context';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type ChatRole = 'user' | 'assistant';

type UiAction = {
    type: 'navigate';
    url: string;
    label: string;
};

type ChatMessage = {
    id: string;
    role: ChatRole;
    content: string;
    actions?: UiAction[];
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function AssistantRichText({ text }: { text: string }) {
    const parts = text.split(/(\*\*[^*]+\*\*)/g);

    return (
        <>
            {parts.map((part, index) => {
                const bold = part.match(/^\*\*([^*]+)\*\*$/);
                if (bold) {
                    return (
                        <strong key={index} className="font-semibold">
                            {bold[1]}
                        </strong>
                    );
                }

                return <span key={index}>{part}</span>;
            })}
        </>
    );
}

export function InAppAssistantPanel({ open, onOpenChange }: Props) {
    const { t } = useTranslation('in-app-assistant');
    const page = usePage();
    const { in_app_assistant } = page.props;
    const scope = in_app_assistant?.scope === 'platform' ? 'platform' : 'clinic';
    const isUnlimited =
        in_app_assistant?.unlimited === true || scope === 'platform';
    const pacientePropId = (page.props as { paciente?: { id?: string } }).paciente?.id;
    const pageContext = useMemo(
        () => resolveAssistantPageContext(page),
        [page.url, page.component, pacientePropId],
    );

    const [configured, setConfigured] = useState(in_app_assistant?.configured === true);
    const [usage, setUsage] = useState<{
        limit: number | null;
        used: number;
        remaining: number | null;
        unlimited: boolean;
    } | null>(isUnlimited ? { limit: null, used: 0, remaining: null, unlimited: true } : null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const bottomRef = useRef<HTMLDivElement | null>(null);
    const inputRef = useRef<HTMLTextAreaElement | null>(null);

    useEffect(() => {
        setConfigured(in_app_assistant?.configured === true);
    }, [in_app_assistant?.configured]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const timer = window.setTimeout(() => inputRef.current?.focus(), 280);

        void fetch('/asistente/status', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    return;
                }
                const body = (await res.json()) as {
                    configured?: boolean;
                    usage?: {
                        limit?: number | null;
                        used?: number;
                        remaining?: number | null;
                        unlimited?: boolean;
                    };
                };
                if (typeof body.configured === 'boolean') {
                    setConfigured(body.configured);
                }
                if (body.usage) {
                    const unlimited = body.usage.unlimited === true || body.usage.limit == null;
                    setUsage({
                        limit: unlimited ? null : Number(body.usage.limit ?? 0),
                        used: Number(body.usage.used ?? 0),
                        remaining: unlimited ? null : Number(body.usage.remaining ?? 0),
                        unlimited,
                    });
                }
            })
            .catch(() => {
                // Mantener el estado de Inertia si el status falla.
            });

        return () => window.clearTimeout(timer);
    }, [open]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages, sending, open]);

    const suggestions = useMemo(() => {
        if (scope === 'platform') {
            return [
                t('panel.suggestions.platform_pending'),
                t('panel.suggestions.platform_risk'),
                t('panel.suggestions.platform_summary'),
                t('panel.suggestions.platform_failed'),
            ];
        }
        if (pageContext.paciente_id) {
            return [
                t('panel.suggestions.query_this_patient'),
                t('panel.suggestions.query_alerts'),
                t('panel.suggestions.nav_vacunas'),
            ];
        }
        return [
            t('panel.suggestions.query_agenda'),
            t('panel.suggestions.nav_vacunas'),
            t('panel.suggestions.query_alerts'),
            t('panel.suggestions.nav_caja'),
        ];
    }, [t, pageContext.paciente_id, scope]);

    const limitReached =
        !isUnlimited &&
        usage !== null &&
        usage.unlimited !== true &&
        typeof usage.remaining === 'number' &&
        usage.remaining <= 0;

    const goTo = (url: string) => {
        onOpenChange(false);
        router.visit(url);
    };

    const sendMessage = async (raw: string) => {
        const message = raw.trim();
        if (message === '' || sending || !configured || limitReached) {
            return;
        }

        setError(null);
        setDraft('');

        const history = messages.map(({ role, content }) => ({ role, content }));
        const userMsg: ChatMessage = {
            id: `u-${Date.now()}`,
            role: 'user',
            content: message,
        };
        setMessages((prev) => [...prev, userMsg]);
        setSending(true);

        // Mantener el foco en el campo para poder seguir escribiendo.
        requestAnimationFrame(() => inputRef.current?.focus());

        try {
            const res = await fetch('/asistente/chat', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message, history, context: pageContext }),
            });

            const body = (await res.json()) as {
                reply?: string;
                message?: string;
                actions?: UiAction[];
                usage?: {
                    limit?: number | null;
                    used?: number;
                    remaining?: number | null;
                    unlimited?: boolean;
                };
            };

            if (body.usage) {
                const unlimited = body.usage.unlimited === true || body.usage.limit == null;
                setUsage({
                    limit: unlimited ? null : Number(body.usage.limit ?? 0),
                    used: Number(body.usage.used ?? 0),
                    remaining: unlimited ? null : Number(body.usage.remaining ?? 0),
                    unlimited,
                });
            }

            if (res.status === 429) {
                throw new Error(body.message || t('panel.limit_reached'));
            }

            if (!res.ok) {
                throw new Error(body.message || t('panel.error'));
            }

            const reply = (body.reply ?? '').trim();
            if (reply === '') {
                throw new Error(t('panel.error'));
            }

            const actions = Array.isArray(body.actions)
                ? body.actions.filter(
                      (a): a is UiAction =>
                          a != null &&
                          a.type === 'navigate' &&
                          typeof a.url === 'string' &&
                          a.url !== '' &&
                          typeof a.label === 'string',
                  )
                : [];

            setMessages((prev) => [
                ...prev,
                {
                    id: `a-${Date.now()}`,
                    role: 'assistant',
                    content: reply,
                    actions: actions.length > 0 ? actions : undefined,
                },
            ]);
        } catch (e) {
            setError(e instanceof Error ? e.message : t('panel.error'));
        } finally {
            setSending(false);
            requestAnimationFrame(() => inputRef.current?.focus());
        }
    };

    const onKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            void sendMessage(draft);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                floating
                overlayClassName={cn(
                    'bg-slate-950/30 backdrop-blur-[3px]',
                    'data-[state=open]:duration-500 data-[state=closed]:duration-300',
                    'data-[state=open]:ease-[cubic-bezier(0.22,1,0.36,1)]',
                )}
                className={cn(
                    'gap-0 overflow-hidden border-sky-200/80 bg-background/95 p-0 ring-1 ring-black/5',
                    'shadow-[0_18px_50px_-12px_rgba(15,23,42,0.35)]',
                    'dark:border-sky-800/70 dark:bg-background/95 dark:ring-white/10',
                    'data-[state=open]:duration-500 data-[state=closed]:duration-300',
                    'ease-[cubic-bezier(0.22,1,0.36,1)]',
                )}
            >
                <SheetHeader className="shrink-0 space-y-0 rounded-t-2xl border-b border-border/70 bg-linear-to-br from-sky-50 via-white to-slate-50 px-5 py-4 pr-12 dark:from-sky-950/50 dark:via-background dark:to-background">
                    <div className="flex items-start gap-3">
                        <div className="mt-0.5 flex size-10 items-center justify-center rounded-xl border border-sky-500/20 bg-sky-600 text-white shadow-md shadow-sky-600/20">
                            <Sparkles className="size-4" strokeWidth={2.25} />
                        </div>
                        <div className="min-w-0 flex-1 space-y-1.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <SheetTitle className="text-base font-semibold tracking-tight">
                                    {t('panel.title')}
                                </SheetTitle>
                                <span
                                    className={cn(
                                        'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium tracking-wide uppercase',
                                        configured
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'
                                            : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200',
                                    )}
                                >
                                    {configured ? t('panel.badge_ready') : t('panel.badge_offline')}
                                </span>
                                {usage && configured && (
                                    <span className="inline-flex items-center rounded-full border border-border/70 bg-white/80 px-2 py-0.5 text-[10px] font-medium text-muted-foreground dark:bg-card/60">
                                        {usage.unlimited || usage.limit == null
                                            ? t('panel.usage_unlimited')
                                            : t('panel.usage', {
                                                  remaining: usage.remaining,
                                                  limit: usage.limit,
                                              })}
                                    </span>
                                )}
                            </div>
                            <SheetDescription className="text-xs leading-relaxed">
                                {scope === 'platform'
                                    ? t('panel.subtitle_platform')
                                    : pageContext.paciente_id
                                      ? t('panel.subtitle_with_patient')
                                      : t('panel.subtitle')}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <div className="flex min-h-0 flex-1 flex-col">
                    <div
                        className={cn(
                            'min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-5',
                            'bg-[radial-gradient(circle_at_top,rgba(14,165,233,0.06),transparent_42%),linear-gradient(180deg,rgba(248,250,252,0.9),rgba(255,255,255,1))]',
                            'dark:bg-[radial-gradient(circle_at_top,rgba(14,165,233,0.08),transparent_42%),linear-gradient(180deg,rgba(2,6,23,0.35),rgba(2,6,23,0))]',
                        )}
                    >
                        {!configured ? (
                            <div className="rounded-xl border border-amber-200/90 bg-amber-50/95 p-4 shadow-xs dark:border-amber-900/50 dark:bg-amber-950/35">
                                <p className="text-sm leading-relaxed text-amber-950 dark:text-amber-100">
                                    {t('panel.not_configured')}
                                </p>
                            </div>
                        ) : limitReached ? (
                            <div className="rounded-xl border border-amber-200/90 bg-amber-50/95 p-4 shadow-xs dark:border-amber-900/50 dark:bg-amber-950/35">
                                <p className="text-sm leading-relaxed text-amber-950 dark:text-amber-100">
                                    {t('panel.limit_reached')}
                                </p>
                            </div>
                        ) : messages.length === 0 ? (
                            <div className="rounded-2xl border border-border/80 bg-white/90 p-5 shadow-sm dark:bg-card/80">
                                <div className="mb-3 flex size-11 items-center justify-center rounded-xl bg-sky-600/10 text-sky-700 dark:text-sky-300">
                                    <Bot className="size-5" strokeWidth={2} />
                                </div>
                                <h3 className="text-sm font-semibold text-foreground">
                                    {t('panel.welcome_title')}
                                </h3>
                                <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                    {scope === 'platform'
                                        ? t('panel.welcome_platform')
                                        : t('panel.welcome')}
                                </p>
                                <p className="mt-3 text-xs text-muted-foreground/80">
                                    {t('panel.empty_hint')}
                                </p>
                                <div className="mt-4 grid gap-2">
                                    {suggestions.map((label) => (
                                        <button
                                            key={label}
                                            type="button"
                                            onClick={() => void sendMessage(label)}
                                            className="rounded-xl border border-border/80 bg-slate-50/80 px-3.5 py-2.5 text-left text-xs font-medium text-foreground/90 transition-all hover:border-sky-300 hover:bg-sky-50 hover:text-sky-900 dark:bg-background/40 dark:hover:border-sky-800 dark:hover:bg-sky-950/40 dark:hover:text-sky-100"
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            messages.map((msg) => (
                                <div
                                    key={msg.id}
                                    className={cn(
                                        'flex gap-2.5',
                                        msg.role === 'user' ? 'flex-row-reverse' : 'flex-row',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border shadow-xs',
                                            msg.role === 'user'
                                                ? 'border-sky-500/30 bg-sky-600 text-white'
                                                : 'border-border/80 bg-white text-sky-700 dark:bg-card dark:text-sky-300',
                                        )}
                                        aria-hidden
                                    >
                                        {msg.role === 'user' ? (
                                            <UserRound className="size-3.5" strokeWidth={2.25} />
                                        ) : (
                                            <Bot className="size-3.5" strokeWidth={2.25} />
                                        )}
                                    </div>
                                    <div
                                        className={cn(
                                            'max-w-[85%] space-y-1',
                                            msg.role === 'user' ? 'items-end' : 'items-start',
                                        )}
                                    >
                                        <p className="px-1 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                                            {msg.role === 'user' ? t('panel.you') : t('panel.assistant')}
                                        </p>
                                        <div
                                            className={cn(
                                                'rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed whitespace-pre-wrap shadow-sm',
                                                msg.role === 'user'
                                                    ? 'rounded-tr-md border border-sky-500/20 bg-sky-600 text-white'
                                                    : 'rounded-tl-md border border-border/80 bg-white text-foreground dark:bg-card',
                                            )}
                                        >
                                            {msg.role === 'assistant' ? (
                                                <AssistantRichText text={msg.content} />
                                            ) : (
                                                msg.content
                                            )}
                                        </div>
                                        {msg.role === 'assistant' &&
                                            msg.actions &&
                                            msg.actions.length > 0 && (
                                                <div className="flex flex-wrap gap-2 pt-1">
                                                    {msg.actions.map((action) => (
                                                        <Button
                                                            key={`${action.url}-${action.label}`}
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 gap-1.5 rounded-full border-sky-200 bg-sky-50/80 text-xs text-sky-800 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200"
                                                            onClick={() => goTo(action.url)}
                                                        >
                                                            {t('panel.go_to', { label: action.label })}
                                                            <ArrowUpRight className="size-3.5" />
                                                        </Button>
                                                    ))}
                                                </div>
                                            )}
                                    </div>
                                </div>
                            ))
                        )}

                        {sending && (
                            <div className="flex items-center gap-2.5">
                                <div className="flex size-8 items-center justify-center rounded-full border border-border/80 bg-white text-sky-700 shadow-xs dark:bg-card dark:text-sky-300">
                                    <Bot className="size-3.5" />
                                </div>
                                <div className="inline-flex items-center gap-2 rounded-2xl rounded-tl-md border border-border/80 bg-white px-3.5 py-2.5 text-xs text-muted-foreground shadow-sm dark:bg-card">
                                    <Loader2 className="size-3.5 animate-spin text-sky-600" />
                                    {t('panel.thinking')}
                                </div>
                            </div>
                        )}

                        {error && (
                            <p className="rounded-xl border border-destructive/30 bg-destructive/5 px-3.5 py-2.5 text-sm text-destructive">
                                {error}
                            </p>
                        )}

                        <div ref={bottomRef} />
                    </div>

                    <div className="shrink-0 rounded-b-2xl border-t border-border/70 bg-white/95 px-4 py-3.5 backdrop-blur-sm dark:bg-background/95">
                        {messages.length > 0 && (
                            <div className="mb-2.5 flex justify-end">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 gap-1.5 px-2 text-xs text-muted-foreground"
                                    disabled={sending}
                                    onClick={() => {
                                        setMessages([]);
                                        setError(null);
                                    }}
                                >
                                    <Trash2 className="size-3.5" />
                                    {t('panel.clear')}
                                </Button>
                            </div>
                        )}
                        <div className="rounded-2xl border border-border/80 bg-slate-50/70 p-2 shadow-xs transition-colors focus-within:border-sky-300/80 focus-within:bg-white dark:bg-card/40 dark:focus-within:border-sky-700/70 dark:focus-within:bg-card/60">
                            <div className="flex items-end gap-2">
                                <Textarea
                                    ref={inputRef}
                                    value={draft}
                                    onChange={(e) => setDraft(e.target.value)}
                                    onKeyDown={onKeyDown}
                                    placeholder={t('panel.placeholder')}
                                    disabled={!configured || limitReached}
                                    rows={2}
                                    className={cn(
                                        'min-h-17 max-h-32 resize-none border-0 bg-transparent shadow-none',
                                        'outline-none focus:outline-none focus-visible:outline-none',
                                        'focus:border-transparent focus-visible:border-transparent',
                                        'focus:ring-0 focus-visible:ring-0 focus-visible:ring-offset-0',
                                        'focus:shadow-none focus-visible:shadow-none focus-visible:bg-transparent',
                                    )}
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    className="size-10 shrink-0 rounded-xl bg-sky-600 text-white shadow-sm shadow-sky-600/20 hover:bg-sky-600/90"
                                    disabled={
                                        !configured ||
                                        limitReached ||
                                        sending ||
                                        draft.trim() === ''
                                    }
                                    onClick={() => void sendMessage(draft)}
                                    aria-label={t('panel.send')}
                                >
                                    {sending ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <SendHorizontal className="size-4" />
                                    )}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
