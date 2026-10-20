import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { StatBadge } from '@/components/data-page';
import {
    resolveExpiryAnchor,
    resolveSubscriptionExpiryLabel,
    resolveSubscriptionUrgency,
    urgencyBadgeVariant,
    urgencyDotClass,
    type SubscriptionExpiryInput,
    type SubscriptionExpiryLabel,
} from '@/lib/subscription-expiry';

type SubscriptionExpiryBadgeProps = {
    subscription: SubscriptionExpiryInput | null | undefined;
    /** Si true, muestra la fecha además del semáforo. */
    showDate?: boolean;
    className?: string;
};

const formatDate = (value: Date): string =>
    value.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

function labelFromKey(t: (key: string, opts?: Record<string, unknown>) => string, spec: SubscriptionExpiryLabel): string {
    switch (spec.key) {
        case 'muted':
            return t('labels.muted');
        case 'grace_last_day':
            return t('labels.grace_last_day');
        case 'grace_remaining':
            return t('labels.grace_remaining', { count: spec.count });
        case 'grace_info':
            return t('labels.grace_info', { days: spec.days });
        case 'expired':
            return t('labels.expired', { count: spec.count });
        case 'today':
            return t('labels.today');
        case 'one_day':
            return t('labels.one_day');
        case 'within_3':
            return t('labels.within_3', { count: spec.count });
        case 'within_7':
            return t('labels.within_7', { count: spec.count });
        case 'ok':
            return t('labels.ok', { count: spec.count });
        default:
            return t('labels.muted');
    }
}

export function SubscriptionExpiryBadge({
    subscription,
    showDate = true,
    className,
}: SubscriptionExpiryBadgeProps) {
    const { t } = useTranslation('subscription-expiry');

    const { urgency, anchor, label } = useMemo(() => {
        const resolvedAnchor = resolveExpiryAnchor(subscription);
        const resolvedUrgency = resolveSubscriptionUrgency(subscription);
        const labelSpec = resolveSubscriptionExpiryLabel(subscription);

        return {
            urgency: resolvedUrgency,
            anchor: resolvedAnchor,
            label: labelFromKey(t, labelSpec),
        };
    }, [subscription, t]);

    if (!subscription) {
        return (
            <span className="text-xs text-muted-foreground italic">
                {t('no_subscription')}
            </span>
        );
    }

    return (
        <div className={className}>
            <div className="flex items-center gap-1.5">
                <span
                    className={`size-2 shrink-0 rounded-full ${urgencyDotClass(urgency)}`}
                    aria-hidden
                />
                <StatBadge
                    label={label}
                    value=""
                    variant={urgencyBadgeVariant(urgency)}
                />
            </div>
            {showDate && anchor && (
                <span className="mt-0.5 block text-[10px] text-muted-foreground">
                    {formatDate(anchor)}
                </span>
            )}
        </div>
    );
}
