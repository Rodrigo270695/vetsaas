import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    FileText,
    ImageIcon,
    MessagesSquare,
    Paperclip,
    Plus,
    Search,
    SendHorizontal,
    Smile,
    Users,
    UserRound,
    X,
} from 'lucide-react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type FormEvent,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { useTenantChatUnread } from '@/contexts/tenant-chat-unread-context';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { cn } from '@/lib/utils';

type ChatUser = { id: string; name: string; email: string };

type ChatAttachment = {
    url: string | null;
    name: string;
    mime: string;
    size: number;
    is_image: boolean;
};

type ChatMessage = {
    id: string;
    body: string;
    user_id: string;
    user_name: string;
    created_at: string | null;
    attachment: ChatAttachment | null;
};

type ConversationSummary = {
    id: string;
    type: 'direct' | 'group';
    title: string;
    name: string | null;
    participants: { id: string; name: string }[];
    participant_count: number;
    unread: number;
    last_message: {
        body: string;
        user_name: string;
        created_at: string | null;
        mine: boolean;
        has_attachment?: boolean;
    } | null;
    updated_at: string | null;
};

type ActiveConversation = ConversationSummary & { messages: ChatMessage[] };

type Props = {
    conversations: ConversationSummary[];
    users: ChatUser[];
    active: ActiveConversation | null;
    unread_total: number;
    can_manage: boolean;
    poll_ms: number;
};

const EMOJIS = [
    '😀', '😁', '😂', '🙂', '😉', '😊', '😍', '🤩',
    '😎', '🤔', '😢', '😭', '😤', '🙌', '👍', '👎',
    '👏', '🙏', '💪', '🔥', '✨', '✅', '❌', '⚠️',
    '📌', '📎', '📷', '🐶', '🐱', '💉', '💊', '🩺',
];

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();

    return (parts[0][0] + parts[1][0]).toUpperCase();
}

function formatClock(iso: string | null | undefined, locale: string): string {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';

    return d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
}

function formatListTime(iso: string | null | undefined, locale: string): string {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const now = new Date();
    const sameDay =
        d.getFullYear() === now.getFullYear()
        && d.getMonth() === now.getMonth()
        && d.getDate() === now.getDate();

    return sameDay
        ? formatClock(iso, locale)
        : d.toLocaleDateString(locale, { day: '2-digit', month: 'short' });
}

