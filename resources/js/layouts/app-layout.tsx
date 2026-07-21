import { PresenceHeartbeat } from '@/components/presence-heartbeat';
import { TourManager } from '@/components/in-app-assistant/tour-manager';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            <PresenceHeartbeat />
            <TourManager />
            {children}
        </AppLayoutTemplate>
    );
}
