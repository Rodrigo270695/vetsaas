import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ClinicSedeLocationBanner } from '@/components/clinic-location/clinic-sede-location-banner';
import { DemoAccessGeoCapture } from '@/components/clinic-location/demo-access-geo-capture';
import { TenantGeoRefreshCapture } from '@/components/clinic-location/tenant-geo-refresh-capture';
import { TenantGpsConsentModal } from '@/components/clinic-location/tenant-gps-consent-modal';
import { InAppAssistantAnnouncementModal } from '@/components/in-app-assistant/in-app-assistant-announcement-modal';
import { OfflineStatusBanner } from '@/components/offline-status-banner';
import { SubscriptionRenewalReminderModal } from '@/components/subscription-renewal-reminder-modal';
import { TenantImpersonationBanner } from '@/components/tenant-impersonation-banner';
import { WhatsAppNeedsLinkBanner } from '@/components/whatsapp-needs-link-banner';
import { useWhatsAppDisconnectedToast } from '@/hooks/use-whatsapp-disconnected-toast';
import { PlatformSupportChatNotifier } from '@/components/plataforma/platform-support-chat-notifier';
import { TenantChatNotifier } from '@/components/comunicaciones/tenant-chat-notifier';
import { PlatformSupportChatUnreadProvider } from '@/contexts/platform-support-chat-unread-context';
import { TenantChatUnreadProvider } from '@/contexts/tenant-chat-unread-context';
import { usePage } from '@inertiajs/react';
import type { AppLayoutProps } from '@/types';

/**
 * Layout principal de la app con sidebar lateral.
 *
 * Header de breadcrumbs fijo + contenido scrollable internamente.
 *
 * Nota técnica: `Sidebar variant="inset"` aplica `md:m-2` al
 * `SidebarInset`, por lo que ocupar `h-svh` exacto provocaba que el
 * componente se desbordara del viewport (100svh + 1rem de margin) y el
 * `<body>` ganara su propio scroll, llevándose el header. En md+
 * compensamos restando `--spacing(4)` (= 1rem = margin top + bottom).
 */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    useWhatsAppDisconnectedToast();
    const page = usePage();
    const initialUnread = page.props.tenant_chat?.unread_total ?? 0;
    const initialPlatformUnread =
        page.props.platform_support_chat?.unread_total ?? 0;

    return (
        <TenantChatUnreadProvider initialUnread={initialUnread}>
            <PlatformSupportChatUnreadProvider
                initialUnread={initialPlatformUnread}
            >
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    className="h-svh max-h-svh overflow-hidden md:h-[calc(100svh-(--spacing(4)))] md:max-h-[calc(100svh-(--spacing(4)))]"
                >
                    <TenantImpersonationBanner />
                    <WhatsAppNeedsLinkBanner />
                    <OfflineStatusBanner />
                    <ClinicSedeLocationBanner />
                    <SubscriptionRenewalReminderModal />
                    <TenantGpsConsentModal />
                    <TenantGeoRefreshCapture />
                    <DemoAccessGeoCapture />
                    <InAppAssistantAnnouncementModal />
                    <TenantChatNotifier />
                    <PlatformSupportChatNotifier />
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {/*
                      Por defecto el contenido scrollea. Páginas “pantalla fija”
                      (chat) marcan data-fixed-viewport y ocupan el alto restante
                      sin mover el shell ni el header.
                    */}
                    <div className="flex min-h-0 flex-1 flex-col overflow-y-auto overflow-x-hidden has-data-fixed-viewport:overflow-hidden">
                        {children}
                    </div>
                </AppContent>
            </AppShell>
            </PlatformSupportChatUnreadProvider>
        </TenantChatUnreadProvider>
    );
}
