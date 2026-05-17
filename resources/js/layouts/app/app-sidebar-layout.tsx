import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { TenantImpersonationBanner } from '@/components/tenant-impersonation-banner';
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
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="h-svh max-h-svh overflow-hidden md:h-[calc(100svh-(--spacing(4)))] md:max-h-[calc(100svh-(--spacing(4)))]"
            >
                <TenantImpersonationBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="flex-1 overflow-y-auto overflow-x-hidden">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
