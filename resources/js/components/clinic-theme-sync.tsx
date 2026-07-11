import { router } from '@inertiajs/react';
import { useLayoutEffect } from 'react';
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

function readRouterPageProps(): Record<string, unknown> | undefined {
    return (router as unknown as { page?: InertiaPage }).page?.props;
}

/**
 * Mantiene las variables CSS de marca al día en navegaciones Inertia.
 * La carga inicial también se cubre en servidor (Blade) y en app.tsx.
 */
export function ClinicThemeSync() {
    useLayoutEffect(() => {
        syncBranding(readRouterPageProps());

        const removeSuccess = router.on('success', (event) => {
            const detail = (event as InertiaEvent).detail;
            syncBranding(detail?.page?.props);
        });

        return () => {
            removeSuccess();
        };
    }, []);

    return null;
}
