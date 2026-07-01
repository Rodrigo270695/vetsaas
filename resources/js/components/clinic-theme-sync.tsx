import { useEffect } from 'react';
import { useClinicBranding } from '@/hooks/use-clinic-branding';
import { applyClinicBrandTheme, clearClinicBrandTheme } from '@/lib/clinic-theme';

/**
 * Aplica el color primario/secundario de la clínica a las variables CSS de marca.
 * Sidebar, dashboard, suscripción y botones «Nuevo» usan la escala `brand-*`.
 */
export function ClinicThemeSync() {
    const branding = useClinicBranding();

    useEffect(() => {
        if (!branding) {
            clearClinicBrandTheme();

            return;
        }

        applyClinicBrandTheme(branding.color_primario, branding.color_secundario);

        return () => {
            clearClinicBrandTheme();
        };
    }, [
        branding?.color_primario,
        branding?.color_secundario,
        branding?.updated_at,
    ]);

    return null;
}
