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

/**
 * Candidato a win-back Free por email: plan free, vencido, con email.
 */
export function isFreeExpiredWinBackCandidate(tenant: {
    email_admin?: string | null;
    telefono?: string | null;
    estado?: string;
    subscriptions?: readonly {
        estado: string;
        trial_ends_at?: string | null;
        current_period_end?: string | null;
        grace_ends_at?: string | null;
        proximo_cobro_at?: string | null;
        plan?: { codigo?: string | null } | null;
    }[] | null;
}): boolean {
    if (tenant.estado === 'cancelled') {
        return false;
    }

    const email = (tenant.email_admin ?? '').trim();
    const phone = (tenant.telefono ?? '').replace(/\D+/g, '');
    const hasEmail = email !== '' && email.includes('@');
    const hasPhone = phone.length >= 9;
    if (!hasEmail && !hasPhone) {
        return false;
    }

    const sub = livingSubscription(tenant.subscriptions);
    if (!sub || sub.plan?.codigo !== 'free') {
        return false;
    }

    if (sub.estado === 'cancelled') {
        return false;
    }

    const anchorRaw =
        sub.estado === 'grace' && sub.grace_ends_at
            ? sub.grace_ends_at
            : sub.estado === 'trial'
              ? sub.trial_ends_at
              : (sub.proximo_cobro_at ?? sub.current_period_end ?? sub.trial_ends_at);

    if (!anchorRaw) {
        return false;
    }

    const anchor = new Date(anchorRaw);
    if (Number.isNaN(anchor.getTime())) {
        return false;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    anchor.setHours(0, 0, 0, 0);

    return anchor.getTime() < today.getTime();
}
