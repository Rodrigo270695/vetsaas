import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

type LocationGate = {
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
 * Si el superadmin solicitó refresco y esta clínica ya dio consentimiento,
 * captura GPS en silencio (usuario real de la clínica, no soporte).
 */
export function TenantGeoRefreshCapture(): null {
    const page = usePage<{
        clinic_location_gate?: LocationGate | null;
        tenant_impersonation?: unknown;
    }>();
    const gate = page.props.clinic_location_gate;
    const impersonating = Boolean(page.props.tenant_impersonation);
    const inFlight = useRef(false);

    useEffect(() => {
        if (impersonating) {
            return;
        }
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
                        // Reintentará en la próxima visita.
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
                    maximumAge: 60_000,
                },
            );
        }, 1200);

        return () => window.clearTimeout(timer);
    }, [
        gate?.has_gps_consent,
        gate?.gps_refresh_due,
        impersonating,
        page.url,
    ]);

    return null;
}
