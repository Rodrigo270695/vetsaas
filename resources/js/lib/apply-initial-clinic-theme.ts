import { applyClinicBrandTheme } from '@/lib/clinic-theme';
import type { ClinicBranding } from '@/types/clinic-branding';

/**
 * Aplica el tema de la clínica leyendo `data-page` de Inertia antes del
 * primer render de React (evita flash de colores por defecto).
 */
export function applyInitialClinicThemeFromDocument(): void {
    if (typeof document === 'undefined') {
        return;
    }

    const appRoot = document.getElementById('app');
    const rawPage = appRoot?.getAttribute('data-page');

    if (!rawPage) {
        return;
    }

    try {
        const page = JSON.parse(rawPage) as { props?: Record<string, unknown> };
        const branding = page.props?.clinic_branding as ClinicBranding | null | undefined;

        if (!branding) {
            return;
        }

        applyClinicBrandTheme(branding.color_primario, branding.color_secundario);
    } catch {
        // JSON inválido: Inertia aplicará el tema en la primera navegación.
    }
}
