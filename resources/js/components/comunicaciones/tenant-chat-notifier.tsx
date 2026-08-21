import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useTenantChatUnread } from '@/contexts/tenant-chat-unread-context';
import { usePermission } from '@/hooks/use-permission';
import { toastManager } from '@/lib/toast';

type InboxPing = {
    unread_total: number;
    latest: {
        message_id: string;
        conversation_id: string;
        user_name: string;
        preview: string;
        created_at: string | null;
        muted?: boolean;
        is_mention?: boolean;
    } | null;
};

const STORAGE_KEY = 'vetsaas.tenant_chat.last_ping_id';
const SOUND_KEY = 'tenant-chat-sound';
const POLL_MS = 10_000;

function isSoundEnabled(): boolean {
    try {
        return window.localStorage.getItem(SOUND_KEY) !== '0';
    } catch {
        return true;
    }
}

function playChatChime(): void {
    if (!isSoundEnabled()) {
        return;
    }
    try {
        const Ctx =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;
        if (!Ctx) {
            return;
        }
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
        // Silencio si el navegador bloquea audio sin gesto.
    }
}

/**
 * Polling global: badge + toast + chime cuando llega un mensaje nuevo.
 */
export function TenantChatNotifier() {
    const { t } = useTranslation('chat-interno');
    const { can } = usePermission();
    const page = usePage();
    const { setUnreadTotal, activeConversationId } = useTenantChatUnread();
    const sharedUnread = page.props.tenant_chat?.unread_total;
    const lastNotifiedId = useRef<string | null>(null);
    const bootstrapped = useRef(false);
    const activeRef = useRef(activeConversationId);
    activeRef.current = activeConversationId;
    const allowed = can('comunicaciones-chat.view');

    useEffect(() => {
        if (typeof sharedUnread === 'number') {
            setUnreadTotal(sharedUnread);
        }
    }, [sharedUnread, setUnreadTotal]);

    useEffect(() => {
        if (!allowed) {
            return;
        }

        let cancelled = false;
        let timer: number | undefined;

        const tick = async () => {
            if (cancelled || document.visibilityState !== 'visible') {
                return;
            }

            try {
                const res = await fetch('/comunicaciones/chat/inbox', {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok || cancelled) {
                    return;
                }

                const data = (await res.json()) as InboxPing;
                setUnreadTotal(data.unread_total ?? 0);

                const latest = data.latest;
                if (!latest?.message_id) {
                    return;
                }

                if (!bootstrapped.current) {
                    lastNotifiedId.current = latest.message_id;
                    window.sessionStorage.setItem(STORAGE_KEY, latest.message_id);
                    bootstrapped.current = true;

                    return;
                }

                if (lastNotifiedId.current === latest.message_id) {
                    return;
                }

                if (activeRef.current === latest.conversation_id) {
                    lastNotifiedId.current = latest.message_id;
                    window.sessionStorage.setItem(STORAGE_KEY, latest.message_id);

                    return;
                }

                lastNotifiedId.current = latest.message_id;
                window.sessionStorage.setItem(STORAGE_KEY, latest.message_id);

                const isMention = latest.is_mention === true;

                // Menciones rompen mute; el resto silenciado no toastea.
                if (!isMention && latest.muted === true) {
                    return;
                }

                playChatChime();
                toastManager.info({
                    id: `chat-ping-${latest.message_id}`,
                    title: isMention
                        ? t('toast_mention_title', { name: latest.user_name })
                        : t('toast_title', { name: latest.user_name }),
                    description: isMention
                        ? `${latest.user_name}: ${latest.preview}`
                        : latest.preview,
                    duration: 6_000,
                    action: {
                        label: t('toast_open'),
                        onClick: () => {
                            const qs = new URLSearchParams({
                                c: latest.conversation_id,
                            });
                            if (isMention) {
                                qs.set('m', latest.message_id);
                            }
                            router.visit(`/comunicaciones/chat?${qs.toString()}`);
                        },
                    },
                });
            } catch {
                // Red intermitente: reintentar en el próximo ciclo.
            }
        };

        void tick();
        timer = window.setInterval(() => {
            void tick();
        }, POLL_MS);

        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                void tick();
            }
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            cancelled = true;
            if (timer) {
                window.clearInterval(timer);
            }
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [allowed, setUnreadTotal, t]);

    return null;
}
