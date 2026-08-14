import { router, usePage } from '@inertiajs/react';
import { Navigation } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type LocationGate = {
    needs_gps?: boolean;
    has_gps_consent?: boolean;
    gps_captured?: boolean;
};

type TenantProp = {
    slug?: string;
    is_demo?: boolean;
} | null;

/**
 * Solo en subdominio de clínica (pago o free). No aparece en el panel central
 * ni en la demo (la demo se registra sola al entrar).
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

    // Panel central o demo → sin botón.
    if (!tenant?.slug || tenant.is_demo) {
        return null;
    }

    const hasConsent = gate?.has_gps_consent === true;

    const capture = () => {
        if (!navigator.geolocation) {
            setMsg('Este navegador no soporta GPS.');
            return;
        }
        setBusy(true);
        setMsg(null);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                router.post(
                    '/tenant/geo',
                    {
                        action: hasConsent ? 'refresh' : 'accept',
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
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        size="sm"
                        variant={gate?.needs_gps ? 'default' : 'outline'}
                        disabled={busy}
                        onClick={capture}
                        className="h-9 gap-1.5 px-2.5 shadow-sm"
                    >
                        <Navigation className="size-4" aria-hidden />
                        <span className="hidden text-xs font-medium sm:inline">
                            {busy
                                ? 'Capturando…'
                                : hasConsent
                                  ? 'Actualizar ubicación'
                                  : 'Capturar ubicación'}
                        </span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs">
                    Guarda el GPS de esta clínica (pago o free) para el mapa
                    de cobertura del panel.
                </TooltipContent>
            </Tooltip>
            {msg ? (
                <p className="max-w-[220px] text-right text-[11px] text-muted-foreground">
                    {msg}
                </p>
            ) : null}
        </div>
    );
}
