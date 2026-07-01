import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { applyClinicBrandTheme, clearClinicBrandTheme } from '@/lib/clinic-theme';
import type { ClinicBranding } from '@/types/clinic-branding';

type InertiaPage = { props?: Record<string, unknown> };
type InertiaEvent = CustomEvent<{ page?: InertiaPage }>;

function syncBranding(props: Record<string, unknown> | undefined): void {
    const branding = props?.clinic_branding as ClinicBranding | null | undefined;

    if (!branding) {
        clearClinicBrandTheme();

        return;
    }

    applyClinicBrandTheme(branding.color_primario, branding.color_secundario);
}

/**
 * Aplica el color primario/secundario de la clínica a las variables CSS de marca.
 *
 * Vive fuera del árbol de Inertia (`withApp`), así que no puede usar `usePage()`;
 * lee `clinic_branding` desde `router.page` y en cada navegación `success`.
 */
export function ClinicThemeSync() {
    useEffect(() => {
        const handle = (props: Record<string, unknown> | undefined): void => {
            syncBranding(props);
        };

        const initialPage = (router as unknown as { page?: InertiaPage }).page;
        handle(initialPage?.props);

        const removeSuccess = router.on('success', (event) => {
            const detail = (event as InertiaEvent).detail;
            handle(detail?.page?.props);
        });

        return () => {
            removeSuccess();
        };
    }, []);

    return null;
}
