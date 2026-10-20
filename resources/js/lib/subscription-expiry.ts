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
    billing_grace_days?: number;
};

const DEFAULT_GRACE_DAYS = 3;

function pickDate(value?: string | null): Date | null {
    if (!value) {
        return null;
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

function startOfDay(date: Date): Date {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
}

function graceDays(subscription: SubscriptionExpiryInput): number {
    const n = subscription.billing_grace_days;
    if (typeof n === 'number' && n >= 1) {
        return Math.floor(n);
    }
    return DEFAULT_GRACE_DAYS;
}

export function periodEndAnchor(
    subscription: SubscriptionExpiryInput | null | undefined,
): Date | null {
    if (!subscription) {
        return null;
    }

    if (subscription.estado === 'trial') {
        return pickDate(subscription.trial_ends_at);
    }

    return (
        pickDate(subscription.proximo_cobro_at) ??
        pickDate(subscription.current_period_end)
    );
}

export function effectiveGraceEndsAt(
    subscription: SubscriptionExpiryInput | null | undefined,
): Date | null {
    if (!subscription) {
        return null;
    }

    const explicit = pickDate(subscription.grace_ends_at);
    if (explicit) {
        return explicit;
    }

    const periodEnd = periodEndAnchor(subscription);
    if (!periodEnd) {
        return null;
    }

    const today = startOfDay(new Date());
    if (today.getTime() <= startOfDay(periodEnd).getTime()) {
        return null;
    }

    const end = new Date(periodEnd);
    end.setDate(end.getDate() + graceDays(subscription));

    return end;
}

export function isInGracePeriod(
    subscription: SubscriptionExpiryInput | null | undefined,
): boolean {
    if (!subscription) {
        return false;
    }

    if (subscription.estado === 'suspended' || subscription.estado === 'cancelled') {
        return false;
    }

    const graceEnd = effectiveGraceEndsAt(subscription);
    if (!graceEnd) {
        return false;
    }

    const today = startOfDay(new Date());
    const graceDay = startOfDay(graceEnd);

    if (subscription.estado === 'grace') {
        return today.getTime() <= graceDay.getTime();
    }

    const periodEnd = periodEndAnchor(subscription);
    if (!periodEnd) {
        return false;
    }

    const periodDay = startOfDay(periodEnd);

    return today.getTime() > periodDay.getTime() && today.getTime() <= graceDay.getTime();
}

export function resolveExpiryAnchor(
    subscription: SubscriptionExpiryInput | null | undefined,
): Date | null {
    if (!subscription) {
        return null;
    }

    if (isInGracePeriod(subscription)) {
        return effectiveGraceEndsAt(subscription);
    }

    if (subscription.estado === 'trial') {
        return pickDate(subscription.trial_ends_at);
    }

    return (
        pickDate(subscription.proximo_cobro_at) ??
        pickDate(subscription.current_period_end) ??
        pickDate(subscription.trial_ends_at)
    );
}

export function daysUntilRenewal(anchor: Date | null): number | null {
    if (!anchor) {
        return null;
    }

    const today = startOfDay(new Date());
    const target = startOfDay(anchor);

    const diffMs = target.getTime() - today.getTime();

    return Math.round(diffMs / (1000 * 60 * 60 * 24));
}

export function resolveUrgency(
    estado: string,
    daysUntil: number | null,
    inGrace = false,
): SubscriptionUrgency {
    if (estado === 'suspended' || estado === 'cancelled') {
        return 'danger';
    }

    if (daysUntil === null) {
        return 'muted';
    }

    if (inGrace) {
        if (daysUntil < 0) {
            return 'red';
        }

        return 'amber';
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

    const inGrace = isInGracePeriod(subscription);
    const anchor = resolveExpiryAnchor(subscription);
    const days = daysUntilRenewal(anchor);

    return resolveUrgency(subscription.estado, days, inGrace);
}

export type SubscriptionExpiryLabel =
    | { key: 'muted' }
    | { key: 'grace_last_day' }
    | { key: 'grace_remaining'; count: number }
    | { key: 'grace_info'; days: number }
    | { key: 'expired'; count: number }
    | { key: 'today' }
    | { key: 'one_day' }
    | { key: 'within_3'; count: number }
    | { key: 'within_7'; count: number }
    | { key: 'ok'; count: number };

export function resolveSubscriptionExpiryLabel(
    subscription: SubscriptionExpiryInput | null | undefined,
): SubscriptionExpiryLabel {
    if (!subscription) {
        return { key: 'muted' };
    }

    const inGrace = isInGracePeriod(subscription);
    const anchor = resolveExpiryAnchor(subscription);
    const days = daysUntilRenewal(anchor);

    if (days === null) {
        return { key: 'muted' };
    }

    if (inGrace) {
        if (days < 0) {
            return { key: 'expired', count: Math.abs(days) };
        }
        if (days === 0) {
            return { key: 'grace_last_day' };
        }

        return { key: 'grace_remaining', count: days };
    }

    if (days < 0) {
        return { key: 'expired', count: Math.abs(days) };
    }
    if (days === 0) {
        return { key: 'today' };
    }
    if (days === 1) {
        return { key: 'one_day' };
    }
    if (days <= 3) {
        return { key: 'within_3', count: days };
    }
    if (days <= 7) {
        return { key: 'within_7', count: days };
    }

    return { key: 'ok', count: days };
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
