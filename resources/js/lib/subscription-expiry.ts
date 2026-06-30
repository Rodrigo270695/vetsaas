/**
 * Fecha de vencimiento y semáforo a partir de la suscripción viva.
 * Espejo de `App\Support\Subscriptions\SubscriptionExpiry`.
 */

export type SubscriptionUrgency =
    | 'ok'
    | 'yellow'
    | 'amber'
    | 'red'
    | 'danger'
    | 'muted';

export type VencimientoFilter =
    | 'todos'
    | 'por_vencer_7'
    | 'por_vencer_3'
    | 'por_vencer_1'
    | 'vencido';

export type SubscriptionExpiryInput = {
    estado: string;
    trial_ends_at?: string | null;
    current_period_end?: string | null;
    grace_ends_at?: string | null;
    proximo_cobro_at?: string | null;
};

export function resolveExpiryAnchor(
    subscription: SubscriptionExpiryInput | null | undefined,
): Date | null {
    if (!subscription) {
        return null;
    }

    const pick = (value?: string | null): Date | null => {
        if (!value) return null;
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
    };

    if (subscription.estado === 'grace') {
        const grace = pick(subscription.grace_ends_at);
        if (grace) return grace;
    }

    if (subscription.estado === 'trial') {
        return pick(subscription.trial_ends_at);
    }

    return (
        pick(subscription.proximo_cobro_at) ??
        pick(subscription.current_period_end) ??
        pick(subscription.trial_ends_at)
    );
}

export function daysUntilRenewal(anchor: Date | null): number | null {
    if (!anchor) return null;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const target = new Date(anchor);
    target.setHours(0, 0, 0, 0);

    const diffMs = target.getTime() - today.getTime();

    return Math.round(diffMs / (1000 * 60 * 60 * 24));
}

export function resolveUrgency(
    estado: string,
    daysUntil: number | null,
): SubscriptionUrgency {
    if (estado === 'suspended' || estado === 'cancelled') {
        return 'danger';
    }

    if (daysUntil === null) {
        return 'muted';
    }

    if (daysUntil < 0) {
        return 'red';
    }

    if (daysUntil <= 1) {
        return 'red';
    }

    if (daysUntil <= 3) {
        return 'amber';
    }

    if (daysUntil <= 7) {
        return 'yellow';
    }

    return 'ok';
}

export function resolveSubscriptionUrgency(
    subscription: SubscriptionExpiryInput | null | undefined,
): SubscriptionUrgency {
    if (!subscription) {
        return 'muted';
    }

    const anchor = resolveExpiryAnchor(subscription);
    const days = daysUntilRenewal(anchor);

    return resolveUrgency(subscription.estado, days);
}

export function urgencyBadgeVariant(
    urgency: SubscriptionUrgency,
): 'success' | 'warning' | 'danger' | 'muted' | 'info' | 'primary' {
    switch (urgency) {
        case 'ok':
            return 'success';
        case 'yellow':
            return 'warning';
        case 'amber':
            return 'warning';
        case 'red':
            return 'danger';
        case 'danger':
            return 'danger';
        default:
            return 'muted';
    }
}

export function urgencyDotClass(urgency: SubscriptionUrgency): string {
    switch (urgency) {
        case 'ok':
            return 'bg-emerald-500';
        case 'yellow':
            return 'bg-yellow-400';
        case 'amber':
            return 'bg-amber-500';
        case 'red':
            return 'bg-red-500';
        case 'danger':
            return 'bg-red-600';
        default:
            return 'bg-muted-foreground/40';
    }
}
