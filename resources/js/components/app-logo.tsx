import { usePage } from '@inertiajs/react';
import { ClinicLogoMark } from '@/components/clinic-logo-mark';
import type { TenantShared } from '@/types/tenant';

export default function AppLogo() {
    const tenant = usePage().props.tenant as TenantShared | null;
    const brandTitle = tenant
        ? (tenant.nombre_comercial || tenant.razon_social).trim()
        : 'VetSaaS';

    return (
        <>
            <ClinicLogoMark
                logoUrl={tenant?.logo_url}
                className="size-8 rounded-md"
                fallbackClassName="size-8"
            />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold tracking-tight">
                    {brandTitle}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Gestión clínica veterinaria
                </span>
            </div>
        </>
    );
}
