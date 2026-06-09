import { createInertiaApp } from '@inertiajs/react';
import PwaInstallBanner from '@/components/pwa-install-banner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import '@/lib/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            // Páginas sin sidebar: errores HTTP, tenant público y welcome.
            case name.startsWith('errors/'):
            case name.startsWith('tenant/errors/'):
            case name === 'tenant/welcome':
                return AuthLayout;
            // Todo lo demás (dashboard, módulos operativos, plataforma)
            // comparte el mismo AppLayout. Los items del sidebar se
            // filtran por permisos del usuario, así que la misma UI
            // muestra cosas distintas a superadmin vs admin de clínica
            // vs veterinario vs recepcionista.
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <PwaInstallBanner />
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* sin SW la app sigue funcionando */
        });
    });
}
