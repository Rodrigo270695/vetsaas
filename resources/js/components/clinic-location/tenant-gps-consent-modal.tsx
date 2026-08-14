import { router, usePage } from '@inertiajs/react';
import { Navigation } from 'lucide-react';
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
 * Consentimiento de la app (obligatorio). Al aceptar, el navegador muestra
 * su propio diálogo nativo de Ubicación (no aparece en el candado hasta
 * que se pide al menos una vez con getCurrentPosition).
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

    const accept = () => {
        if (!navigator.geolocation) {
            setError('Este navegador no soporta geolocalización.');
            return;
        }
        setBusy(true);
        setError(null);
        // Aquí el browser muestra su prompt nativo de Ubicación.
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
                            setError(
                                'No se pudo guardar. Intenta de nuevo.',
                            );
                        },
                    },
                );
            },
            (err) => {
                setBusy(false);
                if (err.code === err.PERMISSION_DENIED) {
                    setError(
                        'Activa Ubicación para este sitio en el candado del navegador y vuelve a pulsar Permitir.',
                    );
                } else {
                    setError(
                        'No se pudo obtener la ubicación. Revisa el GPS e inténtalo otra vez.',
                    );
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
                        Necesitamos la ubicación aproximada de tu clínica para
                        mostrar tu cobertura en el mapa. Pulsa Permitir y
                        confirma en el aviso del navegador.
                    </DialogDescription>
                </DialogHeader>
                {error ? (
                    <p className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                        {error}
                    </p>
                ) : null}
                <DialogFooter>
                    <Button
                        type="button"
                        className="w-full sm:w-auto"
                        disabled={busy}
                        onClick={accept}
                    >
                        {busy ? 'Esperando al navegador…' : 'Permitir ubicación'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
