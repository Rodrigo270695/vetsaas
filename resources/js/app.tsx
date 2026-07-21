import { createInertiaApp, router } from '@inertiajs/react';
import { ClinicThemeSync } from '@/components/clinic-theme-sync';
import PwaInstallBanner from '@/components/pwa-install-banner';
import { OfflineSyncProvider } from '@/contexts/offline-sync-context';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicDocumentLayout from '@/layouts/public-document-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { applyInitialClinicThemeFromDocument } from '@/lib/apply-initial-clinic-theme';
import { showConsoleSecurityWarning } from '@/lib/console-security-warning';
import '@/lib/i18n';
import { rememberInertiaPage } from '@/lib/offline/page-cache';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

initializeTheme();
applyInitialClinicThemeFromDocument();
showConsoleSecurityWarning();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('public/'):
                return PublicDocumentLayout;
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

// PWA instalada: si el bundle quedó desactualizado, forzar recarga completa.
router.on('invalid', () => {
    window.location.reload();
});

window.addEventListener('unhandledrejection', (event) => {
    const reason = event.reason;
    const message =
        typeof reason === 'string'
            ? reason
            : reason instanceof Error
              ? reason.message
              : '';

    if (
        message.includes('Failed to fetch dynamically imported module') ||
        message.includes('Importing a module script failed') ||
        message.includes('error loading dynamically imported module')
    ) {
        event.preventDefault();
        window.location.reload();
    }
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* sin SW la app sigue funcionando */
        });
    });
}
