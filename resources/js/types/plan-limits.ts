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
};

export type PlanLimitsSnapshot = Record<PlanLimitFeature, PlanLimitEntry>;
