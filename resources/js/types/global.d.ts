import type { Auth } from '@/types/auth';
import type { TenantShared } from '@/types/tenant';

export type SharedTenantImpersonation = {
    tenant_label: string;
    started_at: string | null;
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            timezone: string;
            tenant: TenantShared | null;
            tenant_impersonation: SharedTenantImpersonation | null;
            [key: string]: unknown;
        };
    }
}
