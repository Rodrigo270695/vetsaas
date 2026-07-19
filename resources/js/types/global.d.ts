import type { ClinicBranding } from '@/types/clinic-branding';
import type { Auth } from '@/types/auth';
import type { PlanLimitsSnapshot } from '@/types/plan-limits';
import type { TenancyShared } from '@/types/tenancy';
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
            clinic_branding: ClinicBranding | null;
            tenancy: TenancyShared;
            plan_limits: PlanLimitsSnapshot | null;
            subscription_renewal_alert: import('@/components/subscription-renewal-reminder-modal').SubscriptionRenewalAlert | null;
            bot_ia_addon: { activo: boolean; precio_mensual: string | null } | null;
            in_app_assistant: {
                enabled: boolean;
                configured: boolean;
                scope?: 'clinic' | 'platform';
                unlimited?: boolean;
                announcement: {
                    active: boolean;
                    version: number;
                    title?: string | null;
                    body?: string | null;
                    features?: string[];
                } | null;
            } | null;
            tenant_modules: import('@/types/tenant-modules').TenantModulesSnapshot | null;
            tenant_impersonation: SharedTenantImpersonation | null;
            [key: string]: unknown;
        };
    }
}
