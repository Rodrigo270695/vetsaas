import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { StatBadge } from '@/components/data-page';
import {
    daysUntilRenewal,
    resolveExpiryAnchor,
    resolveSubscriptionUrgency,
    urgencyBadgeVariant,
    urgencyDotClass,
    type SubscriptionExpiryInput,
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

export function SubscriptionExpiryBadge({
    subscription,
    showDate = true,
    className,
}: SubscriptionExpiryBadgeProps) {
    const { t } = useTranslation('subscription-expiry');

    const { urgency, days, anchor } = useMemo(() => {
        const resolvedAnchor = resolveExpiryAnchor(subscription);
        const resolvedDays = daysUntilRenewal(resolvedAnchor);
        const resolvedUrgency = resolveSubscriptionUrgency(subscription);

        return {
            urgency: resolvedUrgency,
            days: resolvedDays,
            anchor: resolvedAnchor,
        };
    }, [subscription]);

    if (!subscription) {
        return (
            <span className="text-xs text-muted-foreground italic">
                {t('no_subscription')}
            </span>
        );
    }

    const label =
        days === null
            ? t('labels.muted')
            : days < 0
              ? t('labels.expired', { count: Math.abs(days) })
              : days === 0
                ? t('labels.today')
                : days === 1
                  ? t('labels.one_day')
                  : days <= 3
                    ? t('labels.within_3', { count: days })
                    : days <= 7
                      ? t('labels.within_7', { count: days })
                      : t('labels.ok', { count: days });

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
