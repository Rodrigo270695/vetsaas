export type User = {
    id: string | number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
    /** Lista plana de strings tipo "modulo.accion". */
    permissions: string[];
    /** Lista de nombres de roles asignados (e.g. ['superadmin']). */
    roles: string[];
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
