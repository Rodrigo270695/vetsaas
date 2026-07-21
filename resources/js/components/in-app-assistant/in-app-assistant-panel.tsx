import { router, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Bot,
    GripHorizontal,
    Loader2,
    Map,
    SendHorizontal,
    Sparkles,
    Trash2,
    UserRound,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent as ReactPointerEvent } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { resolveAssistantPageContext } from '@/components/in-app-assistant/resolve-page-context';
import { isTourId } from '@/components/in-app-assistant/tour-definitions';
import type { TourId } from '@/components/in-app-assistant/tour-definitions';
import { startInAppTour } from '@/components/in-app-assistant/tour-manager';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';

type ChatRole = 'user' | 'assistant';

type UiAction =
    | {
          type: 'navigate';
          url: string;
          label: string;
      }
    | {
          type: 'start_tour';
          tour_id: TourId;
          label: string;
      };

type ChatMessage = {
    id: string;
    role: ChatRole;
    content: string;
    actions?: UiAction[];
};

type SuggestionDef = {
    key: string;
    permission?: string | string[];
};

const HISTORY_LIMIT = 12;
const WINDOW_STORAGE_KEY = 'vetsaas.in-app-assistant.window';
const MIN_W = 320;
const MIN_H = 400;
const DEFAULT_W = 400;
const DEFAULT_H = 620;

type WindowGeom = { x: number; y: number; w: number; h: number };

function defaultWindowGeom(): WindowGeom {
    if (typeof window === 'undefined') {
        return { x: 24, y: 24, w: DEFAULT_W, h: DEFAULT_H };
    }
    const margin = 16;
    const w = Math.min(DEFAULT_W, window.innerWidth - margin * 2);
    const h = Math.min(DEFAULT_H, window.innerHeight - margin * 2);
    return {
        x: Math.max(margin, window.innerWidth - w - margin),
        y: Math.max(margin, window.innerHeight - h - margin),
        w,
        h,
    };
}

function clampWindowGeom(geom: WindowGeom): WindowGeom {
    if (typeof window === 'undefined') {
        return geom;
    }
    const margin = 8;
    const maxW = Math.max(MIN_W, window.innerWidth - margin * 2);
    const maxH = Math.max(MIN_H, window.innerHeight - margin * 2);
    const w = Math.min(Math.max(MIN_W, geom.w), maxW);
    const h = Math.min(Math.max(MIN_H, geom.h), maxH);
    const x = Math.min(
        Math.max(margin, geom.x),
        Math.max(margin, window.innerWidth - w - margin),
    );
    const y = Math.min(
        Math.max(margin, geom.y),
        Math.max(margin, window.innerHeight - h - margin),
    );
    return { x, y, w, h };
}

function loadWindowGeom(): WindowGeom {
    try {
        const raw = localStorage.getItem(WINDOW_STORAGE_KEY);
        if (!raw) {
            return defaultWindowGeom();
        }
        const parsed = JSON.parse(raw) as Partial<WindowGeom>;
        if (
            typeof parsed.x !== 'number' ||
            typeof parsed.y !== 'number' ||
            typeof parsed.w !== 'number' ||
            typeof parsed.h !== 'number'
        ) {
            return defaultWindowGeom();
        }
        return clampWindowGeom({
            x: parsed.x,
            y: parsed.y,
            w: parsed.w,
            h: parsed.h,
        });
    } catch {
        return defaultWindowGeom();
    }
}

function saveWindowGeom(geom: WindowGeom): void {
    try {
        localStorage.setItem(WINDOW_STORAGE_KEY, JSON.stringify(geom));
    } catch {
        // ignore
    }
}

function historyStorageKey(scope: string): string {
    return `vetsaas.in-app-assistant.history.${scope}`;
}

