import {
    Boxes,
    Building2,
    PawPrint,
    UserRound,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { usePlanLimits } from '@/hooks/use-plan-limits';
import { cn } from '@/lib/utils';
import type { PlanLimitFeature } from '@/types/plan-limits';

const FEATURES: PlanLimitFeature[] = [
    'max_pacientes',
    'max_propietarios',
    'max_usuarios',
    'max_productos',
    'max_sedes',
];

const FEATURE_ICONS: Record<
    PlanLimitFeature,
    React.ComponentType<{ className?: string }>
> = {
    max_sedes: Building2,
    max_usuarios: Users,
    max_pacientes: PawPrint,
    max_propietarios: UserRound,
    max_productos: Boxes,
};

type Semaphore = 'unlimited' | 'ok' | 'caution' | 'warning' | 'over';

const semaphoreStyles: Record<Semaphore, { bar: string; badge: string }> = {
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

function resolveSemaphore(
    used: number,
    limit: number | null,
    unlimited: boolean,
    provided?: string,
): Semaphore {
    if (
        provided === 'unlimited' ||
        provided === 'ok' ||
        provided === 'caution' ||
        provided === 'warning' ||
        provided === 'over'
    ) {
        return provided;
    }

    if (unlimited || limit === null || limit <= 0) {
        return 'unlimited';
    }

    if (used >= limit) {
        return 'over';
    }

    const pct = (used / limit) * 100;
    if (pct >= 90) {
        return 'warning';
    }
    if (pct >= 75) {
        return 'caution';
    }

    return 'ok';
}

export function PlanUsageSection() {
    const { t } = useTranslation(['config-suscripcion']);
    const limits = usePlanLimits();

    const rows = useMemo(() => {
        if (!limits) {
            return [];
        }

        return FEATURES.map((feature) => {
            const entry = limits[feature];
            if (!entry) {
                return null;
            }

            const semaphore = resolveSemaphore(
                entry.used,
                entry.limit,
                entry.unlimited,
                entry.semaphore,
            );

            return { feature, entry, semaphore };
        }).filter(Boolean) as Array<{
            feature: PlanLimitFeature;
            entry: NonNullable<(typeof limits)[PlanLimitFeature]>;
            semaphore: Semaphore;
        }>;
    }, [limits]);

    if (rows.length === 0) {
        return null;
    }

    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-sm font-semibold text-foreground">
                    {t('usage.title')}
                </h2>
                <p className="text-xs text-muted-foreground">
                    {t('usage.description')}
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {rows.map(({ feature, entry, semaphore }) => {
                    const Icon = FEATURE_ICONS[feature];
                    const styles = semaphoreStyles[semaphore];
                    const progressPct = entry.unlimited
                        ? 100
                        : entry.limit && entry.limit > 0
                          ? Math.min(100, (entry.used / entry.limit) * 100)
                          : 0;

                    const usageLabel = entry.unlimited
                        ? t('usage.usage_unlimited', { used: entry.used })
                        : t('usage.usage', {
                              used: entry.used,
                              limit: entry.limit ?? 0,
                          });

                    return (
                        <div
                            key={feature}
                            className="rounded-xl border border-border/60 bg-card/80 p-4 ring-1 ring-border/20"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex min-w-0 items-center gap-2">
                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted/50">
                                        <Icon className="size-4 text-primary" />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-foreground">
                                            {t(`usage.features.${feature}`)}
                                        </p>
                                        <p className="text-xs tabular-nums text-muted-foreground">
                                            {usageLabel}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    className={cn(
                                        'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                        styles.badge,
                                    )}
                                >
                                    {t(`usage.semaphore.${semaphore}`)}
                                </span>
                            </div>

                            {!entry.unlimited &&
                            entry.limit !== null &&
                            entry.limit > 0 ? (
                                <div className="mt-3 space-y-1">
                                    <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                        <span>
                                            {entry.usage_pct !== null &&
                                            entry.usage_pct !== undefined
                                                ? `${entry.usage_pct}%`
                                                : `${Math.round(progressPct)}%`}
                                        </span>
                                        {entry.remaining !== null ? (
                                            <span className="tabular-nums">
                                                {t('usage.remaining', {
                                                    count: entry.remaining,
                                                })}
                                            </span>
                                        ) : null}
                                    </div>
                                    <div
                                        className="h-1.5 overflow-hidden rounded-full bg-muted/60"
                                        role="progressbar"
                                        aria-valuenow={entry.used}
                                        aria-valuemin={0}
                                        aria-valuemax={entry.limit}
                                        aria-label={usageLabel}
                                    >
                                        <div
                                            className={cn(
                                                'h-full rounded-full transition-all',
                                                styles.bar,
                                            )}
                                            style={{
                                                width: `${progressPct}%`,
                                            }}
                                        />
                                    </div>
                                    {entry.extra && entry.extra > 0 ? (
                                        <p
                                            className={cn(
                                                'text-[11px]',
                                                entry.is_paid_extra
                                                    ? 'text-sky-700 dark:text-sky-400'
                                                    : 'text-emerald-700 dark:text-emerald-400',
                                            )}
                                        >
                                            {entry.is_paid_extra
                                                ? t('usage.includes_paid', {
                                                      count: entry.extra,
                                                      amount: Number(
                                                          entry.precio_mensual ?? 0,
                                                      ).toFixed(2),
                                                  })
                                                : t('usage.includes_extra', {
                                                      count: entry.extra,
                                                  })}
                                        </p>
                                    ) : null}
                                </div>
                            ) : (
                                <p className="mt-2 text-[11px] text-muted-foreground">
                                    {t('usage.unlimited_hint')}
                                </p>
                            )}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
