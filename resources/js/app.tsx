import { createInertiaApp, router } from '@inertiajs/react';
import { ClinicThemeSync } from '@/components/clinic-theme-sync';
import PwaInstallBanner from '@/components/pwa-install-banner';
import { OfflineSyncProvider } from '@/contexts/offline-sync-context';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { rememberInertiaPage } from '@/lib/offline/page-cache';
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
            case name.startsWith('errors/'):
            case name.startsWith('tenant/errors/'):
            case name === 'tenant/welcome':
                return AuthLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <OfflineSyncProvider>
                <TooltipProvider delayDuration={0}>
                    <ClinicThemeSync />
                    {app}
                    <PwaInstallBanner />
                    <Toaster />
                </TooltipProvider>
            </OfflineSyncProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

router.on('success', (event) => {
    rememberInertiaPage(event.detail.page);
});

initializeTheme();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* sin SW la app sigue funcionando */
        });
    });
}
