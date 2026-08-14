import { router, usePage } from '@inertiajs/react';
import { MapPin, Navigation } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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

/** Módulos más visitados (presencia): ahí pedimos GPS. */
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

const SOFT_DISMISS_KEY = 'vetsaas.gps_soft_dismiss_until';
const SOFT_DISMISS_MS = 3 * 60 * 1000; // 3 min tras “Ahora no”

function isHotPath(pathname: string): boolean {
    const path = pathname.split('?')[0] || '/';
    return HOT_PATH_PREFIXES.some(
        (prefix) => path === prefix || path.startsWith(`${prefix}/`),
    );
}

function softDismissActive(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }
    const raw = sessionStorage.getItem(SOFT_DISMISS_KEY);
    if (!raw) {
        return false;
    }
    const until = Number(raw);
    if (!Number.isFinite(until) || Date.now() >= until) {
        sessionStorage.removeItem(SOFT_DISMISS_KEY);
        return false;
    }
    return true;
}

/**
 * Pide permiso de ubicación en módulos de alto tráfico y lo guarda
 * en el tenant para el mapa de Reportes de plataforma.
 */
export function TenantGpsConsentModal() {
    const page = usePage<{ clinic_location_gate?: LocationGate | null }>();
    const gate = page.props.clinic_location_gate;
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const lastPromptPath = useRef<string | null>(null);

    useEffect(() => {
        if (!gate?.needs_gps) {
            setOpen(false);
            return;
        }
        if (typeof window === 'undefined') {
            return;
        }

        // Limpia el flag viejo que bloqueaba el prompt toda la sesión.
        sessionStorage.removeItem('vetsaas.gps_prompt_seen');

        const pathname = new URL(
            page.url,
            window.location.origin,
        ).pathname;

        if (!isHotPath(pathname)) {
            return;
        }
        if (softDismissActive()) {
            return;
        }

        const timer = window.setTimeout(() => {
            lastPromptPath.current = pathname;
            setOpen(true);
        }, 600);

        return () => window.clearTimeout(timer);
    }, [gate?.needs_gps, page.url]);

    const softDismiss = () => {
        sessionStorage.setItem(
            SOFT_DISMISS_KEY,
            String(Date.now() + SOFT_DISMISS_MS),
        );
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
                    setOpen(false);
                    sessionStorage.removeItem(SOFT_DISMISS_KEY);
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
                            setOpen(false);
                            sessionStorage.removeItem(SOFT_DISMISS_KEY);
                        },
                    },
                );
            },
            () => {
                // Permiso del navegador denegado → no marcar deny permanente;
                // solo soft dismiss para no martillar al usuario.
                setBusy(false);
                softDismiss();
            },
            { enableHighAccuracy: false, timeout: 12000, maximumAge: 60_000 },
        );
    };

    if (!gate?.needs_gps) {
        return null;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(v) => {
                if (!v) {
                    softDismiss();
                    return;
                }
                setOpen(true);
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Navigation
                            className="size-5 text-brand-600"
                            aria-hidden
                        />
                        Ubicación de tu clínica
                    </DialogTitle>
                    <DialogDescription>
                        Para el mapa de cobertura de VetSaaS, ¿permites compartir
                        la ubicación aproximada de este dispositivo? Puedes
                        rechazar y seguir trabajando con normalidad.
                    </DialogDescription>
                </DialogHeader>
                <div className="rounded-lg border border-border/60 bg-muted/30 p-3 text-xs text-muted-foreground">
                    <p className="flex items-start gap-2">
                        <MapPin
                            className="mt-0.5 size-3.5 shrink-0"
                            aria-hidden
                        />
                        Solo se guarda latitud/longitud de la clínica. No
                        rastreamos movimientos en tiempo real. Si eliges “Ahora
                        no”, te lo volveremos a pedir en unos minutos al seguir
                        navegando.
                    </p>
                </div>
                <DialogFooter className="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy}
                        onClick={softDismiss}
                    >
                        Ahora no
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={busy}
                        onClick={deny}
                    >
                        No volver a pedir
                    </Button>
                    <Button type="button" disabled={busy} onClick={accept}>
                        Permitir ubicación
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
