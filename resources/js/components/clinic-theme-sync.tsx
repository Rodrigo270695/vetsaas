import { router } from '@inertiajs/react';
import { useLayoutEffect } from 'react';
import { applyClinicBrandTheme, clearClinicBrandTheme } from '@/lib/clinic-theme';
import type { ClinicBranding } from '@/types/clinic-branding';

type InertiaPage = { props?: Record<string, unknown> };
type InertiaEvent = CustomEvent<{ page?: InertiaPage }>;

function pageComponent(props: Record<string, unknown> | undefined, fallback?: string): string {
    if (typeof fallback === 'string' && fallback !== '') {
        return fallback;
    }

    const fromRouter = (router as unknown as { page?: { component?: string } }).page?.component;

    return typeof fromRouter === 'string' ? fromRouter : '';
}

function isPortalPage(component: string): boolean {
    return component.startsWith('portal/');
}

function syncBranding(props: Record<string, unknown> | undefined, component?: string): void {
    if (isPortalPage(pageComponent(props, component))) {
        clearClinicBrandTheme();

        return;
    }

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
        syncBranding(
            readRouterPageProps(),
            (router as unknown as { page?: { component?: string } }).page?.component,
        );

        const removeSuccess = router.on('success', (event) => {
            const detail = (event as InertiaEvent).detail;
            const page = detail?.page as { props?: Record<string, unknown>; component?: string } | undefined;
            syncBranding(page?.props, page?.component);
        });

        return () => {
            removeSuccess();
        };
    }, []);

    return null;
}
