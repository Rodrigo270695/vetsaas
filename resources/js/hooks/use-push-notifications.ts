import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import {
    isPushSupported,
    isServiceWorkerControlling,
    subscribeToPush,
    unsubscribeFromPush,
    waitForServiceWorker,
} from '@/lib/push-subscription';

type PushShared = {
    enabled: boolean;
    vapidPublicKey: string | null;
};

type UsePushNotificationsResult = {
    supported: boolean;
    permission: NotificationPermission | 'unsupported';
    subscribed: boolean;
    swReady: boolean;
    loading: boolean;
    error: string | null;
    enable: () => Promise<void>;
    disable: () => Promise<void>;
};

export function usePushNotifications(): UsePushNotificationsResult {
    const { push } = usePage().props as { push?: PushShared | null };
    const [permission, setPermission] = useState<NotificationPermission | 'unsupported'>(
        isPushSupported() ? Notification.permission : 'unsupported',
    );
    const [subscribed, setSubscribed] = useState(false);
    const [swReady, setSwReady] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const supported = isPushSupported() && Boolean(push?.enabled && push?.vapidPublicKey);

    useEffect(() => {
        if (!supported) {
            setSwReady(false);
            return;
        }

        let cancelled = false;

        const syncState = async (): Promise<void> => {
            try {
                const registration = await waitForServiceWorker();
                if (cancelled) {
                    return;
                }

                const existing = await registration.pushManager.getSubscription();
                setSubscribed(existing !== null);
                setSwReady(isServiceWorkerControlling());
            } catch {
                if (!cancelled) {
                    setSwReady(false);
                    setSubscribed(false);
                }
            }
        };

        void syncState();

        return () => {
            cancelled = true;
        };
    }, [supported]);

    const enable = useCallback(async () => {
        if (!supported || !push?.vapidPublicKey) {
            setError('Las notificaciones push no están configuradas en el servidor.');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const registration = await waitForServiceWorker();
            const result = await Notification.requestPermission();
            setPermission(result);

            if (result !== 'granted') {
                setError('Permiso de notificaciones denegado.');
                return;
            }

            await subscribeToPush(registration, push.vapidPublicKey);
            setSubscribed(true);
            setSwReady(isServiceWorkerControlling());
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'No se pudo activar push.');
            setSubscribed(false);
        } finally {
            setLoading(false);
        }
    }, [push?.vapidPublicKey, supported]);

    const disable = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const registration = await waitForServiceWorker();
            await unsubscribeFromPush(registration);
            setSubscribed(false);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'No se pudo desactivar push.');
        } finally {
            setLoading(false);
        }
    }, []);

    return {
        supported,
        permission,
        subscribed,
        swReady,
        loading,
        error,
        enable,
        disable,
    };
}
