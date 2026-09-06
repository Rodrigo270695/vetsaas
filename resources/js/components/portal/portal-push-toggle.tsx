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
        <div className="min-w-0 flex-1 space-y-1 md:flex-none">
            <button
                type="button"
                disabled={busy || on}
                onClick={() => void activate()}
                className="inline-flex h-12 min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-amber-400 px-3 text-xs font-semibold text-amber-950 shadow-sm transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-70 md:h-10 md:w-auto md:rounded-full md:text-sm"
            >
                {on ? <Bell className="size-4 shrink-0" /> : <BellOff className="size-4 shrink-0" />}
                <span className="md:hidden">{on ? t('home.push_off_short') : t('home.push_on_short')}</span>
                <span className="hidden md:inline">{on ? t('home.push_off') : t('home.push_on')}</span>
            </button>
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
