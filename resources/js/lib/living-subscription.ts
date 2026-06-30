/**
 * Devuelve la suscripción viva del tenant (trial, active, grace o suspended).
 */
export function livingSubscription<
    T extends {
        estado: string;
        trial_ends_at?: string | null;
        current_period_end?: string | null;
        grace_ends_at?: string | null;
        proximo_cobro_at?: string | null;
        plan?: P | null;
    },
    P = unknown,
>(subscriptions: readonly T[] | null | undefined): T | null {
    return subscriptions?.[0] ?? null;
}
