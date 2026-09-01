import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    format,
    formatDistanceToNow,
    parseISO,
} from 'date-fns';
import { enUS, es as esLocale } from 'date-fns/locale';
import {
    Bell,
    BellOff,
    Check,
    ChevronLeft,
    FileText,
    Forward,
    ImageIcon,
    Images,
    MessagesSquare,
    MoreHorizontal,
    Paperclip,
    Pencil,
    Pin,
    PinOff,
    Plus,
    Reply,
    Search,
    SendHorizontal,
    Smile,
    Trash2,
    Volume2,
    VolumeX,
    Users,
    UserRound,
    X,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type FormEvent,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
import { useTranslation } from 'react-i18next';
import { PushNotificationPrompt } from '@/components/push/push-notification-prompt';
import {
    buildChatThreadItems,
    ChatDeliveryReceipt,
    ChatImageLightbox,
    ChatListAside,
    ChatMessageActionSheet,
    ChatMessagePressable,
    ChatMessageScroller,
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTenantChatUnread } from '@/contexts/tenant-chat-unread-context';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';

type ChatUser = { id: string; name: string; email: string };

type ChatAttachment = {
    url: string | null;
    name: string;
    mime: string;
    size: number;
    is_image: boolean;
};

type ReplyPreview = {
    id: string;
    body: string;
    user_id: string;
    user_name: string;
};

type ReadBy = {
    user_id: string;
    name: string;
    read_at: string | null;
};

type TypingUser = {
    user_id: string;
    name: string;
};

type ChatReactionEmoji = '👍' | '✅' | '❤️' | '😂' | '🎉';

type ChatReaction = {
    emoji: ChatReactionEmoji | string;
    count: number;
    reacted?: boolean;
    mine?: boolean;
    user_ids?: string[];
};

type ChatMessage = {
    id: string;
    body: string;
    user_id: string;
    user_name: string;
    created_at: string | null;
    mine?: boolean;
    reply_to_id?: string | null;
    reply_to?: ReplyPreview | null;
    mentioned_user_ids?: string[];
    mentions?: { id: string; name: string }[];
    attachment: ChatAttachment | null;
    attachments?: ChatAttachment[];
    read_by?: ReadBy[];
    delivery_status?: 'sent' | 'delivered' | 'read';
    edited_at?: string | null;
    deleted_at?: string | null;
    is_deleted?: boolean;
    reactions?: ChatReaction[];
};

type ConversationSummary = {
    id: string;
    type: 'direct' | 'group';
    kind?: 'team' | 'support' | string;
    is_support?: boolean;
    title: string;
    name: string | null;
    participants: { id: string; name: string }[];
    participant_count: number;
    unread: number;
    muted?: boolean;
    pinned?: boolean;
    peer_online?: boolean | null;
    peer_last_seen_at?: string | null;
    can_write?: boolean;
    last_message: {
        body: string;
        user_name: string;
        created_at: string | null;
        mine: boolean;
        has_attachment?: boolean;
    } | null;
    updated_at: string | null;
};

type ActiveConversation = ConversationSummary & {
    messages: ChatMessage[];
    typing?: TypingUser[];
};

type MediaGalleryItem = {
    url: string;
    name: string;
    message_id?: string;
    created_at?: string | null;
};

type BroadcastConfig = {
    enabled: boolean;
    key?: string;
    host?: string;
    port?: number;
    scheme?: string;
};

type Props = {
    conversations: ConversationSummary[];
    users: ChatUser[];
    active: ActiveConversation | null;
    focus_message_id?: string | null;
    unread_total: number;
    can_manage: boolean;
    can_create_groups?: boolean;
    draft?: string | null;
    retention_days?: number | null;
    poll_ms: number;
    broadcast?: BroadcastConfig;
};

const EMOJIS = [
    '😀', '😁', '😂', '🙂', '😉', '😊', '😍', '🤩',
    '😎', '🤔', '😢', '😭', '😤', '🙌', '👍', '👎',
    '👏', '🙏', '💪', '🔥', '✨', '✅', '❌', '⚠️',
    '📌', '📎', '📷', '🐶', '🐱', '💉', '💊', '🩺',
];

const REACTION_EMOJIS: ChatReactionEmoji[] = ['👍', '✅', '❤️', '😂', '🎉'];

const SOUND_KEY = 'tenant-chat-sound';
const DRAFT_KEY_PREFIX = 'tenant-chat-draft:';
const MAX_ATTACHMENTS = 5;
const RETENTION_OPTIONS = [30, 90, 180] as const;
const PRESENCE_HEARTBEAT_MS = 45_000;
const URL_IN_TEXT_RE = /(https?:\/\/[^\s<]+[^.,;:!?\s<])/gi;

function draftStorageKey(conversationId: string): string {
    return `${DRAFT_KEY_PREFIX}${conversationId}`;
}

function readDraft(conversationId: string): string | null {
    try {
        return window.localStorage.getItem(draftStorageKey(conversationId));
    } catch {
        return null;
    }
}

function writeDraft(conversationId: string, value: string): void {
    try {
        const key = draftStorageKey(conversationId);
        if (!value.trim()) {
            window.localStorage.removeItem(key);
        } else {
            window.localStorage.setItem(key, value);
        }
    } catch {
        // ignore quota / private mode
    }
}

function clearDraft(conversationId: string): void {
    try {
        window.localStorage.removeItem(draftStorageKey(conversationId));
    } catch {
        // ignore
    }
}

function isMessageDeleted(m: ChatMessage): boolean {
    return Boolean(m.is_deleted || m.deleted_at);
}

function readXsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

function csrfHeaders(json = false): HeadersInit {
    const xsrf = readXsrfToken();
    const meta = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(json ? { 'Content-Type': 'application/json' } : {}),
        ...(meta ? { 'X-CSRF-TOKEN': meta } : {}),
        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
    };
}

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

function playChatChime(): void {
    try {
        const Ctx =
            window.AudioContext
            || (window as unknown as { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.12);
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.08, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.28);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.3);
        window.setTimeout(() => {
            void ctx.close();
        }, 400);
    } catch {
        // Navegador sin gesto / audio bloqueado.
    }
}

function isSoundEnabled(): boolean {
    try {
        return window.localStorage.getItem(SOUND_KEY) !== '0';
    } catch {
        return true;
    }
}

function messageAttachments(m: ChatMessage): ChatAttachment[] {
    if (m.attachments && m.attachments.length > 0) return m.attachments;
    if (m.attachment) return [m.attachment];

    return [];
}

function extractMentionIds(body: string, directory: ChatUser[]): string[] {
    const ids: string[] = [];
    const sorted = [...directory].sort((a, b) => b.name.length - a.name.length);
    for (const u of sorted) {
        const needle = `@${u.name}`;
        if (body.includes(needle) && !ids.includes(u.id)) {
            ids.push(u.id);
        }
    }

    return ids;
}

async function loadChatEchoModules(): Promise<{
    EchoCtor: new (opts: Record<string, unknown>) => ChatEchoInstance;
    Pusher: unknown;
} | null> {
    try {
        const [echoMod, pusherMod] = await Promise.all([
            import('laravel-echo'),
            import('pusher-js'),
        ]);
        const EchoCtor =
            (echoMod as { default?: new (opts: Record<string, unknown>) => ChatEchoInstance }).default
            ?? (echoMod as new (opts: Record<string, unknown>) => ChatEchoInstance);
        const Pusher =
            (pusherMod as { default?: unknown }).default ?? pusherMod;

        return { EchoCtor, Pusher };
    } catch {
        return null;
    }
}

type ChatEchoChannel = {
    listen: (event: string, cb: (payload: unknown) => void) => void;
    stopListening?: (event: string) => void;
    subscribed?: (cb: () => void) => void;
};

type ChatEchoInstance = {
    private: (ch: string) => ChatEchoChannel;
    leave: (ch: string) => void;
    disconnect?: () => void;
    connector?: {
        pusher?: {
            connection?: {
                bind: (event: string, cb: () => void) => void;
            };
        };
    };
};

let sharedEcho: ChatEchoInstance | null = null;
let sharedEchoKey: string | null = null;

async function getSharedEcho(
    broadcast: BroadcastConfig,
): Promise<ChatEchoInstance | null> {
    if (!broadcast.enabled || !broadcast.key) {
        return null;
    }

    const scheme = broadcast.scheme === 'http' ? 'http' : 'https';
    const port = Number(broadcast.port ?? (scheme === 'https' ? 443 : 8080));
    const host = broadcast.host || window.location.hostname;
    const cacheKey = `${broadcast.key}|${host}|${port}|${scheme}`;

    if (sharedEcho && sharedEchoKey === cacheKey) {
        return sharedEcho;
    }

    if (sharedEcho) {
        try {
            sharedEcho.disconnect?.();
        } catch {
            // ignore
        }
        sharedEcho = null;
        sharedEchoKey = null;
    }

    const mods = await loadChatEchoModules();
    if (!mods) {
        return null;
    }

    const { EchoCtor, Pusher } = mods;
    (window as unknown as { Pusher?: unknown }).Pusher = Pusher;

    sharedEcho = new EchoCtor({
        broadcaster: 'reverb',
        key: broadcast.key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: csrfHeaders(),
        },
    });
    sharedEchoKey = cacheKey;

    return sharedEcho;
}

