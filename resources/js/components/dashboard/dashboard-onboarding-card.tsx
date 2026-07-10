import { Check, Circle, Lock, Rocket } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { OnboardingSnapshot } from '@/pages/dashboard/types';

type Props = {
    data: OnboardingSnapshot;
};

export function DashboardOnboardingCard({ data }: Props) {
    const { t } = useTranslation('onboarding');

    if (!data.show) {
        return null;
    }

    const progressPct = data.total_steps > 0
        ? Math.round((data.completed_steps / data.total_steps) * 100)
        : 0;

    return (
        <Card className="gap-0 overflow-hidden border-brand-200/70 bg-linear-to-br from-brand-50/90 via-card to-card py-0 shadow-sm dark:border-brand-800/40 dark:from-brand-950/40">
            <CardHeader className="border-b border-brand-200/50 pb-4 pt-5 dark:border-brand-800/30">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700 ring-1 ring-brand-200/60 dark:bg-brand-900/50 dark:text-brand-200">
                            <Rocket className="size-5" aria-hidden />
                        </div>
                        <div className="min-w-0">
                            <h2 className="text-lg font-semibold text-foreground">{t('banner.title')}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">{t('banner.subtitle')}</p>
                        </div>
                    </div>
                    <div className="shrink-0 rounded-lg border border-brand-200/60 bg-card/80 px-3 py-2 text-right dark:border-brand-800/40">
                        <p className="text-xs font-medium text-muted-foreground">
                            {t('banner.progress', {
                                completed: data.completed_steps,
                                total: data.total_steps,
                            })}
                        </p>
                        <p className="text-lg font-bold tabular-nums text-brand-700 dark:text-brand-300">
                            {progressPct}%
                        </p>
                    </div>
                </div>
                <div className="mt-4 h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full rounded-full bg-brand-500 transition-all"
                        style={{ width: `${progressPct}%` }}
                    />
                </div>
            </CardHeader>

            <CardContent className="space-y-2 px-4 py-4 sm:px-6">
                {data.steps.map((step) => {
                    const canNavigate = Boolean(step.href) && !step.locked && !step.completed;

                    return (
                        <div
                            key={step.id}
                            className={cn(
                                'flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between',
                                step.current
                                    ? 'border-brand-300/70 bg-brand-50/50 dark:border-brand-700/50 dark:bg-brand-950/20'
                                    : 'border-border/60 bg-card/50',
                                step.completed && 'opacity-80',
                            )}
                        >
                            <div className="flex min-w-0 items-start gap-3">
                                <div
                                    className={cn(
                                        'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full',
                                        step.completed
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                            : step.locked
                                              ? 'bg-muted text-muted-foreground'
                                              : step.current
                                                ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-200'
                                                : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {step.completed ? (
                                        <Check className="size-4" aria-hidden />
                                    ) : step.locked ? (
                                        <Lock className="size-3.5" aria-hidden />
                                    ) : (
                                        <Circle className="size-4" aria-hidden />
                                    )}
                                </div>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-medium text-foreground">{t(step.title)}</p>
                                        {step.required && !step.completed && (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
                                                {t('banner.required_badge')}
                                            </span>
                                        )}
                                        {step.completed && (
                                            <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                {t('banner.completed')}
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-0.5 text-sm text-muted-foreground">{t(step.description)}</p>
                                    {step.locked && (
                                        <p className="mt-1 text-xs text-muted-foreground">{t('banner.locked_hint')}</p>
                                    )}
                                    {!step.href && !step.completed && !step.locked && (
                                        <p className="mt-1 text-xs text-muted-foreground">{t('banner.no_permission')}</p>
                                    )}
                                </div>
                            </div>

                            {canNavigate && step.href && (
                                <Button type="button" size="sm" className="shrink-0 cursor-pointer" asChild>
                                    <Link href={step.href}>{t('banner.cta')}</Link>
                                </Button>
                            )}
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
