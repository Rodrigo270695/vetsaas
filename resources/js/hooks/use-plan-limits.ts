import { usePage } from '@inertiajs/react';
import type { PlanLimitFeature, PlanLimitsSnapshot } from '@/types/plan-limits';

export function usePlanLimits(): PlanLimitsSnapshot | null {
    const raw = usePage().props.plan_limits as PlanLimitsSnapshot | null | undefined;

    return raw ?? null;
}

export function usePlanLimitReached(feature: PlanLimitFeature): boolean {
    const limits = usePlanLimits();

    return limits?.[feature]?.reached ?? false;
}
