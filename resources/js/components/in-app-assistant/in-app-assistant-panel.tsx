import { usePage } from '@inertiajs/react';
import { Loader2, SendHorizontal, Sparkles, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
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

type ChatMessage = {
    id: string;
    role: ChatRole;
    content: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function InAppAssistantPanel({ open, onOpenChange }: Props) {
    const { t } = useTranslation('in-app-assistant');
    const { in_app_assistant } = usePage().props;
    const configured = in_app_assistant?.configured === true;

    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const bottomRef = useRef<HTMLDivElement | null>(null);
    const inputRef = useRef<HTMLTextAreaElement | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        const timer = window.setTimeout(() => inputRef.current?.focus(), 280);
        return () => window.clearTimeout(timer);
    }, [open]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages, sending, open]);

    const suggestions = [
        t('panel.suggestions.help_caja'),
        t('panel.suggestions.help_historia'),
        t('panel.suggestions.query_today'),
        t('panel.suggestions.query_stock'),
    ] as const;

    const sendMessage = async (raw: string) => {
        const message = raw.trim();
        if (message === '' || sending || !configured) {
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
                body: JSON.stringify({ message, history }),
            });

            const body = (await res.json()) as {
                reply?: string;
                message?: string;
            };

            if (!res.ok) {
                throw new Error(body.message || t('panel.error'));
            }

            const reply = (body.reply ?? '').trim();
            if (reply === '') {
                throw new Error(t('panel.error'));
            }

            setMessages((prev) => [
                ...prev,
                {
                    id: `a-${Date.now()}`,
                    role: 'assistant',
                    content: reply,
                },
            ]);
        } catch (e) {
            setError(e instanceof Error ? e.message : t('panel.error'));
        } finally {
            setSending(false);
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
                overlayClassName="bg-slate-950/35 backdrop-blur-[2px] data-[state=open]:duration-500 data-[state=closed]:duration-300"
                className={cn(
                    'gap-0 overflow-hidden border-l border-border/70 bg-background p-0 shadow-2xl sm:max-w-md',
                    'data-[state=open]:duration-500 data-[state=closed]:duration-300',
                    'ease-[cubic-bezier(0.22,1,0.36,1)]',
                )}
            >
                <SheetHeader className="shrink-0 border-b border-border/60 bg-linear-to-br from-sky-50/90 via-background to-background px-5 py-4 pr-12 dark:from-sky-950/40">
                    <div className="flex items-start gap-3">
                        <div className="mt-0.5 flex size-9 items-center justify-center rounded-lg bg-sky-600 text-white shadow-sm shadow-sky-600/25">
                            <Sparkles className="size-4" strokeWidth={2.25} />
                        </div>
                        <div className="min-w-0 space-y-0.5">
                            <SheetTitle className="text-base font-semibold tracking-tight">
                                {t('panel.title')}
                            </SheetTitle>
                            <SheetDescription className="text-xs leading-relaxed">
                                {t('panel.subtitle')}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <div className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
                        {!configured ? (
                            <p className="rounded-lg border border-amber-200/80 bg-amber-50/80 px-3 py-2.5 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                                {t('panel.not_configured')}
                            </p>
                        ) : messages.length === 0 ? (
                            <div className="space-y-4">
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {t('panel.welcome')}
                                </p>
                                <p className="text-xs text-muted-foreground/80">
                                    {t('panel.empty_hint')}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {suggestions.map((label) => (
                                        <button
                                            key={label}
                                            type="button"
                                            onClick={() => void sendMessage(label)}
                                            className="rounded-full border border-border/80 bg-card/80 px-3 py-1.5 text-left text-xs text-foreground/90 transition-colors hover:border-sky-300 hover:bg-sky-50/80 dark:hover:border-sky-800 dark:hover:bg-sky-950/40"
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
                                        'flex',
                                        msg.role === 'user' ? 'justify-end' : 'justify-start',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'max-w-[92%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed whitespace-pre-wrap shadow-xs',
                                            msg.role === 'user'
                                                ? 'rounded-br-md bg-sky-600 text-white'
                                                : 'rounded-bl-md border border-border/70 bg-muted/40 text-foreground',
                                        )}
                                    >
                                        {msg.content}
                                    </div>
                                </div>
                            ))
                        )}

                        {sending && (
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Loader2 className="size-3.5 animate-spin" />
                                {t('panel.thinking')}
                            </div>
                        )}

                        {error && (
                            <p className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                                {error}
                            </p>
                        )}

                        <div ref={bottomRef} />
                    </div>

                    <div className="shrink-0 border-t border-border/60 bg-background/95 px-4 py-3 backdrop-blur-sm">
                        {messages.length > 0 && (
                            <div className="mb-2 flex justify-end">
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
                        <div className="flex items-end gap-2">
                            <Textarea
                                ref={inputRef}
                                value={draft}
                                onChange={(e) => setDraft(e.target.value)}
                                onKeyDown={onKeyDown}
                                placeholder={t('panel.placeholder')}
                                disabled={!configured || sending}
                                rows={2}
                                className="min-h-17 max-h-32 resize-none"
                            />
                            <Button
                                type="button"
                                size="icon"
                                className="size-10 shrink-0 bg-sky-600 text-white hover:bg-sky-600/90"
                                disabled={!configured || sending || draft.trim() === ''}
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
            </SheetContent>
        </Sheet>
    );
}
