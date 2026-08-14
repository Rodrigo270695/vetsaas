import { router, usePage } from '@inertiajs/react';
import { Navigation } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type LocationGate = {
    needs_gps: boolean;
    has_gps_consent?: boolean;
    gps_captured?: boolean;
};

type TenantProp = {
    slug?: string;
    is_demo?: boolean;
} | null;

/**
 * Botón manual (pago y free): captura GPS en este dispositivo y lo guarda
 * en el tenant. Reemplaza el cron — el servidor no puede leer el GPS solo.
 */
export function TenantGeoCaptureButton({
    className,
}: {
    className?: string;
}) {
    const page = usePage<{
        clinic_location_gate?: LocationGate | null;
        tenant?: TenantProp;
    }>();
    const gate = page.props.clinic_location_gate;
    const tenant = page.props.tenant;
    const [busy, setBusy] = useState(false);
    const [msg, setMsg] = useState<string | null>(null);

    if (!tenant || tenant.is_demo) {
        return null;
    }
    if (!gate) {
        return null;
    }

    const capture = () => {
        if (!navigator.geolocation) {
            setMsg('Este navegador no soporta GPS.');
            return;
        }
        setBusy(true);
        setMsg(null);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const action = gate.has_gps_consent ? 'refresh' : 'accept';
                router.post(
                    '/tenant/geo',
                    {
                        action,
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                    },
                    {
                        preserveScroll: true,
                        onFinish: () => {
                            setBusy(false);
                            setMsg('Ubicación guardada.');
                        },
                        onError: () => {
                            setBusy(false);
                            setMsg('No se pudo guardar. Intenta de nuevo.');
                        },
                    },
                );
            },
            (err) => {
                setBusy(false);
                if (err.code === err.PERMISSION_DENIED) {
                    setMsg('Activa Ubicación en el candado del navegador.');
                } else {
                    setMsg('No se obtuvo GPS. Revisa el dispositivo.');
                }
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
        );
    };

    return (
        <div className={cn('flex flex-col items-end gap-1', className)}>
            <Button
                type="button"
                size="sm"
                variant={gate.needs_gps ? 'default' : 'outline'}
                disabled={busy}
                onClick={capture}
                className="gap-1.5 shadow-sm"
            >
                <Navigation className="size-3.5" aria-hidden />
                {busy
                    ? 'Capturando…'
                    : gate.has_gps_consent
                      ? 'Actualizar ubicación'
                      : 'Capturar ubicación'}
            </Button>
            {msg ? (
                <p className="max-w-[220px] text-right text-[11px] text-muted-foreground">
                    {msg}
                </p>
            ) : null}
        </div>
    );
}
