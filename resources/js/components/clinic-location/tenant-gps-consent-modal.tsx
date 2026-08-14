import { router, usePage } from '@inertiajs/react';
import { Navigation } from 'lucide-react';
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
    has_gps_consent?: boolean;
    gps_refresh_due?: boolean;
    can_edit_sedes: boolean;
    sedes_url: string;
};

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

async function geolocationPermission(): Promise<
    'granted' | 'prompt' | 'denied' | 'unknown'
> {
    try {
        if (!navigator.permissions?.query) {
            return 'unknown';
        }
        const status = await navigator.permissions.query({
            name: 'geolocation' as PermissionName,
        });
        if (status.state === 'granted' || status.state === 'denied' || status.state === 'prompt') {
            return status.state;
        }
        return 'unknown';
    } catch {
        return 'unknown';
    }
}

/**
 * Consentimiento obligatorio para usuarios reales de la clínica.
 * No se muestra en modo soporte/impersonación (evita guardar el GPS del staff).
 */
export function TenantGpsConsentModal() {
    const page = usePage<{
        clinic_location_gate?: LocationGate | null;
        tenant_impersonation?: { tenant_label?: string } | null;
    }>();
    const gate = page.props.clinic_location_gate;
    const impersonating = Boolean(page.props.tenant_impersonation);
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [browserGranted, setBrowserGranted] = useState(false);
    const autoTried = useRef(false);

    useEffect(() => {
        if (impersonating || !gate?.needs_gps) {
            setOpen(false);
            autoTried.current = false;
            return;
        }
        if (typeof window === 'undefined') {
            return;
        }

        const pathname = new URL(page.url, window.location.origin).pathname;
        if (!isHotPath(pathname)) {
            return;
        }

        let cancelled = false;
        void geolocationPermission().then((state) => {
            if (cancelled) {
                return;
            }
            setBrowserGranted(state === 'granted');
        });

        const timer = window.setTimeout(() => setOpen(true), 400);
        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [gate?.needs_gps, page.url, impersonating]);

    const savePosition = (lat: number, lng: number) => {
        router.post(
            '/tenant/geo',
            { action: 'accept', lat, lng },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setOpen(false);
                },
                onError: () => {
                    setBusy(false);
                    setError('No se pudo guardar. Intenta de nuevo.');
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
                savePosition(pos.coords.latitude, pos.coords.longitude);
            },
            (err) => {
                setBusy(false);
                if (err.code === err.PERMISSION_DENIED) {
                    setError(
                        'Activa Ubicación en el candado del navegador y pulsa Permitir de nuevo.',
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

    // Si ya activó Ubicación en el candado, capturamos y guardamos solos.
    useEffect(() => {
        if (!open || !gate?.needs_gps || !browserGranted || busy) {
            return;
        }
        if (autoTried.current) {
            return;
        }
        autoTried.current = true;
        accept();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- solo al detectar permiso concedido
    }, [open, gate?.needs_gps, browserGranted]);

    if (impersonating || !gate?.needs_gps) {
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
                        Necesitamos la ubicación aproximada de tu clínica para el
                        mapa de cobertura. Pulsa Permitir y confirma en el aviso
                        del navegador.
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
                        {busy
                            ? 'Guardando ubicación…'
                            : browserGranted
                              ? 'Reintentar guardar'
                              : 'Permitir ubicación'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
