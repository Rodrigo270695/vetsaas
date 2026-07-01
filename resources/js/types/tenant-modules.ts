export type TenantModulesSnapshot = {
    enabled: Record<string, boolean>;
    deshabilitados: string[];
};

export type TenantModuleGroup = {
    group: string;
    modules: Array<{
        key: string;
        enabled: boolean;
    }>;
};
