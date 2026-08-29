import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

type TenantProp = {
    slug?: string;
    is_demo?: boolean;
} | null;

const SESSION_KEY = 'vetsaas.demo_access_geo_done';
const LOG_ID_KEY = 'vetsaas.demo_access_log_id';

function csrfHeaders(): Record<string, string> {
    const meta =
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    const xsrf = match?.[1] ? decodeURIComponent(match[1]) : '';

    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(meta ? { 'X-CSRF-TOKEN': meta } : {}),
        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
    };
}

async function postDemoGeo(
    lat: number | null,
    lng: number | null,
): Promise<void> {
    const res = await fetch('/demo/access-geo', {
        method: 'POST',
        credentials: 'same-origin',
        headers: csrfHeaders(),
        body: JSON.stringify({ lat, lng }),
    });
    const data = (await res.json().catch(() => null)) as { id?: string } | null;
    if (data?.id) {
        sessionStorage.setItem(LOG_ID_KEY, data.id);
    }
}

/**
 * En el tenant demo: una vez por sesión registra de dónde entró el visitante
 * (GPS si el browser lo permite; si no, solo IP en servidor).
 */
export function DemoAccessGeoCapture(): null {
    const page = usePage<{ tenant?: TenantProp }>();
    const tenant = page.props.tenant;
    const started = useRef(false);

    useEffect(() => {
        if (!tenant?.is_demo) {
            return;
        }
        if (typeof window === 'undefined') {
            return;
        }
        if (sessionStorage.getItem(SESSION_KEY) === '1') {
            return;
        }
        if (started.current) {
            return;
        }
        started.current = true;

        const timer = window.setTimeout(() => {
            if (!navigator.geolocation) {
                void postDemoGeo(null, null).finally(() => {
                    sessionStorage.setItem(SESSION_KEY, '1');
                });
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    void postDemoGeo(
                        pos.coords.latitude,
                        pos.coords.longitude,
                    ).finally(() => {
                        sessionStorage.setItem(SESSION_KEY, '1');
                    });
                },
                () => {
                    void postDemoGeo(null, null).finally(() => {
                        sessionStorage.setItem(SESSION_KEY, '1');
                    });
                },
                {
                    enableHighAccuracy: false,
                    timeout: 12000,
                    maximumAge: 60_000,
                },
            );
        }, 1200);

        return () => window.clearTimeout(timer);
    }, [tenant?.is_demo]);

    return null;
}
