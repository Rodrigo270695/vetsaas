export type PlanLimitFeature =
    | 'max_sedes'
    | 'max_usuarios'
    | 'max_pacientes'
    | 'max_propietarios'
    | 'max_productos';

export type PlanLimitEntry = {
    limit: number | null;
    used: number;
    remaining: number | null;
    reached: boolean;
    unlimited: boolean;
    base?: number | null;
    extra?: number;
    precio_mensual?: number;
    is_paid_extra?: boolean;
    semaphore?: 'unlimited' | 'ok' | 'caution' | 'warning' | 'over';
    usage_pct?: number | null;
};

export type PlanLimitsSnapshot = Record<PlanLimitFeature, PlanLimitEntry>;
