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
    has_gps_consent?: boolean;
    gps_refresh_due?: boolean;
    can_edit_sedes: boolean;
    sedes_url: string;
};

/** Módulos más visitados: ahí pedimos el consentimiento GPS. */
const HOT_PATH_PREFIXES = [
    '/dashboard',
    '/clinica/pacientes',
    '/clinica/propietarios',
    '/clinica/citas',
    '/clinica/historias-clinicas',
    '/clinica/vacunaciones',
    '/servicios/grooming',
    '/caja/ventas',
    '/inventario/productos',
    '/caja/sesiones',
    '/facturacion',
    '/comunicaciones',
    '/configuracion/tarifas',
    '/clinica/recetas',
];

function isHotPath(pathname: string): boolean {
    const path = pathname.split('?')[0] || '/';
    return HOT_PATH_PREFIXES.some(
        (prefix) => path === prefix || path.startsWith(`${prefix}/`),
    );
}

/**
 * Modal obligatorio: no se cierra con clic afuera, Escape ni la X.
 * Solo “Permitir ubicación” (o rechazo explícito permanente).
 */
export function TenantGpsConsentModal() {
    const page = usePage<{ clinic_location_gate?: LocationGate | null }>();
    const gate = page.props.clinic_location_gate;
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!gate?.needs_gps) {
            setOpen(false);
            return;
        }
        if (typeof window === 'undefined') {
            return;
        }

        sessionStorage.removeItem('vetsaas.gps_prompt_seen');
        sessionStorage.removeItem('vetsaas.gps_soft_dismiss_until');

        const pathname = new URL(page.url, window.location.origin).pathname;
        if (!isHotPath(pathname)) {
            return;
        }

        const timer = window.setTimeout(() => setOpen(true), 400);
        return () => window.clearTimeout(timer);
    }, [gate?.needs_gps, page.url]);

    const deny = () => {
        setBusy(true);
        setError(null);
        router.post(
            '/tenant/geo',
            { action: 'deny' },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setOpen(false);
                },
            },
        );
    };

    const accept = () => {
        if (!navigator.geolocation) {
            setError('Este navegador no soporta geolocalización.');
            return;
        }
        setBusy(true);
        setError(null);
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
                            setOpen(false);
                        },
                        onError: () => {
                            setBusy(false);
                            setError('No se pudo guardar la ubicación. Intenta de nuevo.');
                        },
                    },
                );
            },
            (err) => {
                setBusy(false);
                if (err.code === err.PERMISSION_DENIED) {
                    setError(
                        'Debes permitir la ubicación en el navegador (icono del candado → Ubicación → Permitir) y luego pulsar de nuevo.',
                    );
                } else {
                    setError('No se pudo obtener la ubicación. Revisa el GPS e inténtalo otra vez.');
                }
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
        );
    };

    if (!gate?.needs_gps) {
        return null;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                // Bloquea cierre accidental (overlay / Escape).
                if (!next) {
                    return;
                }
                setOpen(true);
            }}
        >
            <DialogContent
                hideCloseButton
                className="sm:max-w-md"
                onPointerDownOutside={(e) => e.preventDefault()}
                onInteractOutside={(e) => e.preventDefault()}
                onEscapeKeyDown={(e) => e.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Navigation
                            className="size-5 text-brand-600"
                            aria-hidden
                        />
                        Ubicación de tu clínica
                    </DialogTitle>
                    <DialogDescription>
                        Para el mapa de cobertura de VetSaaS necesitamos tu
                        ubicación aproximada. Debes aceptar para continuar
                        usando el sistema con normalidad en este dispositivo.
                    </DialogDescription>
                </DialogHeader>
                <div className="rounded-lg border border-border/60 bg-muted/30 p-3 text-xs text-muted-foreground">
                    <p className="flex items-start gap-2">
                        <MapPin
                            className="mt-0.5 size-3.5 shrink-0"
                            aria-hidden
                        />
                        Solo se guarda latitud/longitud de la clínica. Luego se
                        actualizará automáticamente dos veces al día cuando
                        alguien navegue en la clínica.
                    </p>
                </div>
                {error ? (
                    <p className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                        {error}
                    </p>
                ) : null}
                <DialogFooter className="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={busy}
                        onClick={deny}
                        className="text-muted-foreground"
                    >
                        No volver a pedir
                    </Button>
                    <Button type="button" disabled={busy} onClick={accept}>
                        {busy ? 'Obteniendo…' : 'Permitir ubicación'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
