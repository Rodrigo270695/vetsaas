import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toastManager } from '@/lib/toast';

type LocationGate = {
    needs_sede: boolean;
    needs_sede_geo: boolean;
    needs_gps: boolean;
    gps_captured: boolean;
    can_edit_sedes: boolean;
    sedes_url: string;
};

const TOAST_ID = 'clinic-sede-location-gate-v1';

function dismissStorageKey(gate: LocationGate): string {
    return `vetsaas:sede-location-dismissed:v1:${gate.needs_sede ? 'sede' : 'geo'}`;
}

function fingerprint(gate: LocationGate): string {
    return [gate.needs_sede ? '1' : '0', gate.needs_sede_geo ? '1' : '0', gate.sedes_url].join('|');
}

/**
 * Toast flotante cuando falta sede o geo (no empuja el layout en móvil).
 */
export function useClinicSedeLocationToast(): void {
    const page = usePage<{ clinic_location_gate?: LocationGate | null }>();
    const gate = page.props.clinic_location_gate ?? null;
    const shownFingerprintRef = useRef<string | null>(null);

    useEffect(() => {
        if (!gate || (!gate.needs_sede && !gate.needs_sede_geo)) {
            toastManager.close(TOAST_ID);
            shownFingerprintRef.current = null;

            return;
        }

        const fp = fingerprint(gate);
        const storageKey = dismissStorageKey(gate);

        try {
            if (localStorage.getItem(storageKey) === fp) {
                return;
            }
        } catch {
            // ignore
        }

        if (shownFingerprintRef.current === fp) {
            return;
        }

        shownFingerprintRef.current = fp;

        const title = gate.needs_sede
            ? 'Configura tu primera sede'
            : 'Completa la ubicación de tu sede';
        const description = gate.needs_sede
            ? 'Puedes explorar VetSaaS ahora. Cuando puedas, crea una sede con departamento, provincia y distrito.'
            : 'Tus sedes activas deberían tener departamento, provincia y distrito.';

        toastManager.warning({
            id: TOAST_ID,
            title,
            description,
            duration: Infinity,
            action: gate.can_edit_sedes
                ? {
                      label: gate.needs_sede ? 'Crear sede' : 'Completar ubicación',
                      onClick: () => {
                          router.visit(gate.sedes_url);
                      },
                  }
                : undefined,
            onDismiss: () => {
                try {
                    localStorage.setItem(storageKey, fp);
                } catch {
                    // ignore
                }
            },
        });
    }, [gate]);
}
