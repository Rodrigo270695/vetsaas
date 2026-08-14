import { router, usePage } from '@inertiajs/react';
import { MapPin, Navigation } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type LocationGate = {
    needs_sede: boolean;
    needs_sede_geo: boolean;
    needs_gps: boolean;
    gps_captured: boolean;
    can_edit_sedes: boolean;
    sedes_url: string;
};

const SESSION_KEY = 'vetsaas.gps_prompt_seen';

/**
 * Pide permiso de ubicación del navegador (una vez por sesión) y lo
 * guarda en el tenant para el mapa de calor del superadmin.
 */
export function TenantGpsConsentModal() {
    const page = usePage<{ clinic_location_gate?: LocationGate | null }>();
    const gate = page.props.clinic_location_gate;
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!gate?.needs_gps || !gate.can_edit_sedes) {
            return;
        }
        if (typeof window === 'undefined') {
            return;
        }
        if (sessionStorage.getItem(SESSION_KEY) === '1') {
            return;
        }
        const timer = window.setTimeout(() => setOpen(true), 1200);
        return () => window.clearTimeout(timer);
    }, [gate?.needs_gps, gate?.can_edit_sedes]);

    const dismissSession = () => {
        sessionStorage.setItem(SESSION_KEY, '1');
        setOpen(false);
    };

    const deny = () => {
        setBusy(true);
        router.post(
            '/tenant/geo',
            { action: 'deny' },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    dismissSession();
                },
            },
        );
    };

    const accept = () => {
        if (!navigator.geolocation) {
            deny();
            return;
        }
        setBusy(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                router.post(
                    '/tenant/geo',
                    {
                        action: 'accept',
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                    },
                    {
                        preserveScroll: true,
                        onFinish: () => {
                            setBusy(false);
                            dismissSession();
                        },
                    },
                );
            },
            () => {
                router.post(
                    '/tenant/geo',
                    { action: 'deny' },
                    {
                        preserveScroll: true,
                        onFinish: () => {
                            setBusy(false);
                            dismissSession();
                        },
                    },
                );
            },
            { enableHighAccuracy: false, timeout: 12000, maximumAge: 60_000 },
        );
    };

    if (!gate?.needs_gps) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={(v) => (!v ? dismissSession() : setOpen(v))}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Navigation className="size-5 text-brand-600" aria-hidden />
                        Ubicación de tu clínica
                    </DialogTitle>
                    <DialogDescription>
                        Para mejorar el servicio y el mapa de cobertura de VetSaaS,
                        ¿permites compartir la ubicación aproximada de este dispositivo?
                        Puedes rechazar y seguir usando el sistema.
                    </DialogDescription>
                </DialogHeader>
                <div className="rounded-lg border border-border/60 bg-muted/30 p-3 text-xs text-muted-foreground">
                    <p className="flex items-start gap-2">
                        <MapPin className="mt-0.5 size-3.5 shrink-0" aria-hidden />
                        Solo se guarda latitud/longitud de la clínica (tenant). No
                        rastreamos movimientos en tiempo real.
                    </p>
                </div>
                <DialogFooter className="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy}
                        onClick={deny}
                    >
                        Ahora no
                    </Button>
                    <Button type="button" disabled={busy} onClick={accept}>
                        Permitir ubicación
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
