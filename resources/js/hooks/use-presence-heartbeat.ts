import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

type AuthUser = {
    id: string;
} | null;

type PageProps = {
    auth?: {
        user?: AuthUser;
    };
};

function csrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta instanceof HTMLMetaElement && meta.content) {
        return meta.content;
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (match?.[1]) {
        return decodeURIComponent(match[1]);
    }

    return '';
}

/**
 * Marca presencia mientras la pestaña está abierta (cada ~45s).
 * Alimenta el radar de Operaciones (ventana abierta vs sesión idle).
 */
export function usePresenceHeartbeat(): void {
    const { auth } = usePage<PageProps>().props;
    const userId = auth?.user?.id ?? null;

    useEffect(() => {
        if (!userId) {
            return;
        }

        let cancelled = false;

        const ping = () => {
            if (cancelled || document.visibilityState === 'hidden') {
                return;
            }

            const token = csrfToken();
            void fetch('/presence/heartbeat', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token
                        ? {
                              'X-CSRF-TOKEN': token,
                              'X-XSRF-TOKEN': token,
                          }
                        : {}),
                },
            }).catch(() => {
                /* silencioso: no molestar al operador si falla un ping */
            });
        };

        ping();
        const intervalId = window.setInterval(ping, 45_000);

        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                ping();
            }
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            cancelled = true;
            window.clearInterval(intervalId);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [userId]);
}
