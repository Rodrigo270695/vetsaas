function readXsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m?.[1] ? decodeURIComponent(m[1]) : '';
}

type PushSubscriptionJson = {
    endpoint: string;
    keys: {
        auth: string;
        p256dh: string;
    };
    contentEncoding?: string;
};

const VAPID_PUBLIC_KEY_BYTES = 65;

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

function resolveApplicationServerKey(vapidPublicKey: string): ArrayBuffer {
    const keyBytes = urlBase64ToUint8Array(vapidPublicKey.trim());

    if (keyBytes.byteLength !== VAPID_PUBLIC_KEY_BYTES) {
        throw new Error(
            `Clave VAPID inválida: se esperaban ${VAPID_PUBLIC_KEY_BYTES} bytes y llegaron ${keyBytes.byteLength}.`,
        );
    }

    return keyBytes.buffer.slice(
        keyBytes.byteOffset,
        keyBytes.byteOffset + keyBytes.byteLength,
    );
}

function resolveContentEncoding(): string {
    if ('PushManager' in window) {
        const supported = (
            window.PushManager as typeof PushManager & {
                supportedContentEncodings?: string[];
            }
        ).supportedContentEncodings;

        if (supported?.includes('aes128gcm')) {
            return 'aes128gcm';
        }
    }

    return 'aesgcm';
}

async function syncSubscription(
    subscription: PushSubscription,
    method: 'POST' | 'DELETE',
): Promise<void> {
    const body = subscription.toJSON() as PushSubscriptionJson;
    const payload =
        method === 'DELETE'
            ? { endpoint: body.endpoint }
            : {
                  endpoint: body.endpoint,
                  keys: body.keys,
                  contentEncoding: resolveContentEncoding(),
              };

    const response = await fetch('/push/subscribe', {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': readXsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    if (!response.ok && response.status !== 204) {
        let message = `Error al guardar la suscripción (${response.status})`;

        try {
            const data = (await response.json()) as { message?: string };
            if (data.message) {
                message = data.message;
            }
        } catch {
            // ignore
        }

        throw new Error(message);
    }
}

async function unsubscribeLocally(
    registration: ServiceWorkerRegistration,
): Promise<void> {
    const existing = await registration.pushManager.getSubscription();

    if (existing) {
        await existing.unsubscribe();
    }
}

export async function subscribeToPush(
    registration: ServiceWorkerRegistration,
    vapidPublicKey: string,
): Promise<PushSubscription> {
    if (!registration.active) {
        throw new Error('No hay service worker activo. Recarga la página e inténtalo de nuevo.');
    }

    await unsubscribeLocally(registration);

    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: resolveApplicationServerKey(vapidPublicKey),
    });

    await syncSubscription(subscription, 'POST');

    return subscription;
}

export async function unsubscribeFromPush(
    registration: ServiceWorkerRegistration,
): Promise<void> {
    const subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
        return;
    }

    try {
        await syncSubscription(subscription, 'DELETE');
    } catch {
        // ignore backend errors on disable
    }

    await subscription.unsubscribe();
}

export function isPushSupported(): boolean {
    return (
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

export async function waitForServiceWorker(
    timeoutMs = 12_000,
): Promise<ServiceWorkerRegistration> {
    if (!('serviceWorker' in navigator)) {
        throw new Error('Este navegador no soporta service workers.');
    }

    const existing = await navigator.serviceWorker.getRegistration();
    if (existing?.active) {
        return existing;
    }

    const registration = await navigator.serviceWorker.ready;

    if (registration.active) {
        return registration;
    }

    await new Promise<void>((resolve, reject) => {
        const timer = window.setTimeout(() => {
            reject(new Error('El service worker tarda en activarse. Recarga (Ctrl+F5).'));
        }, timeoutMs);

        navigator.serviceWorker.addEventListener(
            'controllerchange',
            () => {
                window.clearTimeout(timer);
                resolve();
            },
            { once: true },
        );
    });

    return navigator.serviceWorker.ready;
}

export function isServiceWorkerControlling(): boolean {
    return Boolean(navigator.serviceWorker?.controller);
}
