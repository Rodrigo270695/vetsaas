import { usePage } from '@inertiajs/react';
import type { TenantModulesSnapshot } from '@/types/tenant-modules';

export function useTenantModules(): TenantModulesSnapshot | null {
    return (usePage().props.tenant_modules as TenantModulesSnapshot | null) ?? null;
}

export function useTenantModuleEnabled(moduleKey: string): boolean {
    const modules = useTenantModules();

    if (!modules) {
        return true;
    }

    return modules.enabled[moduleKey] !== false;
}