export default function ChatInternoIndex({
    conversations: conversationsProp,
    users,
    active: activeProp,
    focus_message_id: focusMessageIdProp,
    unread_total,
    can_manage,
    can_create_groups,
    draft,
    retention_days,
    poll_ms,
    broadcast,
}: Props) {
    const { t, i18n } = useTranslation('chat-interno');
    const page = usePage<{
        auth?: { user?: { id?: string } };
        tenant?: { id?: string } | null;
        push?: { enabled: boolean; vapidPublicKey: string | null } | null;
    }>();
    const meId = String(page.props.auth?.user?.id ?? '');
    const tenantId = String(page.props.tenant?.id ?? '');
    const { setUnreadTotal, setActiveConversationId } = useTenantChatUnread();
    const canCreateGroups = can_create_groups ?? can_manage;
    const dateFnsLocale = i18n.language?.startsWith('en') ? enUS : esLocale;
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';

    const [conversations, setConversations] = useState(conversationsProp);
    const [active, setActive] = useState(activeProp);
    const [typingUsers, setTypingUsers] = useState<TypingUser[]>(
        activeProp?.typing ?? [],
    );
    const [echoReady, setEchoReady] = useState(false);
    const [openingId, setOpeningId] = useState<string | null>(null);

    const [listQuery, setListQuery] = useState('');
    const [dmOpen, setDmOpen] = useState(false);
    const [groupOpen, setGroupOpen] = useState(false);
    const [userQuery, setUserQuery] = useState('');
    const [body, setBody] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [filePreviews, setFilePreviews] = useState<(string | null)[]>([]);
    const [sending, setSending] = useState(false);
    const [emojiOpen, setEmojiOpen] = useState(false);
    const [replyTo, setReplyTo] = useState<ReplyPreview | null>(null);
    const [threadQuery, setThreadQuery] = useState('');
    const [searchOpen, setSearchOpen] = useState(false);
    const [searchResults, setSearchResults] = useState<ChatMessage[]>([]);
    const [searching, setSearching] = useState(false);
    const [highlightId, setHighlightId] = useState<string | null>(null);
    const focusAppliedRef = useRef(false);
    const jumpingRef = useRef(false);
    const [mentionOpen, setMentionOpen] = useState(false);
    const [mentionQuery, setMentionQuery] = useState('');
    const [mentionIndex, setMentionIndex] = useState(0);
    const [lightbox, setLightbox] = useState<{
        url: string;
        name: string;
    } | null>(null);
    const [actionMessage, setActionMessage] = useState<ChatMessage | null>(
        null,
    );
    const [soundOn, setSoundOn] = useState(() => isSoundEnabled());
    const [retentionValue, setRetentionValue] = useState<number | ''>(
        retention_days ?? '',
    );
    const [muting, setMuting] = useState(false);
    const [pinning, setPinning] = useState(false);
    const [editingMessage, setEditingMessage] = useState<ChatMessage | null>(
        null,
    );
    const [forwardMessage, setForwardMessage] = useState<ChatMessage | null>(
        null,
    );
    const [forwardTargetId, setForwardTargetId] = useState('');
    const [forwarding, setForwarding] = useState(false);
    const [deleteMessage, setDeleteMessage] = useState<ChatMessage | null>(
        null,
    );
    const [deleting, setDeleting] = useState(false);
    const [mediaOpen, setMediaOpen] = useState(false);
    const [mediaItems, setMediaItems] = useState<MediaGalleryItem[]>([]);
    const [mediaLoading, setMediaLoading] = useState(false);

    const bottomRef = useRef<HTMLDivElement | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);
    const messageRefs = useRef<Map<string, HTMLDivElement>>(new Map());
    const draftApplied = useRef(false);
    const lastSoundId = useRef<string | null>(null);
    const typingTimer = useRef<number | undefined>(undefined);
    const searchTimer = useRef<number | undefined>(undefined);
    const draftTimer = useRef<number | undefined>(undefined);
    const prevConversationId = useRef<string | null>(null);
    const skipNextDraftWrite = useRef(false);
    const pendingRetryRef = useRef<{
        body: string;
        files: File[];
        replyTo: ReplyPreview | null;
        conversationId: string;
    } | null>(null);

    const dmForm = useForm<{ user_id: string }>({ user_id: '' });
    const groupForm = useForm<{ name: string; user_ids: string[] }>({
        name: '',
        user_ids: [],
    });

    useEffect(() => {
        setConversations(conversationsProp);
    }, [conversationsProp]);

    useEffect(() => {
        setActive(activeProp);
        setTypingUsers(activeProp?.typing ?? []);
        setReplyTo(null);
        setEditingMessage(null);
        setThreadQuery('');
        setSearchResults([]);
        setHighlightId(null);
        if (activeProp?.messages?.length) {
            const last = activeProp.messages[activeProp.messages.length - 1];
            lastSoundId.current = last?.id ?? null;
        } else {
            lastSoundId.current = null;
        }
    }, [activeProp?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (activeProp && activeProp.id === active?.id) {
            setActive(activeProp);
            setTypingUsers(activeProp.typing ?? []);
        }
    }, [activeProp, active?.id]);

    useEffect(() => {
        setRetentionValue(retention_days ?? '');
    }, [retention_days]);

    // Borradores por conversación (localStorage) + draft inicial del servidor.
    useEffect(() => {
        const nextId = activeProp?.id ?? null;
        const prevId = prevConversationId.current;

        if (prevId && prevId !== nextId) {
            writeDraft(prevId, body);
        }

        if (nextId && nextId !== prevId) {
            const local = readDraft(nextId);
            skipNextDraftWrite.current = true;
            if (local != null) {
                setBody(local);
            } else if (draft && !draftApplied.current) {
                setBody(draft);
                draftApplied.current = true;
            } else {
                setBody('');
            }
        } else if (!nextId) {
            skipNextDraftWrite.current = true;
            setBody('');
        }

        prevConversationId.current = nextId;
        // Solo al cambiar de conversación.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeProp?.id]);

    useEffect(() => {
        if (!active?.id || editingMessage) return;
        if (skipNextDraftWrite.current) {
            skipNextDraftWrite.current = false;

            return;
        }
        if (draftTimer.current) window.clearTimeout(draftTimer.current);
        draftTimer.current = window.setTimeout(() => {
            writeDraft(active.id, body);
        }, 300);

        return () => {
            if (draftTimer.current) window.clearTimeout(draftTimer.current);
        };
    }, [body, active?.id, editingMessage]);

    // Presence heartbeat mientras la página de chat está abierta.
    useEffect(() => {
        let cancelled = false;

        const beat = () => {
            if (cancelled || document.visibilityState !== 'visible') return;
            void fetch('/comunicaciones/chat/presence', {
                method: 'POST',
                headers: csrfHeaders(true),
                credentials: 'same-origin',
                body: JSON.stringify({}),
            }).catch(() => undefined);
        };

        beat();
        const timer = window.setInterval(beat, PRESENCE_HEARTBEAT_MS);
        const onVisible = () => {
            if (document.visibilityState === 'visible') beat();
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, []);

    useEffect(() => {
        setUnreadTotal(unread_total);
    }, [unread_total, setUnreadTotal]);

    useEffect(() => {
        setActiveConversationId(active?.id ?? null);

        return () => setActiveConversationId(null);
    }, [active?.id, setActiveConversationId]);

    useAutoRefresh({
        only: ['conversations', 'active', 'unread_total', 'retention_days'],
        intervalMs: poll_ms || 6_000,
        enabled: !active?.id,
        busy: sending || dmOpen || groupOpen,
    });

    // Poll JSON del hilo activo (incluye typing). Con Echo listo, polling más lento como fallback.
    useEffect(() => {
        if (!active?.id) return;

        let cancelled = false;
        const conversationId = active.id;
        const basePoll = poll_ms || 4_000;
        const interval = echoReady
            ? Math.max(basePoll * 4, 20_000)
            : basePoll;

        const tick = async () => {
            if (cancelled || document.visibilityState !== 'visible' || sending) {
                return;
            }
            try {
                const res = await fetch(
                    `/comunicaciones/chat/${conversationId}/poll`,
                    {
                        headers: csrfHeaders(),
                        credentials: 'same-origin',
                    },
                );
                if (!res.ok || cancelled) return;
                const data = (await res.json()) as {
                    active: ActiveConversation;
                    conversations: ConversationSummary[];
                    unread_total: number;
                    typing?: TypingUser[];
                };
                if (cancelled) return;

                const prevLast =
                    active.messages[active.messages.length - 1]?.id ?? null;
                const nextMessages = data.active?.messages ?? [];
                const nextLast = nextMessages[nextMessages.length - 1];

                setActive(data.active);
                setConversations(data.conversations);
                setTypingUsers(
                    data.typing ?? data.active?.typing ?? [],
                );
                setUnreadTotal(data.unread_total ?? 0);

                if (
                    nextLast
                    && nextLast.id !== prevLast
                    && nextLast.id !== lastSoundId.current
                    && !nextLast.mine
                    && nextLast.user_id !== meId
                    && !data.active?.muted
                    && isSoundEnabled()
                ) {
                    playChatChime();
                }
                if (nextLast) {
                    lastSoundId.current = nextLast.id;
                }
            } catch {
                // Red intermitente.
            }
        };

        void tick();
        const timer = window.setInterval(tick, interval);

        const onVisible = () => {
            if (document.visibilityState === 'visible') void tick();
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [active?.id, poll_ms, sending, meId, setUnreadTotal, echoReady]); // eslint-disable-line react-hooks/exhaustive-deps

    // Echo/Reverb: reutiliza instancia; solo cambia de canal al cambiar hilo.
    useEffect(() => {
        if (!broadcast?.enabled || !broadcast.key || !active?.id || !tenantId) {
            setEchoReady(false);

            return;
        }

        let disposed = false;
        const typingClearTimers = new Map<string, number>();
        const conversationId = active.id;
        const channelName = `tenant.${tenantId}.chat.${conversationId}`;
        const presenceChannelName = `tenant.${tenantId}.chat.presence`;

        void (async () => {
            try {
                const echo = await getSharedEcho(broadcast);
                if (disposed || !echo) {
                    setEchoReady(false);

                    return;
                }

                const markEchoReady = (ready: boolean) => {
                    if (!disposed) setEchoReady(ready);
                };

                try {
                    echo.connector?.pusher?.connection?.bind('connected', () =>
                        markEchoReady(true),
                    );
                    echo.connector?.pusher?.connection?.bind('disconnected', () =>
                        markEchoReady(false),
                    );
                    echo.connector?.pusher?.connection?.bind('unavailable', () =>
                        markEchoReady(false),
                    );
                    echo.connector?.pusher?.connection?.bind('failed', () =>
                        markEchoReady(false),
                    );
                } catch {
                    // Sin connector pusher: seguimos con poll.
                }

                const refreshFromPoll = () => {
                    if (disposed) return;
                    void fetch(`/comunicaciones/chat/${conversationId}/poll`, {
                        headers: csrfHeaders(),
                        credentials: 'same-origin',
                    })
                        .then((r) => (r.ok ? r.json() : null))
                        .then((data) => {
                            if (!data || disposed) return;
                            setActive(data.active);
                            setConversations(data.conversations);
                            setTypingUsers(
                                data.typing ?? data.active?.typing ?? [],
                            );
                            setUnreadTotal(data.unread_total ?? 0);
                        })
                        .catch(() => undefined);
                };

                const applyPresence = (payload: {
                    user_id?: string;
                    online?: boolean;
                    last_seen_at?: string | null;
                }) => {
                    const uid = String(payload.user_id ?? '');
                    if (!uid || uid === meId) return;

                    setActive((prev) => {
                        if (!prev || prev.type !== 'direct') return prev;
                        const isPeer = (prev.participants ?? []).some(
                            (p) => p.id === uid,
                        );
                        if (!isPeer) return prev;

                        return {
                            ...prev,
                            peer_online: Boolean(payload.online),
                            peer_last_seen_at:
                                payload.last_seen_at ?? prev.peer_last_seen_at ?? null,
                        };
                    });

                    setConversations((prev) =>
                        prev.map((c) => {
                            if (c.type !== 'direct') return c;
                            const isPeer = (c.participants ?? []).some(
                                (p) => p.id === uid,
                            );
                            if (!isPeer) return c;

                            return {
                                ...c,
                                peer_online: Boolean(payload.online),
                                peer_last_seen_at:
                                    payload.last_seen_at ?? c.peer_last_seen_at ?? null,
                            };
                        }),
                    );
                };

                const channel = echo.private(channelName);
                for (const eventName of [
                    '.chat.message',
                    '.chat.message.updated',
                    '.chat.message.deleted',
                    '.chat.reaction',
                    '.chat.conversation.updated',
                ]) {
                    channel.listen(eventName, refreshFromPoll);
                }

                channel.listen(
                    '.chat.typing',
                    (payload: {
                        user_id?: string;
                        user_name?: string;
                        conversation_id?: string;
                    }) => {
                        const uid = String(payload.user_id ?? '');
                        if (!uid || uid === meId) return;
                        if (
                            payload.conversation_id
                            && String(payload.conversation_id) !== conversationId
                        ) {
                            return;
                        }

                        setTypingUsers((prev) => {
                            const next = prev.filter((u) => u.user_id !== uid);
                            next.push({
                                user_id: uid,
                                name: String(payload.user_name ?? 'Usuario'),
                            });

                            return next;
                        });

                        const prevTimer = typingClearTimers.get(uid);
                        if (prevTimer !== undefined) {
                            window.clearTimeout(prevTimer);
                        }
                        typingClearTimers.set(
                            uid,
                            window.setTimeout(() => {
                                typingClearTimers.delete(uid);
                                setTypingUsers((prev) =>
                                    prev.filter((u) => u.user_id !== uid),
                                );
                            }, 4_000),
                        );
                    },
                );

                channel.listen(
                    '.chat.read',
                    (payload: {
                        user_id?: string;
                        last_read_at?: string;
                        conversation_id?: string;
                    }) => {
                        const uid = String(payload.user_id ?? '');
                        if (!uid || uid === meId) return;
                        if (
                            payload.conversation_id
                            && String(payload.conversation_id) !== conversationId
                        ) {
                            return;
                        }

                        const lastReadAt = payload.last_read_at ?? null;
                        setActive((prev) => {
                            if (!prev) return prev;
                            const readerName =
                                prev.participants?.find((p) => p.id === uid)?.name
                                ?? 'Usuario';

                            return {
                                ...prev,
                                messages: prev.messages.map((m) => {
                                    if (!m.mine && m.user_id !== meId) return m;
                                    if (!m.created_at || !lastReadAt) return m;
                                    try {
                                        if (
                                            new Date(m.created_at).getTime()
                                            > new Date(lastReadAt).getTime()
                                        ) {
                                            return m;
                                        }
                                    } catch {
                                        return m;
                                    }

                                    const readBy = [...(m.read_by ?? [])];
                                    const idx = readBy.findIndex(
                                        (r) => r.user_id === uid,
                                    );
                                    const entry = {
                                        user_id: uid,
                                        name: readerName,
                                        read_at: lastReadAt,
                                    };
                                    if (idx >= 0) readBy[idx] = entry;
                                    else readBy.push(entry);

                                    return { ...m, read_by: readBy };
                                }),
                            };
                        });
                    },
                );

                channel.listen('.chat.presence', applyPresence);

                const presenceChannel = echo.private(presenceChannelName);
                presenceChannel.listen('.chat.presence', applyPresence);

                try {
                    channel.subscribed?.(() => markEchoReady(true));
                } catch {
                    // ignore
                }
            } catch {
                setEchoReady(false);
            }
        })();

        return () => {
            disposed = true;
            setEchoReady(false);
            typingClearTimers.forEach((id) => window.clearTimeout(id));
            typingClearTimers.clear();
            if (sharedEcho) {
                try {
                    sharedEcho.leave(channelName);
                    sharedEcho.leave(presenceChannelName);
                } catch {
                    // ignore
                }
            }
        };
    }, [
        broadcast?.enabled,
        broadcast?.key,
        broadcast?.host,
        broadcast?.port,
        broadcast?.scheme,
        active?.id,
        tenantId,
        meId,
        setUnreadTotal,
    ]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [active?.id, active?.messages?.length]);

    useEffect(() => {
        const urls = files.map((f) =>
            f.type.startsWith('image/') ? URL.createObjectURL(f) : null,
        );
        setFilePreviews(urls);

        return () => {
            urls.forEach((u) => {
                if (u) URL.revokeObjectURL(u);
            });
        };
    }, [files]);

    const filteredConversations = useMemo(() => {
        const q = listQuery.trim().toLowerCase();
        const base = !q
            ? conversations
            : conversations.filter((c) =>
                  `${c.title} ${c.last_message?.body ?? ''}`
                      .toLowerCase()
                      .includes(q),
              );

        return [...base].sort((a, b) => {
            const pinDiff = Number(Boolean(b.pinned)) - Number(Boolean(a.pinned));
            if (pinDiff !== 0) return pinDiff;

            return 0;
        });
    }, [conversations, listQuery]);

    const filteredUsers = useMemo(() => {
        const q = userQuery.trim().toLowerCase();
        if (!q) return users;

        return users.filter((u) =>
            `${u.name} ${u.email}`.toLowerCase().includes(q),
        );
    }, [users, userQuery]);

    const mentionCandidates = useMemo(() => {
        const base =
            active?.participants?.length
                ? active.participants
                    .filter((p) => p.id !== meId)
                    .map((p) => ({
                        id: p.id,
                        name: p.name,
                        email:
                            users.find((u) => u.id === p.id)?.email ?? '',
                    }))
                : users.filter((u) => u.id !== meId);

        const q = mentionQuery.trim().toLowerCase();
        if (!q) return base;

        return base.filter((u) => u.name.toLowerCase().includes(q));
    }, [active?.participants, users, meId, mentionQuery]);

    const threadItems = useMemo(
        () =>
            buildChatThreadItems(
                active?.messages ?? [],
                { today: t('today'), yesterday: t('yesterday') },
                dateFnsLocale,
            ),
        [active?.messages, t, dateFnsLocale],
    );

    const [mobileListOpen, setMobileListOpen] = useState(() => !active);

    useEffect(() => {
        if (!active) {
            setMobileListOpen(true);
        }
    }, [active]);

    const openConversation = (id: string) => {
        if (!id || id === active?.id || openingId) return;

        setMobileListOpen(false);
        setOpeningId(id);
        setTypingUsers([]);
        setReplyTo(null);
        setThreadQuery('');
        setSearchOpen(false);
        setSearchResults([]);

        // Optimistic: marcar selección en lista mientras llega el poll.
        setConversations((prev) =>
            prev.map((c) =>
                c.id === id ? { ...c, unread: 0 } : c,
            ),
        );

        void (async () => {
            try {
                const res = await fetch(`/comunicaciones/chat/${id}/poll`, {
                    headers: csrfHeaders(),
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    // Fallback Inertia si el poll falla (p.ej. 403).
                    router.get(
                        '/comunicaciones/chat',
                        { c: id },
                        {
                            preserveScroll: true,
                            replace: true,
                            only: ['conversations', 'active', 'unread_total'],
                        },
                    );

                    return;
                }

                const data = (await res.json()) as {
                    active: ActiveConversation;
                    conversations: ConversationSummary[];
                    unread_total: number;
                    typing?: TypingUser[];
                };

                setActive(data.active);
                setConversations(data.conversations);
                setTypingUsers(data.typing ?? data.active?.typing ?? []);
                setUnreadTotal(data.unread_total ?? 0);

                const url = new URL(window.location.href);
                url.searchParams.set('c', id);
                url.searchParams.delete('m');
                window.history.replaceState({}, '', url.toString());
            } catch {
                router.get(
                    '/comunicaciones/chat',
                    { c: id },
                    {
                        preserveScroll: true,
                        replace: true,
                        only: ['conversations', 'active', 'unread_total'],
                    },
                );
            } finally {
                setOpeningId(null);
            }
        })();
    };

    const openMobileList = () => setMobileListOpen(true);
    const closeMobileList = () => setMobileListOpen(false);

    const clearAttachments = () => {
        setFiles([]);
        if (fileRef.current) fileRef.current.value = '';
    };

    const refreshActivePoll = useCallback(async (conversationId: string) => {
        try {
            const res = await fetch(
                `/comunicaciones/chat/${conversationId}/poll`,
                {
                    headers: csrfHeaders(),
                    credentials: 'same-origin',
                },
            );
            if (!res.ok) return;
            const data = (await res.json()) as {
                active: ActiveConversation;
                conversations: ConversationSummary[];
                unread_total: number;
                typing?: TypingUser[];
            };
            setActive(data.active);
            setConversations(data.conversations);
            setTypingUsers(data.typing ?? data.active?.typing ?? []);
            setUnreadTotal(data.unread_total ?? 0);
        } catch {
            // ignore
        }
    }, [setUnreadTotal]);

    const postTyping = useCallback(() => {
        if (!active?.id) return;
        void fetch(`/comunicaciones/chat/${active.id}/typing`, {
            method: 'POST',
            headers: csrfHeaders(true),
            credentials: 'same-origin',
            body: JSON.stringify({}),
        }).catch(() => undefined);
    }, [active?.id]);

    const onBodyChange = (value: string) => {
        setBody(value);

        const ta = textareaRef.current;
        const caret = ta?.selectionStart ?? value.length;
        const before = value.slice(0, caret);
        const match = before.match(/@([^\s@]*)$/);
        if (match) {
            setMentionOpen(true);
            setMentionQuery(match[1] ?? '');
            setMentionIndex(0);
        } else {
            setMentionOpen(false);
            setMentionQuery('');
        }

        if (typingTimer.current) window.clearTimeout(typingTimer.current);
        typingTimer.current = window.setTimeout(() => {
            postTyping();
        }, 450);
    };

    const insertMention = (user: { id: string; name: string }) => {
        const ta = textareaRef.current;
        const caret = ta?.selectionStart ?? body.length;
        const before = body.slice(0, caret);
        const after = body.slice(caret);
        const replaced = before.replace(/@([^\s@]*)$/, `@${user.name} `);
        setBody(`${replaced}${after}`);
        setMentionOpen(false);
        setMentionQuery('');
        requestAnimationFrame(() => {
            textareaRef.current?.focus();
            const pos = replaced.length;
            textareaRef.current?.setSelectionRange(pos, pos);
        });
    };

    const sendPayload = useCallback(
        (opts: {
            conversationId: string;
            text: string;
            attachFiles: File[];
            reply: ReplyPreview | null;
        }) => {
            const { conversationId, text, attachFiles, reply } = opts;
            if (!text.trim() && attachFiles.length === 0) return;
            if (sending) return;

            const mentionIds = extractMentionIds(text, users);
            setSending(true);
            pendingRetryRef.current = {
                body: text,
                files: attachFiles,
                replyTo: reply,
                conversationId,
            };

            const formData = new FormData();
            formData.append('body', text.trim());
            if (reply?.id) {
                formData.append('reply_to_id', reply.id);
            }
            mentionIds.forEach((id) => {
                formData.append('mentioned_user_ids[]', id);
            });
            attachFiles.forEach((file) => {
                formData.append('attachments[]', file);
            });

            router.post(
                `/comunicaciones/chat/${conversationId}/messages`,
                formData as never,
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        pendingRetryRef.current = null;
                        setBody('');
                        setReplyTo(null);
                        clearAttachments();
                        setMentionOpen(false);
                        clearDraft(conversationId);
                    },
                    onError: () => {
                        toastManager.error({
                            id: 'chat-send-failed',
                            title: t('send_failed'),
                            description: t('send_failed_hint'),
                            duration: 10_000,
                            action: {
                                label: t('retry'),
                                onClick: () => {
                                    const pending = pendingRetryRef.current;
                                    if (!pending) return;
                                    setBody(pending.body);
                                    setFiles(pending.files);
                                    setReplyTo(pending.replyTo);
                                    sendPayload({
                                        conversationId: pending.conversationId,
                                        text: pending.body,
                                        attachFiles: pending.files,
                                        reply: pending.replyTo,
                                    });
                                },
                            },
                        });
                    },
                    onFinish: () => {
                        setSending(false);
                    },
                },
            );
        },
        [sending, users, t],
    );

    const submitMessage = (e: FormEvent) => {
        e.preventDefault();
        if (!active || sending) return;
        if (active.can_write === false) return;

        if (editingMessage) {
            const nextBody = body.trim();
            if (!nextBody) return;
            setSending(true);
            void fetch(
                `/comunicaciones/chat/messages/${editingMessage.id}`,
                {
                    method: 'PATCH',
                    headers: csrfHeaders(true),
                    credentials: 'same-origin',
                    body: JSON.stringify({ body: nextBody }),
                },
            )
                .then(async (res) => {
                    if (!res.ok) throw new Error('edit failed');
                    setEditingMessage(null);
                    const local = readDraft(active.id);
                    skipNextDraftWrite.current = true;
                    setBody(local ?? '');
                    await refreshActivePoll(active.id);
                })
                .catch(() => {
                    toastManager.error({ title: t('send_failed') });
                })
                .finally(() => setSending(false));

            return;
        }

        if (!body.trim() && files.length === 0) return;
        sendPayload({
            conversationId: active.id,
            text: body,
            attachFiles: files,
            reply: replyTo,
        });
    };

    const cancelEditing = () => {
        setEditingMessage(null);
        if (active?.id) {
            const local = readDraft(active.id);
            setBody(local ?? '');
        } else {
            setBody('');
        }
    };

    const startEditMessage = (m: ChatMessage) => {
        if (active?.id) {
            writeDraft(active.id, body);
        }
        setReplyTo(null);
        setEditingMessage(m);
        skipNextDraftWrite.current = true;
        setBody(m.body ?? '');
        requestAnimationFrame(() => textareaRef.current?.focus());
    };

    const confirmDeleteMessage = async () => {
        if (!active || !deleteMessage || deleting) return;
        setDeleting(true);
        try {
            const res = await fetch(
                `/comunicaciones/chat/messages/${deleteMessage.id}`,
                {
                    method: 'DELETE',
                    headers: csrfHeaders(true),
                    credentials: 'same-origin',
                },
            );
            if (!res.ok) throw new Error('delete failed');
            setDeleteMessage(null);
            await refreshActivePoll(active.id);
        } catch {
            toastManager.error({ title: t('send_failed') });
        } finally {
            setDeleting(false);
        }
    };

    const toggleReaction = async (
        message: ChatMessage,
        emoji: ChatReactionEmoji,
    ) => {
        if (!active) return;
        try {
            const res = await fetch(
                `/comunicaciones/chat/messages/${message.id}/reaction`,
                {
                    method: 'POST',
                    headers: csrfHeaders(true),
                    credentials: 'same-origin',
                    body: JSON.stringify({ emoji }),
                },
            );
            if (!res.ok) throw new Error('reaction failed');
            await refreshActivePoll(active.id);
        } catch {
            toastManager.error({ title: t('send_failed') });
        }
    };

    const submitForward = async () => {
        if (!active || !forwardMessage || !forwardTargetId || forwarding) return;
        setForwarding(true);
        try {
            const res = await fetch(
                `/comunicaciones/chat/messages/${forwardMessage.id}/forward`,
                {
                    method: 'POST',
                    headers: csrfHeaders(true),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        target_conversation_id: forwardTargetId,
                    }),
                },
            );
            if (!res.ok) throw new Error('forward failed');
            setForwardMessage(null);
            setForwardTargetId('');
            openConversation(forwardTargetId);
        } catch {
            toastManager.error({ title: t('send_failed') });
        } finally {
            setForwarding(false);
        }
    };

    const togglePin = async () => {
        if (!active || pinning) return;
        const next = !active.pinned;
        setPinning(true);
        setActive({ ...active, pinned: next });
        setConversations((prev) =>
            prev.map((c) =>
                c.id === active.id ? { ...c, pinned: next } : c,
            ),
        );
        try {
            const res = await fetch(`/comunicaciones/chat/${active.id}/pin`, {
                method: 'POST',
                headers: csrfHeaders(true),
                credentials: 'same-origin',
                body: JSON.stringify({ pinned: next }),
            });
            if (!res.ok) throw new Error('pin failed');
        } catch {
            setActive({ ...active, pinned: !next });
            toastManager.error({ title: t('send_failed') });
        } finally {
            setPinning(false);
        }
    };

    const openMediaGallery = async () => {
        if (!active) return;
        setMediaOpen(true);
        setMediaLoading(true);
        try {
            const res = await fetch(
                `/comunicaciones/chat/${active.id}/media`,
                {
                    headers: csrfHeaders(),
                    credentials: 'same-origin',
                },
            );
            if (!res.ok) throw new Error('media failed');
            const data = (await res.json()) as {
                media?: MediaGalleryItem[];
                items?: MediaGalleryItem[];
            };
            setMediaItems(data.media ?? data.items ?? []);
        } catch {
            setMediaItems([]);
            toastManager.error({ title: t('send_failed') });
        } finally {
            setMediaLoading(false);
        }
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

    const toggleMute = async () => {
        if (!active || muting) return;
        const next = !active.muted;
        setMuting(true);
        setActive({ ...active, muted: next });
        setConversations((prev) =>
            prev.map((c) =>
                c.id === active.id ? { ...c, muted: next } : c,
            ),
        );
        try {
            await fetch(`/comunicaciones/chat/${active.id}/mute`, {
                method: 'POST',
                headers: csrfHeaders(true),
                credentials: 'same-origin',
                body: JSON.stringify({ muted: next }),
            });
        } catch {
            setActive({ ...active, muted: !next });
        } finally {
            setMuting(false);
        }
    };

    const toggleSound = () => {
        const next = !soundOn;
        setSoundOn(next);
        try {
            window.localStorage.setItem(SOUND_KEY, next ? '1' : '0');
        } catch {
            // ignore
        }
    };

    const saveRetention = () => {
        if (!can_manage) return;
        router.post(
            '/comunicaciones/chat/retention',
            {
                chat_retention_days:
                    retentionValue === '' ? null : retentionValue,
            },
            { preserveScroll: true },
        );
    };

    const runThreadSearch = useCallback(
        (q: string) => {
            if (!active?.id) return;
            const trimmed = q.trim();
            if (trimmed.length < 2) {
                setSearchResults([]);
                setSearching(false);

                return;
            }
            setSearching(true);
            void fetch(
                `/comunicaciones/chat/${active.id}/search?q=${encodeURIComponent(trimmed)}`,
                {
                    headers: csrfHeaders(),
                    credentials: 'same-origin',
                },
            )
                .then((r) => (r.ok ? r.json() : null))
                .then((data: { results?: ChatMessage[] } | null) => {
                    setSearchResults(data?.results ?? []);
                })
                .catch(() => setSearchResults([]))
                .finally(() => setSearching(false));
        },
        [active?.id],
    );

    useEffect(() => {
        if (searchTimer.current) window.clearTimeout(searchTimer.current);
        searchTimer.current = window.setTimeout(() => {
            runThreadSearch(threadQuery);
        }, 280);

        return () => {
            if (searchTimer.current) window.clearTimeout(searchTimer.current);
        };
    }, [threadQuery, runThreadSearch]);

    const jumpToMessage = useCallback(
        async (id: string) => {
            setSearchOpen(false);

            const scrollAndHighlight = () => {
                setHighlightId(id);
                requestAnimationFrame(() => {
                    const el = messageRefs.current.get(id);
                    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
                window.setTimeout(() => setHighlightId(null), 2500);
            };

            if (messageRefs.current.has(id)) {
                scrollAndHighlight();

                return;
            }

            if (!active?.id || jumpingRef.current) {
                return;
            }

            jumpingRef.current = true;
            toastManager.info({
                id: `chat-jump-${id}`,
                title: t('jump_loading'),
                duration: 3_000,
            });

            try {
                const res = await fetch(
                    `/comunicaciones/chat/${active.id}/messages/${id}/context`,
                    {
                        headers: csrfHeaders(),
                        credentials: 'same-origin',
                    },
                );
                if (!res.ok) {
                    toastManager.error({
                        title: t('jump_not_found'),
                        duration: 4_000,
                    });

                    return;
                }

                const data = (await res.json()) as {
                    messages?: ChatMessage[];
                };
                const incoming = data.messages ?? [];
                if (incoming.length === 0) {
                    toastManager.error({
                        title: t('jump_not_found'),
                        duration: 4_000,
                    });

                    return;
                }

                setActive((prev) => {
                    if (!prev) return prev;
                    const byId = new Map(
                        prev.messages.map((m) => [m.id, m] as const),
                    );
                    for (const m of incoming) {
                        byId.set(m.id, m);
                    }
                    const merged = [...byId.values()].sort((a, b) =>
                        (a.created_at ?? '').localeCompare(b.created_at ?? ''),
                    );

                    return { ...prev, messages: merged };
                });

                window.setTimeout(() => {
                    scrollAndHighlight();
                }, 60);
            } catch {
                toastManager.error({
                    title: t('jump_not_found'),
                    duration: 4_000,
                });
            } finally {
                jumpingRef.current = false;
            }
        },
        [active?.id, t],
    );

    useEffect(() => {
        if (!focusMessageIdProp || !active?.id) {
            return;
        }
        if (focusAppliedRef.current) {
            return;
        }
        if ((active.messages?.length ?? 0) === 0) {
            return;
        }
        focusAppliedRef.current = true;
        void jumpToMessage(focusMessageIdProp);
    }, [
        focusMessageIdProp,
        active?.id,
        active?.messages?.length,
        jumpToMessage,
    ]);

    useEffect(() => {
        focusAppliedRef.current = false;
    }, [focusMessageIdProp, activeProp?.id]);

    const onComposerKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (mentionOpen && mentionCandidates.length > 0) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setMentionIndex(
                    (i) => (i + 1) % mentionCandidates.length,
                );

                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                setMentionIndex(
                    (i) =>
                        (i - 1 + mentionCandidates.length)
                        % mentionCandidates.length,
                );

                return;
            }
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                const pick = mentionCandidates[mentionIndex];
                if (pick) insertMention(pick);

                return;
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                setMentionOpen(false);

                return;
            }
        }

        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submitMessage(e);
        }
    };

    const typingLabel = useMemo(() => {
        if (typingUsers.length === 0) return null;
        if (typingUsers.length === 1) {
            return t('typing', { name: typingUsers[0].name });
        }
        const names = typingUsers.map((u) => u.name).join(', ');

        return t('typing_many', { names });
    }, [typingUsers, t]);

    const presenceLabel = useMemo(() => {
        if (!active || active.type !== 'direct') return null;
        if (active.peer_online) return t('presence_online');
        if (active.peer_last_seen_at) {
            try {
                const relative = formatDistanceToNow(
                    parseISO(active.peer_last_seen_at),
                    { addSuffix: true, locale: dateFnsLocale },
                );

                return t('presence_last_seen', { time: relative });
            } catch {
                return t('presence_unknown');
            }
        }

        return null;
    }, [active, t, dateFnsLocale]);

    const threadSubtitle = typingLabel
        ?? presenceLabel
        ?? (active?.type === 'group'
            ? t('participants', { count: active.participant_count })
            : t('dm_badge'));

    const canWrite = active == null || active.can_write !== false;

    const renderDeliveryStatus = (m: ChatMessage) => {
        if (!m.mine && m.user_id !== meId) return null;

        return (
            <ChatDeliveryReceipt
                status={m.delivery_status}
                readers={m.read_by}
                excludeUserId={meId}
                labels={{
                    sent: t('delivery_sent'),
                    delivered: t('delivery_delivered'),
                    read: t('delivery_read'),
                    seen: t('seen'),
                    seenBy: (names) => t('seen_by', { names }),
                }}
            />
        );
    };

    const linkifyText = (text: string, keyPrefix: string): ReactNode[] => {
        const nodes: ReactNode[] = [];
        let last = 0;
        let match: RegExpExecArray | null;
        const re = new RegExp(URL_IN_TEXT_RE.source, 'gi');
        while ((match = re.exec(text)) !== null) {
            if (match.index > last) {
                nodes.push(text.slice(last, match.index));
            }
            const href = match[0];
            nodes.push(
                <a
                    key={`${keyPrefix}-url-${match.index}`}
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="underline underline-offset-2 hover:opacity-90"
                    onClick={(ev) => ev.stopPropagation()}
                >
                    {href}
                </a>,
            );
            last = match.index + href.length;
        }
        if (last < text.length) nodes.push(text.slice(last));
        if (nodes.length === 0) nodes.push(text);

        return nodes;
    };

    const renderBodyWithMentions = (m: ChatMessage) => {
        if (isMessageDeleted(m)) {
            return (
                <p className="px-3.5 py-2 text-sm italic text-muted-foreground opacity-80">
                    {t('message_deleted')}
                </p>
            );
        }
        if (!m.body) return null;
        const mentions = m.mentions ?? [];
        if (mentions.length === 0) {
            return (
                <p className="whitespace-pre-wrap wrap-break-word px-3.5 py-2">
                    {linkifyText(m.body, m.id)}
                </p>
            );
        }
        const sorted = [...mentions].sort(
            (a, b) => b.name.length - a.name.length,
        );
        const parts: ReactNode[] = [];
        let rest = m.body;
        let key = 0;
        while (rest.length > 0) {
            let foundAt = -1;
            let found: { id: string; name: string } | null = null;
            for (const mention of sorted) {
                const token = `@${mention.name}`;
                const idx = rest.indexOf(token);
                if (idx !== -1 && (foundAt === -1 || idx < foundAt)) {
                    foundAt = idx;
                    found = mention;
                }
            }
            if (!found || foundAt < 0) {
                parts.push(...linkifyText(rest, `${m.id}-${key}`));
                break;
            }
            if (foundAt > 0) {
                parts.push(
                    ...linkifyText(rest.slice(0, foundAt), `${m.id}-${key}`),
                );
            }
            parts.push(
                <span
                    key={`m-${key++}`}
                    className="font-semibold underline decoration-emerald-300/60"
                >
                    @{found.name}
                </span>,
            );
            rest = rest.slice(foundAt + found.name.length + 1);
        }

        return (
            <p className="whitespace-pre-wrap wrap-break-word px-3.5 py-2">
                {parts}
            </p>
        );
    };

    return (
        <>
            <Head title={t('title')} />

            <ChatShell>
                <div
                    className={cn(
                        'flex items-center justify-between gap-3 border-b border-border/60 bg-linear-to-r from-emerald-50/90 via-card to-teal-50/40 px-4 py-3 dark:from-emerald-950/40 dark:via-card dark:to-teal-950/20',
                        active && 'max-lg:hidden',
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

                    <div className="flex shrink-0 items-center gap-1.5">
                        {page.props.push != null ? (
                            <PushNotificationPrompt
                                variant="labeled"
                                description={t('push_notifications_hint')}
                                className="max-sm:px-2"
                            />
                        ) : null}

                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="size-8 text-muted-foreground"
                            onClick={toggleSound}
                            aria-label={soundOn ? t('sound_on') : t('sound_off')}
                            title={soundOn ? t('sound_on') : t('sound_off')}
                        >
                            {soundOn ? (
                                <Volume2 className="size-4" />
                            ) : (
                                <VolumeX className="size-4" />
                            )}
                        </Button>

                        {can_manage ? (
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="hidden h-8 gap-1 text-xs sm:inline-flex"
                                    >
                                        {t('retention')}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent align="end" className="w-56 space-y-2 p-3">
                                    <p className="text-xs text-muted-foreground">
                                        {t('retention_hint')}
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {RETENTION_OPTIONS.map((d) => (
                                            <Button
                                                key={d}
                                                type="button"
                                                size="sm"
                                                variant={
                                                    retentionValue === d
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className={cn(
                                                    'h-7 text-xs',
                                                    retentionValue === d
                                                        && 'bg-emerald-600 hover:bg-emerald-700',
                                                )}
                                                onClick={() =>
                                                    setRetentionValue(d)
                                                }
                                            >
                                                {t('retention_days', {
                                                    count: d,
                                                })}
                                            </Button>
                                        ))}
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="w-full bg-emerald-600 hover:bg-emerald-700"
                                        onClick={saveRetention}
                                    >
                                        {t('retention_save')}
                                    </Button>
                                </PopoverContent>
                            </Popover>
                        ) : null}

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    size="sm"
                                    className="shrink-0 gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                                >
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
                                {canCreateGroups ? (
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
                </div>

                <div className="relative min-h-0 flex-1 overflow-hidden lg:grid lg:grid-cols-[minmax(17rem,21rem)_1fr]">
                    <ChatListAside
                        mobileTitle={t('conversations_title')}
                        mobileSubtitle={t('conversations_hint')}
                        hasActiveThread={active !== null}
                        mobileListOpen={mobileListOpen}
                        onCloseMobileList={closeMobileList}
                        onBackdropClick={closeMobileList}
                        toolbar={
                            <ChatSearchInput
                                value={listQuery}
                                onChange={(e) => setListQuery(e.target.value)}
                                placeholder={t('search_placeholder')}
                            />
                        }
                    >
                            {filteredConversations.length === 0 ? (
                                <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                                    {t('empty_list')}
                                </p>
                            ) : (
                                <ul className="divide-y divide-border/40">
                                    {filteredConversations.map((c, index) => {
                                        const selected = active?.id === c.id;

                                        return (
                                            <li
                                                key={c.id}
                                                className="animate-in fade-in slide-in-from-left-2 fill-mode-both duration-300"
                                                style={{
                                                    animationDelay: `${Math.min(index, 8) * 35}ms`,
                                                }}
                                            >
                                                <button
                                                    type="button"
                                                    disabled={openingId !== null}
                                                    onClick={() =>
                                                        openConversation(c.id)
                                                    }
                                                    className={cn(
                                                        'flex w-full cursor-pointer items-start gap-3 border-l-2 px-3 py-3.5 text-left transition-colors active:bg-emerald-50/70 lg:py-3',
                                                        selected || openingId === c.id
                                                            ? 'border-l-emerald-600 bg-emerald-50/80 dark:bg-emerald-950/35'
                                                            : 'border-l-transparent hover:bg-background/80',
                                                        openingId === c.id && 'opacity-70',
                                                        openingId !== null && openingId !== c.id && 'opacity-50',
                                                    )}
                                                >
                                                    <Avatar className="mt-0.5 size-11 border border-border/50 shadow-sm lg:size-10">
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
                                                            {c.is_support ||
                                                            c.kind === 'support' ? (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="h-5 shrink-0 rounded-full px-1.5 text-[10px] font-medium"
                                                                >
                                                                    {t('support_badge')}
                                                                </Badge>
                                                            ) : c.can_write === false ? (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="h-5 shrink-0 rounded-full px-1.5 text-[10px] font-medium"
                                                                >
                                                                    {t('observer_badge')}
                                                                </Badge>
                                                            ) : null}
                                                            {c.pinned ? (
                                                                <Pin
                                                                    className="size-3 shrink-0 text-amber-600 dark:text-amber-400"
                                                                    aria-label={t(
                                                                        'pinned_badge',
                                                                    )}
                                                                />
                                                            ) : null}
                                                            {c.muted ? (
                                                                <BellOff
                                                                    className="size-3 shrink-0 text-muted-foreground"
                                                                    aria-label={t(
                                                                        'muted_badge',
                                                                    )}
                                                                />
                                                            ) : null}
                                                            {c.unread > 0 ? (
                                                                <Badge className="h-5 shrink-0 rounded-full bg-emerald-600 px-1.5 text-[10px] text-white hover:bg-emerald-600">
                                                                    {c.unread}
                                                                </Badge>
                                                            ) : null}
                                                            <span className="ml-auto shrink-0 text-[10px] text-muted-foreground">
                                                                {formatListTime(
                                                                    c.updated_at,
                                                                    locale,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                            {c.last_message
                                                                ? `${c.last_message.mine ? `${t('you')}: ` : ''}${c.last_message.body}`
                                                                : c.type
                                                                      === 'group'
                                                                  ? t(
                                                                        'participants',
                                                                        {
                                                                            count: c.participant_count,
                                                                        },
                                                                    )
                                                                  : t(
                                                                        'type_direct',
                                                                    )}
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
                            'max-lg:absolute max-lg:inset-0 max-lg:z-10 max-lg:transition-[transform,filter] max-lg:duration-500 max-lg:ease-[cubic-bezier(0.22,1,0.36,1)]',
                            !active && 'max-lg:hidden',
                            active
                                && mobileListOpen
                                && 'max-lg:scale-[0.985] max-lg:brightness-[0.92]',
                        )}
                    >
                        {!active ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
                                <div className="flex size-16 items-center justify-center rounded-2xl bg-emerald-600/10 text-emerald-700 ring-1 ring-emerald-600/15 dark:text-emerald-300">
                                    <MessagesSquare
                                        className="size-7"
                                        aria-hidden
                                    />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold">
                                        {t('empty_thread')}
                                    </p>
                                    <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                        {t('empty_thread_hint')}
                                    </p>
                                </div>
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
                                            onClick={openMobileList}
                                            aria-label={t('back')}
                                        >
                                            <ChevronLeft className="size-5" />
                                        </Button>
                                        <Avatar className="size-9 border border-border/50 shadow-sm sm:size-10">
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
                                        <div className="min-w-0 flex-1">
                                            <p className="flex items-center gap-2 truncate text-sm font-semibold">
                                                <span className="truncate">
                                                    {active.title}
                                                </span>
                                                {active.is_support ||
                                                active.kind === 'support' ? (
                                                    <Badge
                                                        variant="secondary"
                                                        className="h-5 shrink-0 rounded-full px-1.5 text-[10px] font-medium"
                                                    >
                                                        {t('support_badge')}
                                                    </Badge>
                                                ) : null}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {threadSubtitle}
                                            </p>
                                        </div>
                                        {canWrite ? (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8 shrink-0 text-muted-foreground"
                                                    onClick={() =>
                                                        void togglePin()
                                                    }
                                                    disabled={pinning}
                                                    aria-label={
                                                        active.pinned
                                                            ? t('unpin')
                                                            : t('pin')
                                                    }
                                                >
                                                    {active.pinned ? (
                                                        <PinOff className="size-4 text-amber-600 dark:text-amber-400" />
                                                    ) : (
                                                        <Pin className="size-4" />
                                                    )}
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent side="bottom">
                                                {active.pinned
                                                    ? t('unpin')
                                                    : t('pin')}
                                            </TooltipContent>
                                        </Tooltip>
                                        ) : null}
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8 shrink-0 text-muted-foreground"
                                                    onClick={() =>
                                                        void openMediaGallery()
                                                    }
                                                    aria-label={t(
                                                        'media_gallery',
                                                    )}
                                                >
                                                    <Images className="size-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent side="bottom">
                                                {t('media_gallery')}
                                            </TooltipContent>
                                        </Tooltip>
                                        {canWrite ? (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8 shrink-0 text-muted-foreground"
                                                    onClick={() =>
                                                        void toggleMute()
                                                    }
                                                    disabled={muting}
                                                    aria-label={
                                                        active.muted
                                                            ? t('unmute_hint')
                                                            : t('mute_hint')
                                                    }
                                                >
                                                    {active.muted ? (
                                                        <BellOff className="size-4" />
                                                    ) : (
                                                        <Bell className="size-4" />
                                                    )}
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent side="bottom">
                                                {active.muted
                                                    ? t('unmute_hint')
                                                    : t('mute_hint')}
                                            </TooltipContent>
                                        </Tooltip>
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
                                        <Badge
                                            variant="outline"
                                            className="hidden shrink-0 text-[10px] sm:inline-flex"
                                        >
                                            {active.pinned
                                                ? t('pinned_badge')
                                                : active.type === 'group'
                                                  ? t('group_badge')
                                                  : t('dm_badge')}
                                        </Badge>
                                    </div>

                                    {searchOpen ? (
                                        <div className="relative px-1 sm:px-0">
                                            <ChatSearchInput
                                                value={threadQuery}
                                                onChange={(e) =>
                                                    setThreadQuery(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={t('search_thread')}
                                                autoFocus
                                            />
                                            {(threadQuery.trim().length >= 2
                                                || searching) && (
                                                <div className="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-border/60 bg-card shadow-lg">
                                                    {searching ? (
                                                        <p className="px-3 py-2 text-xs text-muted-foreground">
                                                            {t('polling')}
                                                        </p>
                                                    ) : searchResults.length
                                                      === 0 ? (
                                                        <p className="px-3 py-2 text-xs text-muted-foreground">
                                                            {t(
                                                                'search_thread_empty',
                                                            )}
                                                        </p>
                                                    ) : (
                                                        searchResults.map(
                                                            (m) => (
                                                                <button
                                                                    key={m.id}
                                                                    type="button"
                                                                    className="flex w-full flex-col gap-0.5 border-b border-border/40 px-3 py-2 text-left last:border-0 hover:bg-muted/60"
                                                                    onClick={() =>
                                                                        void jumpToMessage(
                                                                            m.id,
                                                                        )
                                                                    }
                                                                >
                                                                    <span className="text-[10px] font-medium text-emerald-700 dark:text-emerald-300">
                                                                        {
                                                                            m.user_name
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {formatClock(
                                                                            m.created_at,
                                                                            locale,
                                                                        )}
                                                                    </span>
                                                                    <span className="line-clamp-2 text-xs">
                                                                        {m.body}
                                                                    </span>
                                                                </button>
                                                            ),
                                                        )
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ) : null}
                                </header>

                                <ChatMessageScroller className="px-4" contentClassName="gap-3">
                                    {threadItems.map((item) => {
                                        if (item.kind === 'sep') {
                                            return (
                                                <div
                                                    key={item.key}
                                                    className="flex items-center justify-center py-1"
                                                >
                                                    <span className="rounded-full bg-muted/80 px-3 py-0.5 text-[10px] font-medium text-muted-foreground">
                                                        {item.label}
                                                    </span>
                                                </div>
                                            );
                                        }

                                        const m = item.message;
                                        const mine =
                                            m.mine
                                            ?? m.user_id === meId;
                                        const deleted = isMessageDeleted(m);
                                        const atts = deleted
                                            ? []
                                            : messageAttachments(m);
                                        const readLabel = renderDeliveryStatus(m);
                                        const reactions = m.reactions ?? [];

                                        return (
                                            <ChatMessagePressable
                                                key={m.id}
                                                disabled={deleted}
                                                onLongPress={() =>
                                                    setActionMessage(m)
                                                }
                                                onSwipeReply={() => {
                                                    setEditingMessage(null);
                                                    setReplyTo({
                                                        id: m.id,
                                                        body:
                                                            m.body
                                                            || (atts[0]
                                                                ?.name
                                                                ?? ''),
                                                        user_id: m.user_id,
                                                        user_name:
                                                            m.user_name,
                                                    });
                                                    textareaRef.current?.focus();
                                                }}
                                                className={cn(
                                                    'group flex',
                                                    mine
                                                        ? 'justify-end'
                                                        : 'justify-start',
                                                    highlightId === m.id
                                                        && 'animate-pulse',
                                                )}
                                            >
                                                <div
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
                                                    className="flex max-w-[min(100%,30rem)] flex-col gap-0.5"
                                                >
                                                    <div
                                                        className={cn(
                                                            'relative overflow-hidden rounded-2xl text-sm shadow-sm',
                                                            deleted
                                                                ? 'border border-dashed border-border/70 bg-muted/40 text-muted-foreground'
                                                                : mine
                                                                  ? 'rounded-br-md bg-emerald-600 text-white'
                                                                  : 'rounded-bl-md border border-border/60 bg-card text-foreground',
                                                            highlightId
                                                                === m.id
                                                                && 'ring-2 ring-amber-400/80 ring-offset-2 ring-offset-background',
                                                        )}
                                                    >
                                                        {!mine
                                                        && !deleted
                                                        && active.type
                                                            === 'group' ? (
                                                            <p className="px-3.5 pt-2 text-[10px] font-semibold tracking-wide text-emerald-700 uppercase dark:text-emerald-300">
                                                                {m.user_name}
                                                            </p>
                                                        ) : null}

                                                        {!deleted
                                                        && m.reply_to ? (
                                                            <button
                                                                type="button"
                                                                className={cn(
                                                                    'mx-2 mt-2 block w-[calc(100%-1rem)] rounded-lg border-l-2 px-2.5 py-1.5 text-left text-xs',
                                                                    mine
                                                                        ? 'border-emerald-200/70 bg-emerald-700/40'
                                                                        : 'border-emerald-500/50 bg-muted/60',
                                                                )}
                                                                onClick={() =>
                                                                    void jumpToMessage(
                                                                        m.reply_to!
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

                                                        {atts.length > 0 ? (
                                                            <div className="space-y-1.5 px-2 pt-2">
                                                                {atts.map(
                                                                    (
                                                                        att,
                                                                        i,
                                                                    ) =>
                                                                        att.is_image
                                                                        && att.url ? (
                                                                            <button
                                                                                key={`${m.id}-att-${i}`}
                                                                                type="button"
                                                                                className="block w-full overflow-hidden rounded-xl"
                                                                                onClick={() =>
                                                                                    setLightbox(
                                                                                        {
                                                                                            url: att.url!,
                                                                                            name: att.name,
                                                                                        },
                                                                                    )
                                                                                }
                                                                                aria-label={t(
                                                                                    'lightbox',
                                                                                )}
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
                                                                            </button>
                                                                        ) : (
                                                                            <a
                                                                                key={`${m.id}-att-${i}`}
                                                                                href={
                                                                                    att.url
                                                                                    ?? '#'
                                                                                }
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
                                                                                    {
                                                                                        att.name
                                                                                    }
                                                                                </span>
                                                                                <span className="ml-auto shrink-0 text-[10px] opacity-80">
                                                                                    {formatBytes(
                                                                                        att.size,
                                                                                    )}
                                                                                </span>
                                                                            </a>
                                                                        ),
                                                                )}
                                                            </div>
                                                        ) : null}

                                                        {deleted
                                                            ? renderBodyWithMentions(
                                                                  m,
                                                              )
                                                            : m.body
                                                              ? renderBodyWithMentions(
                                                                    m,
                                                                )
                                                              : atts.length
                                                                === 0
                                                                ? (
                                                                      <div className="h-2" />
                                                                  )
                                                                : null}

                                                        <div
                                                            className={cn(
                                                                'flex items-center gap-1 px-3.5 pb-1.5',
                                                                mine
                                                                    ? 'justify-end text-emerald-100/90'
                                                                    : 'justify-end text-muted-foreground',
                                                                deleted
                                                                    && 'text-muted-foreground',
                                                            )}
                                                        >
                                                            {!deleted ? (
                                                                <>
                                                                    <button
                                                                        type="button"
                                                                        className={cn(
                                                                            'mr-auto rounded p-0.5 touch-manipulation',
                                                                            mine
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
                                                                                        m.user_name,
                                                                                },
                                                                            );
                                                                            textareaRef.current?.focus();
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
                                                                        <PopoverTrigger asChild>
                                                                            <button
                                                                                type="button"
                                                                                className={cn(
                                                                                    'rounded p-0.5 touch-manipulation',
                                                                                    mine
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
                                                                        <DropdownMenuTrigger asChild>
                                                                            <button
                                                                                type="button"
                                                                                className={cn(
                                                                                    'rounded p-0.5 touch-manipulation',
                                                                                    mine
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
                                                                        <DropdownMenuContent align="end" className="w-44">
                                                                            <DropdownMenuItem
                                                                                onSelect={() => {
                                                                                    setForwardMessage(
                                                                                        m,
                                                                                    );
                                                                                    setForwardTargetId(
                                                                                        '',
                                                                                    );
                                                                                }}
                                                                            >
                                                                                <Forward className="size-4" />
                                                                                {t(
                                                                                    'forward',
                                                                                )}
                                                                            </DropdownMenuItem>
                                                                            {mine ? (
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
                                                            {m.edited_at
                                                            && !deleted ? (
                                                                <span className="text-[10px] opacity-80">
                                                                    {t(
                                                                        'edited',
                                                                    )}
                                                                </span>
                                                            ) : null}
                                                            <span className="text-[10px]">
                                                                {formatClock(
                                                                    m.created_at,
                                                                    locale,
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {!deleted
                                                    && reactions.length
                                                        > 0 ? (
                                                        <div
                                                            className={cn(
                                                                'flex flex-wrap gap-1 px-1',
                                                                mine
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
                                                                            r.reacted || r.mine
                                                                                ? 'border-emerald-500/50 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
                                                                                : 'border-border/60 bg-card text-foreground hover:bg-muted/70',
                                                                        )}
                                                                        onClick={() =>
                                                                            void toggleReaction(
                                                                                m,
                                                                                r.emoji as ChatReactionEmoji,
                                                                            )
                                                                        }
                                                                    >
                                                                        <span>
                                                                            {
                                                                                r.emoji
                                                                            }
                                                                        </span>
                                                                        {r.count
                                                                        > 1 ? (
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

                                                    {readLabel}
                                                </div>
                                            </ChatMessagePressable>
                                        );
                                    })}
                                    <div ref={bottomRef} />
                                </ChatMessageScroller>

                                {canWrite ? (
                                <form
                                    onSubmit={submitMessage}
                                    className="relative border-t border-border/60 bg-card/95 p-3 backdrop-blur-md"
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
                                                onClick={() => setReplyTo(null)}
                                                aria-label={t('reply_cancel')}
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
                                                    className="flex min-w-0 flex-1 items-center gap-2 rounded-xl border border-border/60 bg-muted/40 px-2.5 py-2 sm:flex-none sm:max-w-[14rem]"
                                                >
                                                    {filePreviews[idx] ? (
                                                        <img
                                                            src={
                                                                filePreviews[
                                                                    idx
                                                                ]!
                                                            }
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
                                                            {formatBytes(
                                                                file.size,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-7"
                                                        onClick={() =>
                                                            setFiles((prev) =>
                                                                prev.filter(
                                                                    (
                                                                        _,
                                                                        i,
                                                                    ) =>
                                                                        i
                                                                        !== idx,
                                                                ),
                                                            )
                                                        }
                                                        aria-label={t(
                                                            'remove_attachment',
                                                        )}
                                                    >
                                                        <X className="size-3.5" />
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    ) : null}

                                    {mentionOpen
                                    && mentionCandidates.length > 0 ? (
                                        <div className="absolute bottom-full left-3 z-30 mb-1 max-h-40 w-64 overflow-y-auto rounded-xl border border-border/60 bg-card shadow-lg">
                                            <p className="border-b border-border/40 px-2.5 py-1.5 text-[10px] text-muted-foreground">
                                                {t('mention_hint')}
                                            </p>
                                            {mentionCandidates.map((u, i) => (
                                                <button
                                                    key={u.id}
                                                    type="button"
                                                    className={cn(
                                                        'flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm hover:bg-muted/70',
                                                        i === mentionIndex
                                                            && 'bg-emerald-50 dark:bg-emerald-950/40',
                                                    )}
                                                    onMouseDown={(ev) => {
                                                        ev.preventDefault();
                                                        insertMention(u);
                                                    }}
                                                >
                                                    <Avatar className="size-6">
                                                        <AvatarFallback className="bg-emerald-100 text-[9px] text-emerald-800">
                                                            {initials(u.name)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="truncate">
                                                        {u.name}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    ) : null}

                                    <div className="flex items-end gap-1.5">
                                        <input
                                            ref={fileRef}
                                            type="file"
                                            multiple
                                            className="hidden"
                                            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
                                            onChange={(e) => {
                                                const picked = Array.from(
                                                    e.target.files ?? [],
                                                );
                                                setFiles((prev) =>
                                                    [
                                                        ...prev,
                                                        ...picked,
                                                    ].slice(0, MAX_ATTACHMENTS),
                                                );
                                                if (fileRef.current) {
                                                    fileRef.current.value = '';
                                                }
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
                                                editingMessage != null
                                                || files.length >= MAX_ATTACHMENTS
                                            }
                                            aria-label={t('attach')}
                                            title={
                                                files.length
                                                >= MAX_ATTACHMENTS
                                                    ? t('attachments_max')
                                                    : t('attach')
                                            }
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
                                            ref={textareaRef}
                                            value={body}
                                            onChange={(e) =>
                                                onBodyChange(e.target.value)
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
                                            className="min-h-10 max-h-28 flex-1 resize-none"
                                            onKeyDown={onComposerKeyDown}
                                        />
                                        <Button
                                            type="submit"
                                            size="icon"
                                            disabled={
                                                editingMessage
                                                    ? !body.trim() || sending
                                                    : (!body.trim()
                                                          && files.length
                                                              === 0)
                                                      || sending
                                            }
                                            className="size-10 shrink-0 bg-emerald-600 hover:bg-emerald-700"
                                            aria-label={
                                                editingMessage
                                                    ? t('save_edit')
                                                    : t('send')
                                            }
                                        >
                                            {editingMessage ? (
                                                <Check className="size-4" />
                                            ) : (
                                                <SendHorizontal className="size-4" />
                                            )}
                                        </Button>
                                    </div>
                                    <p className="mt-1.5 flex items-center gap-1 text-[10px] text-muted-foreground">
                                        <ImageIcon className="size-3" />
                                        {t('attach_hint')}
                                    </p>
                                </form>
                                ) : (
                                    <div className="border-t border-border/60 bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                                        {t('observer_hint')}
                                    </div>
                                )}
                            </>
                        )}
                    </section>
                </div>
            </ChatShell>

            <ChatImageLightbox
                open={!!lightbox}
                url={lightbox?.url ?? null}
                name={lightbox?.name}
                title={t('lightbox')}
                onOpenChange={(open) => {
                    if (!open) setLightbox(null);
                }}
            />

            <ChatMessageActionSheet
                open={!!actionMessage}
                onOpenChange={(open) => {
                    if (!open) setActionMessage(null);
                }}
                mine={Boolean(
                    actionMessage
                    && (actionMessage.mine || actionMessage.user_id === meId),
                )}
                preview={actionMessage?.body}
                reactionEmojis={REACTION_EMOJIS}
                labels={{
                    title: t('message_actions'),
                    hint: t('message_actions_hint'),
                    reply: t('reply'),
                    react: t('react'),
                    forward: t('forward'),
                    edit: t('edit'),
                    delete: t('delete'),
                }}
                onReply={() => {
                    if (!actionMessage) return;
                    const atts = messageAttachments(actionMessage);
                    setEditingMessage(null);
                    setReplyTo({
                        id: actionMessage.id,
                        body:
                            actionMessage.body
                            || (atts[0]?.name ?? ''),
                        user_id: actionMessage.user_id,
                        user_name: actionMessage.user_name,
                    });
                    textareaRef.current?.focus();
                }}
                onReact={(emoji) => {
                    if (!actionMessage) return;
                    void toggleReaction(
                        actionMessage,
                        emoji as ChatReactionEmoji,
                    );
                }}
                onForward={() => {
                    if (!actionMessage) return;
                    setForwardMessage(actionMessage);
                    setForwardTargetId('');
                }}
                onEdit={
                    actionMessage
                    && (actionMessage.mine || actionMessage.user_id === meId)
                        ? () => startEditMessage(actionMessage)
                        : undefined
                }
                onDelete={
                    actionMessage
                    && (actionMessage.mine || actionMessage.user_id === meId)
                        ? () => setDeleteMessage(actionMessage)
                        : undefined
                }
            />

            <Dialog
                open={mediaOpen}
                onOpenChange={(open) => {
                    setMediaOpen(open);
                    if (!open) setMediaItems([]);
                }}
            >
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-lg">
                    <DialogHeader className="border-b border-border/60 px-5 py-4">
                        <DialogTitle className="text-base">
                            {t('media_gallery_title')}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {t('media_gallery')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[70dvh] overflow-y-auto p-4">
                        {mediaLoading ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                {t('media_gallery_loading')}
                            </p>
                        ) : mediaItems.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                {t('media_gallery_empty')}
                            </p>
                        ) : (
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {mediaItems.map((item, idx) => (
                                    <button
                                        key={`${item.url}-${idx}`}
                                        type="button"
                                        className="overflow-hidden rounded-xl border border-border/50 bg-muted/30"
                                        onClick={() => {
                                            setLightbox({
                                                url: item.url,
                                                name: item.name,
                                            });
                                        }}
                                        aria-label={item.name}
                                    >
                                        <img
                                            src={item.url}
                                            alt={item.name}
                                            className="aspect-square w-full object-cover"
                                        />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                open={!!forwardMessage}
                onOpenChange={(open) => {
                    if (!open) {
                        setForwardMessage(null);
                        setForwardTargetId('');
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
                        {conversations.filter((c) => c.id !== active?.id)
                            .length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                {t('forward_empty')}
                            </p>
                        ) : (
                            conversations
                                .filter((c) => c.id !== active?.id)
                                .map((c) => (
                                    <button
                                        key={c.id}
                                        type="button"
                                        onClick={() =>
                                            setForwardTargetId(c.id)
                                        }
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
                                                    initials(c.title)
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
                        >
                            {t('cancel')}
                        </Button>
                        <Button
                            type="button"
                            className="bg-emerald-600 hover:bg-emerald-700"
                            disabled={!forwardTargetId || forwarding}
                            onClick={() => void submitForward()}
                        >
                            {t('forward_submit')}
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
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>{t('delete_confirm')}</DialogTitle>
                        <DialogDescription>
                            {t('delete_confirm_hint')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteMessage(null)}
                        >
                            {t('cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={deleting}
                            onClick={() => void confirmDeleteMessage()}
                        >
                            {t('delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={dmOpen} onOpenChange={setDmOpen}>
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                    <DialogHeader className="border-b border-border/60 bg-linear-to-r from-emerald-50/90 to-card px-5 py-4 dark:from-emerald-950/40">
                        <div className="flex items-center gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm">
                                <UserRound className="size-4" aria-hidden />
                            </span>
                            <div className="min-w-0 text-left">
                                <DialogTitle className="text-base">
                                    {t('direct_title')}
                                </DialogTitle>
                                <DialogDescription className="text-xs">
                                    {t('direct_hint')}
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                    <div className="space-y-3 p-4">
                        <div className="relative">
                            <ChatSearchInput
                                value={userQuery}
                                onChange={(e) => setUserQuery(e.target.value)}
                                placeholder={t('direct_search')}
                                className="h-10 bg-muted/30"
                            />
                        </div>
                        <div className="max-h-72 space-y-1 overflow-y-auto rounded-xl border border-border/50 bg-muted/15 p-1.5">
                            {users.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    {t('direct_empty')}
                                </p>
                            ) : filteredUsers.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    {t('no_users_match')}
                                </p>
                            ) : (
                                filteredUsers.map((u) => (
                                    <button
                                        key={u.id}
                                        type="button"
                                        disabled={dmForm.processing}
                                        onClick={() => startDm(u.id)}
                                        className="flex w-full cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2.5 text-left transition-colors hover:bg-emerald-50/80 dark:hover:bg-emerald-950/30"
                                    >
                                        <Avatar className="size-10 border border-border/50 shadow-sm">
                                            <AvatarFallback className="bg-emerald-100 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                                {initials(u.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {u.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {u.email}
                                            </p>
                                        </div>
                                        <span className="text-[10px] font-medium text-emerald-700 dark:text-emerald-300">
                                            {t('start_chat')}
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={groupOpen} onOpenChange={setGroupOpen}>
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                    <form onSubmit={submitGroup}>
                        <DialogHeader className="border-b border-border/60 bg-linear-to-r from-sky-50/90 to-card px-5 py-4 dark:from-sky-950/40">
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-xl bg-sky-600 text-white shadow-sm">
                                    <Users className="size-4" aria-hidden />
                                </span>
                                <div className="min-w-0 text-left">
                                    <DialogTitle className="text-base">
                                        {t('group_title')}
                                    </DialogTitle>
                                    <DialogDescription className="text-xs">
                                        {t('group_hint')}
                                    </DialogDescription>
                                </div>
                            </div>
                        </DialogHeader>

                        <div className="space-y-4 p-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="group-name">
                                    {t('group_name')}
                                </Label>
                                <Input
                                    id="group-name"
                                    value={groupForm.data.name}
                                    onChange={(e) =>
                                        groupForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={t('group_name_placeholder')}
                                    className="h-10 border-border/60"
                                />
                                {groupForm.errors.name ? (
                                    <p className="text-xs text-destructive">
                                        {groupForm.errors.name}
                                    </p>
                                ) : null}
                            </div>

                            {groupForm.data.user_ids.length > 0 ? (
                                <div className="flex flex-wrap gap-1.5">
                                    {groupForm.data.user_ids.map((id) => {
                                        const u = users.find((x) => x.id === id);
                                        if (!u) return null;

                                        return (
                                            <button
                                                key={id}
                                                type="button"
                                                onClick={() => toggleMember(id)}
                                                className="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-emerald-600/20 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800 transition-colors hover:bg-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-200"
                                            >
                                                <Avatar className="size-4">
                                                    <AvatarFallback className="bg-emerald-200 text-[8px] text-emerald-900">
                                                        {initials(u.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span className="max-w-28 truncate">
                                                    {u.name}
                                                </span>
                                                <X className="size-3 opacity-70" />
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : null}

                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between gap-2">
                                    <Label>{t('group_members')}</Label>
                                    <span className="text-[10px] text-muted-foreground">
                                        {t('group_selected', {
                                            count: groupForm.data.user_ids
                                                .length,
                                        })}
                                    </span>
                                </div>
                                <div className="relative">
                                    <ChatSearchInput
                                        value={userQuery}
                                        onChange={(e) =>
                                            setUserQuery(e.target.value)
                                        }
                                        placeholder={t('direct_search')}
                                        className="h-10 bg-muted/30"
                                    />
                                </div>
                                <div className="max-h-52 space-y-1 overflow-y-auto rounded-xl border border-border/50 bg-muted/15 p-1.5">
                                    {filteredUsers.length === 0 ? (
                                        <p className="py-6 text-center text-xs text-muted-foreground">
                                            {t('no_users_match')}
                                        </p>
                                    ) : (
                                        filteredUsers.map((u) => {
                                            const selected =
                                                groupForm.data.user_ids.includes(
                                                    u.id,
                                                );

                                            return (
                                                <button
                                                    key={u.id}
                                                    type="button"
                                                    onClick={() =>
                                                        toggleMember(u.id)
                                                    }
                                                    className={cn(
                                                        'flex w-full cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm transition-colors',
                                                        selected
                                                            ? 'bg-emerald-50 ring-1 ring-emerald-600/20 dark:bg-emerald-950/40'
                                                            : 'hover:bg-background/90',
                                                    )}
                                                >
                                                    <Avatar className="size-9 border border-border/40">
                                                        <AvatarFallback
                                                            className={cn(
                                                                'text-[10px] font-semibold',
                                                                selected
                                                                    ? 'bg-emerald-600 text-white'
                                                                    : 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
                                                            )}
                                                        >
                                                            {initials(u.name)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate font-medium">
                                                            {u.name}
                                                        </p>
                                                        <p className="truncate text-xs text-muted-foreground">
                                                            {u.email}
                                                        </p>
                                                    </div>
                                                    <span
                                                        className={cn(
                                                            'flex size-5 items-center justify-center rounded-full border',
                                                            selected
                                                                ? 'border-emerald-600 bg-emerald-600 text-white'
                                                                : 'border-muted-foreground/30',
                                                        )}
                                                    >
                                                        {selected ? (
                                                            <Check className="size-3" />
                                                        ) : null}
                                                    </span>
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

                        <DialogFooter className="gap-2 border-t border-border/60 bg-muted/20 px-4 py-3 sm:gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setGroupOpen(false)}
                                className="cursor-pointer"
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
                                className="cursor-pointer bg-emerald-600 hover:bg-emerald-700"
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
