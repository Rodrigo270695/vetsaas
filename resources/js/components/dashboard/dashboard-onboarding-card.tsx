import { Check, ChevronRight, Lock, Rocket } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
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
        <section className="overflow-hidden rounded-xl border border-brand-200/60 bg-linear-to-r from-brand-50/80 via-card to-card shadow-sm dark:border-brand-800/35 dark:from-brand-950/30">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-brand-200/40 px-4 py-3 dark:border-brand-800/25">
                <div className="flex min-w-0 flex-1 items-center gap-2.5">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-200">
                        <Rocket className="size-4" aria-hidden />
                    </div>
                    <div className="min-w-0">
                        <h2 className="text-sm font-semibold text-foreground">{t('banner.title')}</h2>
                        <p className="truncate text-xs text-muted-foreground">{t('banner.subtitle_short')}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    {data.preview && (
                        <span className="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                            {t('banner.preview')}
                        </span>
                    )}
                    <span className="font-medium tabular-nums">
                        {t('banner.progress', {
                            completed: data.completed_steps,
                            total: data.total_steps,
                        })}
                    </span>
                    <span className="font-bold tabular-nums text-brand-700 dark:text-brand-300">{progressPct}%</span>
                </div>
            </div>

            <div className="px-4 pt-2">
                <div className="h-1 overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full rounded-full bg-brand-500 transition-all"
                        style={{ width: `${progressPct}%` }}
                    />
                </div>
            </div>

            <div className="grid gap-2 p-3 sm:grid-cols-3">
                {data.steps.map((step) => {
                    const canNavigate = Boolean(step.href) && !step.locked && !step.completed;

                    return (
                        <div
                            key={step.id}
                            className={cn(
                                'relative flex min-h-[88px] flex-col rounded-lg border px-3 py-2.5 transition-colors',
                                step.current
                                    ? 'border-brand-300/80 bg-brand-50/60 ring-1 ring-brand-200/50 dark:border-brand-700/50 dark:bg-brand-950/25 dark:ring-brand-800/40'
                                    : 'border-border/50 bg-card/40',
                                step.completed && 'opacity-90',
                            )}
                        >
                            <div className="flex items-start gap-2">
                                <div
                                    className={cn(
                                        'flex size-5 shrink-0 items-center justify-center rounded-full',
                                        step.completed
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                            : step.locked
                                              ? 'bg-muted text-muted-foreground'
                                              : 'bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-200',
                                    )}
                                >
                                    {step.completed ? (
                                        <Check className="size-3" aria-hidden />
                                    ) : step.locked ? (
                                        <Lock className="size-2.5" aria-hidden />
                                    ) : (
                                        <span className="size-1.5 rounded-full bg-current" />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-semibold leading-tight text-foreground">
                                        {t(step.title)}
                                    </p>
                                    {step.required && !step.completed && (
                                        <span className="mt-0.5 inline-block text-[10px] font-medium text-amber-700 dark:text-amber-300">
                                            {t('banner.required_badge')}
                                        </span>
                                    )}
                                </div>
                            </div>

                            <p className="mt-1.5 line-clamp-2 flex-1 text-[11px] leading-snug text-muted-foreground">
                                {t(step.description)}
                            </p>

                            {canNavigate && step.href ? (
                                <Link
                                    href={step.href}
                                    className="mt-2 inline-flex items-center gap-0.5 text-[11px] font-semibold text-brand-700 hover:underline dark:text-brand-300"
                                >
                                    {t('banner.cta')}
                                    <ChevronRight className="size-3" aria-hidden />
                                </Link>
                            ) : step.completed ? (
                                <span className="mt-2 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                                    {t('banner.completed')}
                                </span>
                            ) : step.locked ? (
                                <span className="mt-2 text-[10px] text-muted-foreground">{t('banner.locked_hint')}</span>
                            ) : null}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
