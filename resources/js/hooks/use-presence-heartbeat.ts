import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

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
 * Marca presencia mientras la pestaña está abierta (cada ~45s) y al cambiar de vista.
 * Envía path + componente Inertia para el radar de módulos.
 */
export function usePresenceHeartbeat(): void {
    const page = usePage<PageProps>();
    const userId = page.props.auth?.user?.id ?? null;
    const lastSentPathRef = useRef<string | null>(null);

    useEffect(() => {
        if (!userId) {
            return;
        }

        let cancelled = false;

        const ping = () => {
            if (cancelled || document.visibilityState === 'hidden') {
                return;
            }

            const path = `${window.location.pathname}${window.location.search}`;
            const component = typeof page.component === 'string' ? page.component : null;
            lastSentPathRef.current = path;

            const token = csrfToken();
            void fetch('/presence/heartbeat', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token
                        ? {
                              'X-CSRF-TOKEN': token,
                              'X-XSRF-TOKEN': token,
                          }
                        : {}),
                },
                body: JSON.stringify({
                    path,
                    component,
                }),
            }).catch(() => {
                /* silencioso */
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
    }, [userId, page.url, page.component]);
}
