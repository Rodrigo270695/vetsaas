import { Bell, BellOff } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { isPushSupported, waitForServiceWorker } from '@/lib/push-subscription';

type Props = {
    enabled: boolean;
    vapid: string;
    subscribeUrl: string;
};

function readXsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m?.[1] ? decodeURIComponent(m[1]) : '';
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i += 1) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

export function PortalPushToggle({ enabled, vapid, subscribeUrl }: Props) {
    const { t } = useTranslation('portal-propietario');
    const [on, setOn] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (!enabled || !vapid || !isPushSupported()) {
        return null;
    }

    const activate = async () => {
        setBusy(true);
        setError(null);
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                throw new Error('denied');
            }
            const registration = await waitForServiceWorker();
            const existing = await registration.pushManager.getSubscription();
            if (existing) {
                await existing.unsubscribe();
            }
            const keyBytes = urlBase64ToUint8Array(vapid.trim());
            const sub = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: keyBytes.buffer.slice(
                    keyBytes.byteOffset,
                    keyBytes.byteOffset + keyBytes.byteLength,
                ),
            });
            const body = sub.toJSON();
            const res = await fetch(subscribeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': readXsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    endpoint: body.endpoint,
                    keys: body.keys,
                    contentEncoding: 'aes128gcm',
                }),
            });
            if (!res.ok) {
                throw new Error('fail');
            }
            setOn(true);
        } catch {
            setError(t('home.push_fail'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <button
                type="button"
                disabled={busy || on}
                onClick={() => void activate()}
                className="inline-flex size-11 min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-full bg-slate-100 text-slate-800 ring-1 ring-slate-200/80 transition hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-70 dark:bg-white/10 dark:text-white dark:ring-white/10"
                aria-label={on ? t('home.push_off') : t('home.push_on')}
                title={error ?? (on ? t('home.push_off') : t('home.push_on'))}
            >
                {on ? <Bell className="size-4" /> : <BellOff className="size-4" />}
            </button>
        </>
    );
}