function loadHistory(scope: string): ChatMessage[] {
    try {
        const raw = sessionStorage.getItem(historyStorageKey(scope));
        if (!raw) {
            return [];
        }
        const parsed = JSON.parse(raw) as unknown;
        if (!Array.isArray(parsed)) {
            return [];
        }
        return parsed
            .filter(
                (item): item is ChatMessage =>
                    item != null &&
                    typeof item === 'object' &&
                    typeof (item as ChatMessage).id === 'string' &&
                    ((item as ChatMessage).role === 'user' ||
                        (item as ChatMessage).role === 'assistant') &&
                    typeof (item as ChatMessage).content === 'string',
            )
            .slice(-HISTORY_LIMIT)
            .map((item) => ({
                id: item.id,
                role: item.role,
                content: item.content,
                // No restauramos actions de navegación (pueden quedar stale).
            }));
    } catch {
        return [];
    }
}

function saveHistory(scope: string, messages: ChatMessage[]): void {
    try {
        const slim = messages
            .slice(-HISTORY_LIMIT)
            .map(({ id, role, content }) => ({
                id,
                role,
                content,
            }));
        sessionStorage.setItem(historyStorageKey(scope), JSON.stringify(slim));
    } catch {
        // sessionStorage lleno / privado: ignorar.
    }
}

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
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
    const { can } = usePermission();
    const { in_app_assistant } = page.props;
    const scope =
        in_app_assistant?.scope === 'platform' ? 'platform' : 'clinic';
    const isUnlimited =
        in_app_assistant?.unlimited === true || scope === 'platform';
    const pacientePropId = (page.props as { paciente?: { id?: string } })
        .paciente?.id;
    const pageContext = useMemo(
        () => resolveAssistantPageContext(page),
        [page.url, page.component, pacientePropId],
    );

    const [configured, setConfigured] = useState(
        in_app_assistant?.configured === true,
    );
    const [usage, setUsage] = useState<{
        limit: number | null;
        used: number;
        remaining: number | null;
        unlimited: boolean;
    } | null>(
        isUnlimited
            ? { limit: null, used: 0, remaining: null, unlimited: true }
            : null,
    );
    const [messages, setMessages] = useState<ChatMessage[]>(() =>
        loadHistory(scope),
    );
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [geom, setGeom] = useState<WindowGeom>(() => loadWindowGeom());
    const [dragging, setDragging] = useState(false);
    const [resizing, setResizing] = useState(false);
    const bottomRef = useRef<HTMLDivElement | null>(null);
    const inputRef = useRef<HTMLTextAreaElement | null>(null);
    const historyHydrated = useRef(false);
    const dragRef = useRef<{
        pointerId: number;
        startX: number;
        startY: number;
        originX: number;
        originY: number;
    } | null>(null);
    const resizeRef = useRef<{
        pointerId: number;
        startX: number;
        startY: number;
        originW: number;
        originH: number;
        originX: number;
        originY: number;
    } | null>(null);
    const geomRef = useRef(geom);
    geomRef.current = geom;

    useEffect(() => {
        setConfigured(in_app_assistant?.configured === true);
    }, [in_app_assistant?.configured]);

    useEffect(() => {
        // Al cambiar de clínica ↔ plataforma, recargar historial corto de ese scope.
        setMessages(loadHistory(scope));
        historyHydrated.current = true;
    }, [scope]);

    useEffect(() => {
        if (!historyHydrated.current) {
            return;
        }
        saveHistory(scope, messages);
    }, [messages, scope]);

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
                    const unlimited =
                        body.usage.unlimited === true ||
                        body.usage.limit == null;
                    setUsage({
                        limit: unlimited ? null : Number(body.usage.limit ?? 0),
                        used: Number(body.usage.used ?? 0),
                        remaining: unlimited
                            ? null
                            : Number(body.usage.remaining ?? 0),
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

    useEffect(() => {
        if (!open) {
            return;
        }
        const onResize = () => {
            setGeom((prev) => {
                const next = clampWindowGeom(prev);
                saveWindowGeom(next);
                return next;
            });
        };
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }
        const onKeyDown = (e: globalThis.KeyboardEvent) => {
            if (e.key === 'Escape') {
                onOpenChange(false);
            }
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open, onOpenChange]);

    const suggestions = useMemo(() => {
        const pick = (defs: SuggestionDef[], max = 5): string[] => {
            const out: string[] = [];
            for (const def of defs) {
                if (def.permission && !can(def.permission)) {
                    continue;
                }
                out.push(t(`panel.suggestions.${def.key}`));
                if (out.length >= max) {
                    break;
                }
            }
            return out;
        };

        if (scope === 'platform') {
            return pick([
                { key: 'explain_screen' },
                { key: 'platform_pending' },
                { key: 'platform_risk' },
                { key: 'platform_expiring' },
                { key: 'platform_bot_ia' },
                { key: 'platform_openwa' },
                { key: 'platform_cold_leads' },
                { key: 'platform_summary' },
            ]);
        }

        if (pageContext.paciente_id) {
            return pick([
                { key: 'explain_screen' },
                { key: 'query_this_patient', permission: 'pacientes.view' },
                {
                    key: 'query_alerts',
                    permission: [
                        'alertas-stock.view',
                        'stock.view',
                        'citas.view',
                    ],
                },
                { key: 'nav_vacunas', permission: 'pacientes.view' },
                { key: 'query_who_attends', permission: 'citas.view' },
            ]);
        }

        return pick([
            { key: 'explain_screen' },
            { key: 'query_agenda', permission: 'citas.view' },
            { key: 'query_who_attends', permission: 'citas.view' },
            { key: 'query_caja_today', permission: 'caja-sesiones.view' },
            { key: 'query_expiry', permission: 'alertas-stock.view' },
            {
                key: 'query_alerts',
                permission: ['alertas-stock.view', 'stock.view'],
            },
            { key: 'query_stock', permission: 'stock.view' },
            { key: 'nav_caja', permission: 'caja-sesiones.view' },
            { key: 'nav_vacunas', permission: 'pacientes.view' },
            { key: 'query_today' },
        ]);
    }, [t, pageContext.paciente_id, scope, can]);

    const clearConversation = () => {
        setMessages([]);
        setError(null);
        try {
            sessionStorage.removeItem(historyStorageKey(scope));
        } catch {
            // ignore
        }
    };

    const limitReached =
        !isUnlimited &&
        usage !== null &&
        usage.unlimited !== true &&
        typeof usage.remaining === 'number' &&
        usage.remaining <= 0;

    const goTo = (url: string) => {
        // Ventana flotante: se puede seguir usando el chat mientras navegas.
        router.visit(url);
    };

    const startTour = (tourId: TourId) => {
        onOpenChange(false);
        startInAppTour(tourId);
    };

    const commitGeom = (next: WindowGeom) => {
        const clamped = clampWindowGeom(next);
        setGeom(clamped);
        saveWindowGeom(clamped);
        return clamped;
    };

    const onDragPointerDown = (e: ReactPointerEvent<HTMLDivElement>) => {
        if (e.button !== 0) {
            return;
        }
        const target = e.target as HTMLElement | null;
        if (target?.closest('button, a, input, textarea')) {
            return;
        }
        e.preventDefault();
        dragRef.current = {
            pointerId: e.pointerId,
            startX: e.clientX,
            startY: e.clientY,
            originX: geomRef.current.x,
            originY: geomRef.current.y,
        };
        setDragging(true);
        e.currentTarget.setPointerCapture(e.pointerId);
    };

    const onDragPointerMove = (e: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== e.pointerId) {
            return;
        }
        commitGeom({
            ...geomRef.current,
            x: drag.originX + (e.clientX - drag.startX),
            y: drag.originY + (e.clientY - drag.startY),
        });
    };

    const onDragPointerUp = (e: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== e.pointerId) {
            return;
        }
        dragRef.current = null;
        setDragging(false);
        try {
            e.currentTarget.releasePointerCapture(e.pointerId);
        } catch {
            // ignore
        }
    };

    const onResizePointerDown = (e: ReactPointerEvent<HTMLDivElement>) => {
        if (e.button !== 0) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        resizeRef.current = {
            pointerId: e.pointerId,
            startX: e.clientX,
            startY: e.clientY,
            originW: geomRef.current.w,
            originH: geomRef.current.h,
            originX: geomRef.current.x,
            originY: geomRef.current.y,
        };
        setResizing(true);
        e.currentTarget.setPointerCapture(e.pointerId);
    };

    const onResizePointerMove = (e: ReactPointerEvent<HTMLDivElement>) => {
        const resize = resizeRef.current;
        if (!resize || resize.pointerId !== e.pointerId) {
            return;
        }
        commitGeom({
            x: resize.originX,
            y: resize.originY,
            w: resize.originW + (e.clientX - resize.startX),
            h: resize.originH + (e.clientY - resize.startY),
        });
    };

    const onResizePointerUp = (e: ReactPointerEvent<HTMLDivElement>) => {
        const resize = resizeRef.current;
        if (!resize || resize.pointerId !== e.pointerId) {
            return;
        }
        resizeRef.current = null;
        setResizing(false);
        try {
            e.currentTarget.releasePointerCapture(e.pointerId);
        } catch {
            // ignore
        }
    };

    const sendMessage = async (raw: string) => {
        const message = raw.trim();
        if (message === '' || sending || !configured || limitReached) {
            return;
        }

        setError(null);
        setDraft('');

        const history = messages.map(({ role, content }) => ({
            role,
            content,
        }));
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
                body: JSON.stringify({
                    message,
                    history,
                    context: pageContext,
                }),
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
                const unlimited =
                    body.usage.unlimited === true || body.usage.limit == null;
                setUsage({
                    limit: unlimited ? null : Number(body.usage.limit ?? 0),
                    used: Number(body.usage.used ?? 0),
                    remaining: unlimited
                        ? null
                        : Number(body.usage.remaining ?? 0),
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
                          typeof a === 'object' &&
                          typeof a.label === 'string' &&
                          ((a.type === 'navigate' &&
                              typeof a.url === 'string' &&
                              a.url !== '') ||
                              (a.type === 'start_tour' && isTourId(a.tour_id))),
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

    if (!open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            role="dialog"
            aria-label={t('panel.title')}
            aria-modal="false"
            className={cn(
                'fixed z-[120] flex flex-col overflow-hidden rounded-2xl border border-sky-200/80 bg-background shadow-[0_18px_50px_-12px_rgba(15,23,42,0.35)] ring-1 ring-black/5',
                'dark:border-sky-800/70 dark:ring-white/10',
                (dragging || resizing) && 'select-none',
            )}
            style={{
                left: geom.x,
                top: geom.y,
                width: geom.w,
                height: geom.h,
            }}
        >
            <div
                className={cn(
                    'relative shrink-0 touch-none border-b border-border/70 bg-linear-to-br from-sky-50 via-white to-slate-50 px-4 py-3 dark:from-sky-950/50 dark:via-background dark:to-background',
                    dragging ? 'cursor-grabbing' : 'cursor-grab',
                )}
                onPointerDown={onDragPointerDown}
                onPointerMove={onDragPointerMove}
                onPointerUp={onDragPointerUp}
                onPointerCancel={onDragPointerUp}
            >
                <div className="flex items-start gap-3">
                    <div className="mt-0.5 flex size-10 items-center justify-center rounded-xl border border-sky-500/20 bg-sky-600 text-white shadow-md shadow-sky-600/20">
                        <Sparkles className="size-4" strokeWidth={2.25} />
                    </div>
                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex flex-wrap items-center gap-2 pr-8">
                            <h2 className="text-base font-semibold tracking-tight">
                                {t('panel.title')}
                            </h2>
                            <span
                                className={cn(
                                    'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium tracking-wide uppercase',
                                    configured
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200',
                                )}
                            >
                                {configured
                                    ? t('panel.badge_ready')
                                    : t('panel.badge_offline')}
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
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {scope === 'platform'
                                ? t('panel.subtitle_platform')
                                : pageContext.paciente_id
                                  ? t('panel.subtitle_with_patient')
                                  : t('panel.subtitle')}
                        </p>
                        <div className="flex items-center gap-1 pt-0.5 text-[10px] text-muted-foreground/70">
                            <GripHorizontal className="size-3.5" />
                            <span>{t('panel.drag_hint')}</span>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="absolute top-2.5 right-2.5 size-8 rounded-full text-muted-foreground hover:text-foreground"
                        onClick={() => onOpenChange(false)}
                        aria-label={t('panel.close')}
                    >
                        <X className="size-4" />
                    </Button>
                </div>
            </div>

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
                                    msg.role === 'user'
                                        ? 'flex-row-reverse'
                                        : 'flex-row',
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
                                        <UserRound
                                            className="size-3.5"
                                            strokeWidth={2.25}
                                        />
                                    ) : (
                                        <Bot
                                            className="size-3.5"
                                            strokeWidth={2.25}
                                        />
                                    )}
                                </div>
                                <div
                                    className={cn(
                                        'max-w-[85%] space-y-1',
                                        msg.role === 'user'
                                            ? 'items-end'
                                            : 'items-start',
                                    )}
                                >
                                    <p className="px-1 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                                        {msg.role === 'user'
                                            ? t('panel.you')
                                            : t('panel.assistant')}
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
                                            <AssistantRichText
                                                text={msg.content}
                                            />
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
                                                        key={`${action.type}-${
                                                            action.type ===
                                                            'navigate'
                                                                ? action.url
                                                                : action.tour_id
                                                        }-${action.label}`}
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-8 gap-1.5 rounded-full border-sky-200 bg-sky-50/80 text-xs text-sky-800 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200"
                                                        onClick={() =>
                                                            action.type ===
                                                            'navigate'
                                                                ? goTo(
                                                                      action.url,
                                                                  )
                                                                : startTour(
                                                                      action.tour_id,
                                                                  )
                                                        }
                                                    >
                                                        {action.type ===
                                                        'navigate'
                                                            ? t('panel.go_to', {
                                                                  label: action.label,
                                                              })
                                                            : t(
                                                                  'panel.start_tour',
                                                                  {
                                                                      label: action.label,
                                                                  },
                                                              )}
                                                        {action.type ===
                                                        'navigate' ? (
                                                            <ArrowUpRight className="size-3.5" />
                                                        ) : (
                                                            <Map className="size-3.5" />
                                                        )}
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

                <div className="relative shrink-0 border-t border-border/70 bg-white/95 px-4 py-3.5 dark:bg-background/95">
                    {messages.length > 0 && (
                        <div className="mb-2.5 flex justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 gap-1.5 px-2 text-xs text-muted-foreground"
                                disabled={sending}
                                onClick={clearConversation}
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
                                    'max-h-32 min-h-17 resize-none border-0 bg-transparent shadow-none',
                                    'outline-none focus:outline-none focus-visible:outline-none',
                                    'focus:border-transparent focus-visible:border-transparent',
                                    'focus:ring-0 focus-visible:ring-0 focus-visible:ring-offset-0',
                                    'focus:shadow-none focus-visible:bg-transparent focus-visible:shadow-none',
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

                    <div
                        className="absolute right-1 bottom-1 z-10 size-4 cursor-nwse-resize touch-none"
                        onPointerDown={onResizePointerDown}
                        onPointerMove={onResizePointerMove}
                        onPointerUp={onResizePointerUp}
                        onPointerCancel={onResizePointerUp}
                        aria-hidden
                        title={t('panel.resize_hint')}
                    >
                        <span className="absolute right-0.5 bottom-0.5 h-2.5 w-2.5 border-r-2 border-b-2 border-sky-400/80 dark:border-sky-500/70" />
                    </div>
                </div>
            </div>
        </div>,
        document.body,
    );
}
