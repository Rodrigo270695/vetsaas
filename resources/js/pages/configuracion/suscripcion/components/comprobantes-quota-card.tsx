import { ReceiptText } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

export type ComprobantesQuota = {
    enabled: boolean;
    unlimited: boolean;
    used: number;
    included: number | null;
    remaining: number | null;
    ciclo: 'mensual' | 'anual';
    period_start: string;
    period_end: string;
    usage_pct: number | null;
    semaphore: 'unlimited' | 'ok' | 'caution' | 'warning' | 'over';
    allows_overage: boolean;
    overage_units: number;
    overage_blocks: number;
    overage_cost: string;
    overage_block_size: number;
    overage_cost_per_block: string;
};

type Props = {
    quota: ComprobantesQuota | null;
    locale: string;
    className?: string;
    compact?: boolean;
};

const formatDate = (value: string, locale: string): string => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const semaphoreStyles: Record<
    ComprobantesQuota['semaphore'],
    { bar: string; badge: string }
> = {
    unlimited: {
        bar: 'bg-sky-500',
        badge: 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
    },
    ok: {
        bar: 'bg-emerald-500',
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    },
    caution: {
        bar: 'bg-yellow-500',
        badge: 'bg-yellow-100 text-yellow-900 dark:bg-yellow-950/40 dark:text-yellow-200',
    },
    warning: {
        bar: 'bg-amber-500',
        badge: 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
    },
    over: {
        bar: 'bg-red-500',
        badge: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-200',
    },
};

export function ComprobantesQuotaCard({
    quota,
    locale,
    className,
    compact = false,
}: Props) {
    const { t } = useTranslation(['config-suscripcion']);

    const styles = quota ? semaphoreStyles[quota.semaphore] : semaphoreStyles.ok;

    const progressPct = useMemo(() => {
        if (!quota || quota.unlimited || quota.included === null || quota.included <= 0) {
            return quota?.unlimited ? 100 : 0;
        }

        return Math.min(100, (quota.used / quota.included) * 100);
    }, [quota]);

    if (!quota?.enabled) {
        return null;
    }

    const usageLabel = quota.unlimited
        ? t('comprobantes.usage_unlimited', { used: quota.used })
        : t('comprobantes.usage', {
              used: quota.used,
              included: quota.included ?? 0,
          });

    const overageCost = Number(quota.overage_cost);
    const periodLabel = t(`comprobantes.period_${quota.ciclo}`, {
        start: formatDate(quota.period_start, locale),
        end: formatDate(quota.period_end, locale),
    });

    if (compact) {
        return (
            <div
                className={cn(
                    'rounded-xl border border-border/60 bg-card/80 p-4 ring-1 ring-border/20',
                    className,
                )}
            >
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <ReceiptText className="size-4 text-primary" />
                        <h3 className="text-sm font-semibold text-foreground">
                            {t('comprobantes.title')}
                        </h3>
                        <span
                            className={cn(
                                'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                styles.badge,
                            )}
                        >
                            {t(`comprobantes.semaphore.${quota.semaphore}`)}
                        </span>
                    </div>
                    <p className="text-sm font-medium tabular-nums text-foreground">
                        {usageLabel}
                    </p>
                </div>

                <p className="mt-1 text-xs text-muted-foreground">{periodLabel}</p>

                {!quota.unlimited && quota.included !== null && quota.included > 0 && (
                    <div className="mt-2.5 space-y-1">
                        <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                            <span>{t('comprobantes.progress_label')}</span>
                            <span className="tabular-nums">
                                {quota.usage_pct !== null ? `${quota.usage_pct}%` : '—'}
                                {quota.remaining !== null
                                    ? ` · ${t('comprobantes.remaining')}: ${quota.remaining}`
                                    : ''}
                            </span>
                        </div>
                        <div
                            className="h-1.5 overflow-hidden rounded-full bg-muted/60"
                            role="progressbar"
                            aria-valuenow={quota.used}
                            aria-valuemin={0}
                            aria-valuemax={quota.included}
                            aria-label={usageLabel}
                        >
                            <div
                                className={cn(
                                    'h-full rounded-full transition-all',
                                    styles.bar,
                                )}
                                style={{ width: `${progressPct}%` }}
                            />
                        </div>
                    </div>
                )}

                {quota.semaphore === 'over' &&
                    quota.ciclo === 'mensual' &&
                    overageCost > 0 && (
                        <p className="mt-2 text-xs text-amber-800 dark:text-amber-200">
                            {t('comprobantes.overage_title', {
                                cost: `S/. ${overageCost.toFixed(2)}`,
                            })}
                        </p>
                    )}

                <p className="mt-2 text-[11px] text-muted-foreground">
                    {t('comprobantes.allow_overage')}
                </p>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'rounded-xl border border-border/60 bg-card/80 p-4 ring-1 ring-border/20',
                className,
            )}
        >
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-1">
                        <p className="text-2xl font-semibold tracking-tight text-foreground">
                            {usageLabel}
                        </p>
                        <p className="text-sm text-muted-foreground">{periodLabel}</p>
                    </div>

                    {!quota.unlimited && quota.remaining !== null && (
                        <div className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-sm">
                            <span className="text-muted-foreground">
                                {t('comprobantes.remaining')}:{' '}
                            </span>
                            <span className="font-semibold text-foreground">
                                {quota.remaining}
                            </span>
                        </div>
                    )}
                </div>

                {!quota.unlimited && quota.included !== null && quota.included > 0 && (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>{t('comprobantes.progress_label')}</span>
                            <span className="tabular-nums">
                                {quota.usage_pct !== null ? `${quota.usage_pct}%` : '—'}
                            </span>
                        </div>
                        <div
                            className="h-2 overflow-hidden rounded-full bg-muted/60"
                            role="progressbar"
                            aria-valuenow={quota.used}
                            aria-valuemin={0}
                            aria-valuemax={quota.included}
                            aria-label={usageLabel}
                        >
                            <div
                                className={cn(
                                    'h-full rounded-full transition-all',
                                    styles.bar,
                                )}
                                style={{ width: `${progressPct}%` }}
                            />
                        </div>
                    </div>
                )}

                {quota.allows_overage && (
                    <p className="text-xs text-muted-foreground">
                        {t('comprobantes.allow_overage')}
                    </p>
                )}
            </div>
        </div>
    );
}
