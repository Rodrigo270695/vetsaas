import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

type LocationGate = {
    needs_gps: boolean;
    has_gps_consent?: boolean;
    gps_refresh_due?: boolean;
};

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

/**
 * Si el tenant ya dio consentimiento y el cron marcó refresco (o no hay XY),
 * captura GPS en silencio y guarda el último punto para el mapa.
 */
export function TenantGeoAutoCapture(): null {
    const page = usePage<{ clinic_location_gate?: LocationGate | null }>();
    const gate = page.props.clinic_location_gate;
    const inFlight = useRef(false);

    useEffect(() => {
        if (!gate?.has_gps_consent || !gate.gps_refresh_due) {
            return;
        }
        if (typeof window === 'undefined' || !navigator.geolocation) {
            return;
        }
        if (inFlight.current) {
            return;
        }

        inFlight.current = true;
        const timer = window.setTimeout(() => {
            navigator.geolocation.getCurrentPosition(
                async (pos) => {
                    try {
                        await fetch('/tenant/geo', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: csrfHeaders(),
                            body: JSON.stringify({
                                action: 'refresh',
                                lat: pos.coords.latitude,
                                lng: pos.coords.longitude,
                            }),
                        });
                    } catch {
                        // Silencioso: reintentará en la próxima visita / cron.
                    } finally {
                        inFlight.current = false;
                    }
                },
                () => {
                    inFlight.current = false;
                },
                {
                    enableHighAccuracy: false,
                    timeout: 15000,
                    maximumAge: 5 * 60_000,
                },
            );
        }, 1500);

        return () => {
            window.clearTimeout(timer);
        };
    }, [gate?.has_gps_consent, gate?.gps_refresh_due, page.url]);

    return null;
}