function formatBytes(n: number): string {
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;

    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

export default function ChatInternoIndex({
    conversations,
    users,
    active,
    unread_total,
    can_manage,
    poll_ms,
}: Props) {
    const { t, i18n } = useTranslation('chat-interno');
    const page = usePage<{ auth?: { user?: { id?: string } } }>();
    const meId = String(page.props.auth?.user?.id ?? '');
    const { setUnreadTotal, setActiveConversationId } = useTenantChatUnread();

    const [listQuery, setListQuery] = useState('');
    const [dmOpen, setDmOpen] = useState(false);
    const [groupOpen, setGroupOpen] = useState(false);
    const [userQuery, setUserQuery] = useState('');
    const [body, setBody] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [filePreview, setFilePreview] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [emojiOpen, setEmojiOpen] = useState(false);
    const bottomRef = useRef<HTMLDivElement | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);

    const dmForm = useForm<{ user_id: string }>({ user_id: '' });
    const groupForm = useForm<{ name: string; user_ids: string[] }>({
        name: '',
        user_ids: [],
    });

    useEffect(() => {
        setUnreadTotal(unread_total);
    }, [unread_total, setUnreadTotal]);

    useEffect(() => {
        setActiveConversationId(active?.id ?? null);

        return () => setActiveConversationId(null);
    }, [active?.id, setActiveConversationId]);

    useAutoRefresh({
        only: ['conversations', 'active', 'unread_total'],
        intervalMs: poll_ms || 6_000,
        enabled: true,
        busy: sending || dmOpen || groupOpen,
    });

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [active?.id, active?.messages?.length]);

    useEffect(() => {
        if (!file) {
            setFilePreview(null);

            return;
        }
        if (!file.type.startsWith('image/')) {
            setFilePreview(null);

            return;
        }
        const url = URL.createObjectURL(file);
        setFilePreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const filteredConversations = useMemo(() => {
        const q = listQuery.trim().toLowerCase();
        if (!q) return conversations;

        return conversations.filter((c) =>
            `${c.title} ${c.last_message?.body ?? ''}`.toLowerCase().includes(q),
        );
    }, [conversations, listQuery]);

    const filteredUsers = useMemo(() => {
        const q = userQuery.trim().toLowerCase();
        if (!q) return users;

        return users.filter((u) =>
            `${u.name} ${u.email}`.toLowerCase().includes(q),
        );
    }, [users, userQuery]);

    const openConversation = (id: string) => {
        router.get(
            '/comunicaciones/chat',
            { c: id },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearAttachment = () => {
        setFile(null);
        if (fileRef.current) fileRef.current.value = '';
    };

    const submitMessage = (e: FormEvent) => {
        e.preventDefault();
        if (!active || sending) return;
        if (!body.trim() && !file) return;

        setSending(true);
        router.post(
            `/comunicaciones/chat/${active.id}/messages`,
            {
                body: body.trim(),
                attachment: file,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setSending(false);
                    setBody('');
                    clearAttachment();
                },
            },
        );
    };

    const insertEmoji = (emoji: string) => {
        setBody((prev) => `${prev}${emoji}`);
        setEmojiOpen(false);
        textareaRef.current?.focus();
    };

    const startDm = (userId: string) => {
        dmForm.setData('user_id', userId);
        dmForm.post('/comunicaciones/chat/direct', {
            onSuccess: () => {
                setDmOpen(false);
                setUserQuery('');
                dmForm.reset();
            },
        });
    };

    const toggleMember = (userId: string) => {
        const current = groupForm.data.user_ids;
        groupForm.setData(
            'user_ids',
            current.includes(userId)
                ? current.filter((id) => id !== userId)
                : [...current, userId],
        );
    };

    const submitGroup = (e: FormEvent) => {
        e.preventDefault();
        groupForm.post('/comunicaciones/chat/groups', {
            onSuccess: () => {
                setGroupOpen(false);
                setUserQuery('');
                groupForm.reset();
            },
        });
    };

    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';

    return (
        <>
            <Head title={t('title')} />

            <div className="flex h-[calc(100dvh-5.5rem)] min-h-112 flex-col overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm lg:h-[calc(100dvh-6.5rem)]">
                <div className="flex items-center justify-between gap-3 border-b border-border/60 bg-linear-to-r from-emerald-50/90 via-card to-teal-50/40 px-4 py-3 dark:from-emerald-950/40 dark:via-card dark:to-teal-950/20">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30">
                            <MessagesSquare className="size-4" aria-hidden />
                        </span>
                        <div className="min-w-0">
                            <h1 className="truncate text-base font-semibold tracking-tight">
                                {t('title')}
                            </h1>
                            <p className="truncate text-xs text-muted-foreground">
                                {t('subtitle')}
                            </p>
                        </div>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button size="sm" className="shrink-0 gap-1.5 bg-emerald-600 hover:bg-emerald-700">
                                <Plus className="size-4" aria-hidden />
                                {t('new')}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuItem
                                onSelect={() => {
                                    setUserQuery('');
                                    setDmOpen(true);
                                }}
                            >
                                <UserRound className="size-4" aria-hidden />
                                {t('new_dm')}
                            </DropdownMenuItem>
                            {can_manage ? (
                                <DropdownMenuItem
                                    onSelect={() => {
                                        setUserQuery('');
                                        groupForm.reset();
                                        setGroupOpen(true);
                                    }}
                                >
                                    <Users className="size-4" aria-hidden />
                                    {t('new_group')}
                                </DropdownMenuItem>
                            ) : null}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="grid min-h-0 flex-1 lg:grid-cols-[minmax(17rem,21rem)_1fr]">
                    <aside className="flex min-h-0 flex-col border-b border-border/60 bg-muted/20 lg:border-r lg:border-b-0">
                        <div className="border-b border-border/50 p-3">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={listQuery}
                                    onChange={(e) => setListQuery(e.target.value)}
                                    placeholder={t('search_placeholder')}
                                    className="h-9 border-border/60 bg-background/80 pl-8 text-sm"
                                />
                            </div>
                        </div>

                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {filteredConversations.length === 0 ? (
                                <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                                    {t('empty_list')}
                                </p>
                            ) : (
                                <ul>
                                    {filteredConversations.map((c) => {
                                        const selected = active?.id === c.id;

                                        return (
                                            <li key={c.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => openConversation(c.id)}
                                                    className={cn(
                                                        'flex w-full items-start gap-3 border-l-2 px-3 py-3 text-left transition-colors',
                                                        selected
                                                            ? 'border-l-emerald-600 bg-emerald-50/80 dark:bg-emerald-950/35'
                                                            : 'border-l-transparent hover:bg-background/80',
                                                    )}
                                                >
                                                    <Avatar className="mt-0.5 size-10 border border-border/50 shadow-sm">
                                                        <AvatarFallback
                                                            className={cn(
                                                                'text-xs font-semibold',
                                                                c.type === 'group'
                                                                    ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200'
                                                                    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
                                                            )}
                                                        >
                                                            {c.type === 'group' ? (
                                                                <Users className="size-3.5" />
                                                            ) : (
                                                                initials(c.title)
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="truncate text-sm font-medium">
                                                                {c.title}
                                                            </span>
                                                            {c.unread > 0 ? (
                                                                <Badge className="h-5 shrink-0 rounded-full bg-emerald-600 px-1.5 text-[10px] text-white hover:bg-emerald-600">
                                                                    {c.unread}
                                                                </Badge>
                                                            ) : null}
                                                            <span className="ml-auto shrink-0 text-[10px] text-muted-foreground">
                                                                {formatListTime(c.updated_at, locale)}
                                                            </span>
                                                        </div>
                                                        <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                            {c.last_message
                                                                ? `${c.last_message.mine ? `${t('you')}: ` : ''}${c.last_message.body}`
                                                                : c.type === 'group'
                                                                  ? t('participants', {
                                                                        count: c.participant_count,
                                                                    })
                                                                  : t('type_direct')}
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

                    <section className="relative flex min-h-0 flex-col bg-[radial-gradient(ellipse_at_top,rgba(16,185,129,0.06),transparent_55%)]">
                        {!active ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
                                <div className="flex size-16 items-center justify-center rounded-2xl bg-emerald-600/10 text-emerald-700 ring-1 ring-emerald-600/15 dark:text-emerald-300">
                                    <MessagesSquare className="size-7" aria-hidden />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold">{t('empty_thread')}</p>
                                    <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                        {t('empty_thread_hint')}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <>
                                <header className="flex items-center gap-3 border-b border-border/60 bg-card/85 px-4 py-3 backdrop-blur-md">
                                    <Avatar className="size-10 border border-border/50 shadow-sm">
                                        <AvatarFallback
                                            className={cn(
                                                'text-xs font-semibold',
                                                active.type === 'group'
                                                    ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200'
                                                    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
                                            )}
                                        >
                                            {active.type === 'group' ? (
                                                <Users className="size-3.5" />
                                            ) : (
                                                initials(active.title)
                                            )}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold">
                                            {active.title}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {active.type === 'group'
                                                ? t('participants', {
                                                      count: active.participant_count,
                                                  })
                                                : t('dm_badge')}
                                        </p>
                                    </div>
                                    <Badge variant="outline" className="ml-auto shrink-0 text-[10px]">
                                        {active.type === 'group'
                                            ? t('group_badge')
                                            : t('dm_badge')}
                                    </Badge>
                                </header>

                                <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
                                    {active.messages.map((m) => {
                                        const mine = m.user_id === meId;

                                        return (
                                            <div
                                                key={m.id}
                                                className={cn(
                                                    'flex',
                                                    mine ? 'justify-end' : 'justify-start',
                                                )}
                                            >
                                                <div
                                                    className={cn(
                                                        'max-w-[min(100%,30rem)] overflow-hidden rounded-2xl text-sm shadow-sm',
                                                        mine
                                                            ? 'rounded-br-md bg-emerald-600 text-white'
                                                            : 'rounded-bl-md border border-border/60 bg-card text-foreground',
                                                    )}
                                                >
                                                    {!mine && active.type === 'group' ? (
                                                        <p className="px-3.5 pt-2 text-[10px] font-semibold tracking-wide text-emerald-700 uppercase dark:text-emerald-300">
                                                            {m.user_name}
                                                        </p>
                                                    ) : null}

                                                    {m.attachment ? (
                                                        <div className="px-2 pt-2">
                                                            {m.attachment.is_image && m.attachment.url ? (
                                                                <a
                                                                    href={m.attachment.url}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="block overflow-hidden rounded-xl"
                                                                >
                                                                    <img
                                                                        src={m.attachment.url}
                                                                        alt={m.attachment.name}
                                                                        className="max-h-56 w-full object-cover"
                                                                    />
                                                                </a>
                                                            ) : (
                                                                <a
                                                                    href={m.attachment.url ?? '#'}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className={cn(
                                                                        'flex items-center gap-2 rounded-xl px-2.5 py-2',
                                                                        mine
                                                                            ? 'bg-emerald-700/40'
                                                                            : 'bg-muted/70',
                                                                    )}
                                                                >
                                                                    <FileText className="size-4 shrink-0" />
                                                                    <span className="min-w-0 truncate text-xs font-medium">
                                                                        {m.attachment.name}
                                                                    </span>
                                                                    <span className="ml-auto shrink-0 text-[10px] opacity-80">
                                                                        {formatBytes(m.attachment.size)}
                                                                    </span>
                                                                </a>
                                                            )}
                                                        </div>
                                                    ) : null}

                                                    {m.body ? (
                                                        <p className="whitespace-pre-wrap wrap-break-word px-3.5 py-2">
                                                            {m.body}
                                                        </p>
                                                    ) : (
                                                        <div className="h-2" />
                                                    )}

                                                    <p
                                                        className={cn(
                                                            'px-3.5 pb-1.5 text-right text-[10px]',
                                                            mine
                                                                ? 'text-emerald-100/90'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {formatClock(m.created_at, locale)}
                                                    </p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                    <div ref={bottomRef} />
                                </div>

                                <form
                                    onSubmit={submitMessage}
                                    className="border-t border-border/60 bg-card/95 p-3 backdrop-blur-md"
                                >
                                    {file ? (
                                        <div className="mb-2 flex items-center gap-2 rounded-xl border border-border/60 bg-muted/40 px-2.5 py-2">
                                            {filePreview ? (
                                                <img
                                                    src={filePreview}
                                                    alt=""
                                                    className="size-10 rounded-lg object-cover"
                                                />
                                            ) : (
                                                <span className="flex size-10 items-center justify-center rounded-lg bg-background">
                                                    <FileText className="size-4 text-muted-foreground" />
                                                </span>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-medium">
                                                    {file.name}
                                                </p>
                                                <p className="text-[10px] text-muted-foreground">
                                                    {formatBytes(file.size)}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="size-7"
                                                onClick={clearAttachment}
                                                aria-label={t('remove_attachment')}
                                            >
                                                <X className="size-3.5" />
                                            </Button>
                                        </div>
                                    ) : null}

                                    <div className="flex items-end gap-1.5">
                                        <input
                                            ref={fileRef}
                                            type="file"
                                            className="hidden"
                                            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
                                            onChange={(e) => {
                                                const next = e.target.files?.[0] ?? null;
                                                setFile(next);
                                            }}
                                        />

                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="size-9 shrink-0 text-muted-foreground"
                                            onClick={() => fileRef.current?.click()}
                                            aria-label={t('attach')}
                                        >
                                            <Paperclip className="size-4" />
                                        </Button>

                                        <Popover open={emojiOpen} onOpenChange={setEmojiOpen}>
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
                                                            onClick={() => insertEmoji(emoji)}
                                                        >
                                                            {emoji}
                                                        </button>
                                                    ))}
                                                </div>
                                            </PopoverContent>
                                        </Popover>

                                        <Textarea
                                            ref={textareaRef}
                                            value={body}
                                            onChange={(e) => setBody(e.target.value)}
                                            placeholder={t('composer_placeholder')}
                                            rows={1}
                                            className="min-h-10 max-h-28 flex-1 resize-none"
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' && !e.shiftKey) {
                                                    e.preventDefault();
                                                    submitMessage(e);
                                                }
                                            }}
                                        />
                                        <Button
                                            type="submit"
                                            size="icon"
                                            disabled={(!body.trim() && !file) || sending}
                                            className="size-10 shrink-0 bg-emerald-600 hover:bg-emerald-700"
                                            aria-label={t('send')}
                                        >
                                            <SendHorizontal className="size-4" />
                                        </Button>
                                    </div>
                                    <p className="mt-1.5 flex items-center gap-1 text-[10px] text-muted-foreground">
                                        <ImageIcon className="size-3" />
                                        {t('attach_hint')}
                                    </p>
                                </form>
                            </>
                        )}
                    </section>
                </div>
            </div>

            <Dialog open={dmOpen} onOpenChange={setDmOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('direct_title')}</DialogTitle>
                        <DialogDescription>{t('direct_hint')}</DialogDescription>
                    </DialogHeader>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={userQuery}
                            onChange={(e) => setUserQuery(e.target.value)}
                            placeholder={t('direct_search')}
                            className="h-9 pl-8"
                        />
                    </div>
                    <div className="max-h-64 space-y-1 overflow-y-auto">
                        {users.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                {t('direct_empty')}
                            </p>
                        ) : filteredUsers.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                {t('no_users_match')}
                            </p>
                        ) : (
                            filteredUsers.map((u) => (
                                <button
                                    key={u.id}
                                    type="button"
                                    disabled={dmForm.processing}
                                    onClick={() => startDm(u.id)}
                                    className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-muted/70"
                                >
                                    <Avatar className="size-8">
                                        <AvatarFallback className="bg-emerald-100 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                            {initials(u.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">{u.name}</p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {u.email}
                                        </p>
                                    </div>
                                </button>
                            ))
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={groupOpen} onOpenChange={setGroupOpen}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={submitGroup}>
                        <DialogHeader>
                            <DialogTitle>{t('group_title')}</DialogTitle>
                        </DialogHeader>
                        <div className="mt-3 space-y-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="group-name">{t('group_name')}</Label>
                                <Input
                                    id="group-name"
                                    value={groupForm.data.name}
                                    onChange={(e) => groupForm.setData('name', e.target.value)}
                                    placeholder={t('group_name_placeholder')}
                                />
                                {groupForm.errors.name ? (
                                    <p className="text-xs text-destructive">
                                        {groupForm.errors.name}
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-1.5">
                                <Label>{t('group_members')}</Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={userQuery}
                                        onChange={(e) => setUserQuery(e.target.value)}
                                        placeholder={t('direct_search')}
                                        className="h-9 pl-8"
                                    />
                                </div>
                                <div className="max-h-48 space-y-1 overflow-y-auto rounded-md border border-border/60 p-1">
                                    {filteredUsers.length === 0 ? (
                                        <p className="py-4 text-center text-xs text-muted-foreground">
                                            {t('no_users_match')}
                                        </p>
                                    ) : (
                                        filteredUsers.map((u) => {
                                            const selected =
                                                groupForm.data.user_ids.includes(u.id);

                                            return (
                                                <button
                                                    key={u.id}
                                                    type="button"
                                                    onClick={() => toggleMember(u.id)}
                                                    className={cn(
                                                        'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm',
                                                        selected
                                                            ? 'bg-emerald-50 dark:bg-emerald-950/40'
                                                            : 'hover:bg-muted/60',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'flex size-4 items-center justify-center rounded border',
                                                            selected
                                                                ? 'border-emerald-600 bg-emerald-600 text-white'
                                                                : 'border-muted-foreground/40',
                                                        )}
                                                    >
                                                        {selected ? (
                                                            <Check className="size-3" />
                                                        ) : null}
                                                    </span>
                                                    <span className="truncate">{u.name}</span>
                                                </button>
                                            );
                                        })
                                    )}
                                </div>
                                {groupForm.errors.user_ids ? (
                                    <p className="text-xs text-destructive">
                                        {groupForm.errors.user_ids}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                        <DialogFooter className="mt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setGroupOpen(false)}
                            >
                                {t('cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    groupForm.processing
                                    || !groupForm.data.name.trim()
                                    || groupForm.data.user_ids.length === 0
                                }
                                className="bg-emerald-600 hover:bg-emerald-700"
                            >
                                {t('group_submit')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
