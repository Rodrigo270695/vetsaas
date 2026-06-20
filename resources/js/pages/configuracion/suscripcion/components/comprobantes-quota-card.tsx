import { ReceiptText } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { SectionCard } from '../../general/components/section-card';
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
    { bar: string; badge: string; ring: string }
> = {
    unlimited: {
        bar: 'bg-sky-500',
        badge: 'bg-sky-100 text-sky-800 ring-sky-200/60 dark:bg-sky-950/40 dark:text-sky-200',
        ring: 'ring-sky-200/50',
    },
    ok: {
        bar: 'bg-emerald-500',
        badge: 'bg-emerald-100 text-emerald-800 ring-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-200',
        ring: 'ring-emerald-200/50',
    },
    caution: {
        bar: 'bg-yellow-500',
        badge: 'bg-yellow-100 text-yellow-900 ring-yellow-200/60 dark:bg-yellow-950/40 dark:text-yellow-200',
        ring: 'ring-yellow-200/50',
    },
    warning: {
        bar: 'bg-amber-500',
        badge: 'bg-amber-100 text-amber-900 ring-amber-200/60 dark:bg-amber-950/40 dark:text-amber-200',
        ring: 'ring-amber-200/50',
    },
    over: {
        bar: 'bg-red-500',
        badge: 'bg-red-100 text-red-800 ring-red-200/60 dark:bg-red-950/40 dark:text-red-200',
        ring: 'ring-red-200/50',
    },
};

export function ComprobantesQuotaCard({ quota, locale }: Props) {
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

    return (
        <SectionCard
            title={t('comprobantes.title')}
            description={t('comprobantes.description')}
            icon={ReceiptText}
            className="xl:col-span-12"
            badge={
                <span
                    className={cn(
                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1',
                        styles.badge,
                        styles.ring,
                    )}
                >
                    {t(`comprobantes.semaphore.${quota.semaphore}`)}
                </span>
            }
        >
            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-1">
                        <p className="text-2xl font-semibold tracking-tight text-foreground">
                            {usageLabel}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t(`comprobantes.period_${quota.ciclo}`, {
                                start: formatDate(quota.period_start, locale),
                                end: formatDate(quota.period_end, locale),
                            })}
                        </p>
                    </div>

                    {!quota.unlimited && quota.remaining !== null && (
                        <div className="rounded-xl border border-border/60 bg-muted/20 px-4 py-3 text-sm">
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
                            className="h-3 overflow-hidden rounded-full bg-muted/60 ring-1 ring-border/40"
                            role="progressbar"
                            aria-valuenow={quota.used}
                            aria-valuemin={0}
                            aria-valuemax={quota.included}
                            aria-label={usageLabel}
                        >
                            <div
                                className={cn(
                                    'h-full rounded-full transition-all duration-500',
                                    styles.bar,
                                )}
                                style={{ width: `${progressPct}%` }}
                            />
                        </div>
                    </div>
                )}

                {quota.semaphore === 'over' && quota.ciclo === 'anual' && (
                    <div className="rounded-xl border border-red-200/60 bg-red-50/80 px-4 py-3 text-sm text-red-950 dark:border-red-800/40 dark:bg-red-950/30 dark:text-red-100">
                        <p className="font-semibold">{t('comprobantes.annual_over_title')}</p>
                        <p className="mt-1 text-xs opacity-90">{t('comprobantes.annual_over_hint')}</p>
                    </div>
                )}

                {quota.semaphore === 'over' && quota.ciclo === 'mensual' && overageCost > 0 && (
                    <div className="rounded-xl border border-amber-200/60 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/40 dark:bg-amber-950/30 dark:text-amber-100">
                        <p className="font-semibold">
                            {t('comprobantes.overage_title', {
                                cost: `S/. ${overageCost.toFixed(2)}`,
                            })}
                        </p>
                        <p className="mt-1 text-xs opacity-90">
                            {t('comprobantes.overage_hint', {
                                block: quota.overage_block_size,
                                price: quota.overage_cost_per_block,
                                blocks: quota.overage_blocks,
                            })}
                        </p>
                    </div>
                )}

                {quota.allows_overage && (
                    <p className="text-xs text-muted-foreground">{t('comprobantes.allow_overage')}</p>
                )}
            </div>
        </SectionCard>
    );
}
