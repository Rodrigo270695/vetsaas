import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { usePlatformSupportChatUnread } from '@/contexts/platform-support-chat-unread-context';
import { usePermission } from '@/hooks/use-permission';
import { toastManager } from '@/lib/toast';

type InboxPing = {
    unread_total: number;
    latest: {
        tenant_id: string;
        tenant_nombre: string;
        preview: string;
        last_message_at: string | null;
        fingerprint: string;
    } | null;
};

const STORAGE_KEY = 'vetsaas.platform_support_chat.last_ping';
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
 * Polling global (panel plataforma): badge + toast cuando una clínica responde.
 */
export function PlatformSupportChatNotifier() {
    const { t } = useTranslation('plataforma-chat-soporte');
    const { can } = usePermission();
    const page = usePage();
    const { setUnreadTotal, activeTenantId } = usePlatformSupportChatUnread();
    const sharedUnread = page.props.platform_support_chat?.unread_total;
    const lastNotified = useRef<string | null>(null);
    const bootstrapped = useRef(false);
    const activeRef = useRef(activeTenantId);
    activeRef.current = activeTenantId;
    const allowed = can('plataforma-chat-soporte.view');

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
                const res = await fetch('/plataforma/chat-soporte/inbox', {
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
                if (!latest?.fingerprint) {
                    return;
                }

                if (!bootstrapped.current) {
                    lastNotified.current = latest.fingerprint;
                    window.sessionStorage.setItem(STORAGE_KEY, latest.fingerprint);
                    bootstrapped.current = true;
                    return;
                }

                if (lastNotified.current === latest.fingerprint) {
                    return;
                }

                if (activeRef.current === latest.tenant_id) {
                    lastNotified.current = latest.fingerprint;
                    window.sessionStorage.setItem(STORAGE_KEY, latest.fingerprint);
                    return;
                }

                lastNotified.current = latest.fingerprint;
                window.sessionStorage.setItem(STORAGE_KEY, latest.fingerprint);

                playChatChime();
                toastManager.info({
                    id: `platform-support-ping-${latest.fingerprint}`,
                    title: t('toast_title', { name: latest.tenant_nombre }),
                    description: latest.preview,
                    duration: 6_000,
                    action: {
                        label: t('toast_open'),
                        onClick: () => {
                            router.visit(
                                `/plataforma/chat-soporte?tenant=${encodeURIComponent(latest.tenant_id)}`,
                            );
                        },
                    },
                });
            } catch {
                // Red intermitente.
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
