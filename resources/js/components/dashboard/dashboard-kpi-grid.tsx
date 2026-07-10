import type { LucideIcon } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type KpiAccent = 'brand' | 'sky' | 'emerald' | 'amber' | 'violet' | 'rose' | 'slate';

export type DashboardKpiItem = {
    key: string;
    label: string;
    value: string | number;
    icon: LucideIcon;
    accent?: KpiAccent;
    highlight?: boolean;
    /** Si se define, el KPI es un enlace navegable. */
    href?: string;
};

const accentStyles: Record<
    KpiAccent,
    { card: string; icon: string; value: string }
> = {
    brand: {
        card: 'border-brand-200/60 bg-gradient-to-br from-brand-50/80 to-card dark:from-brand-950/30',
        icon: 'bg-brand-100 text-brand-700 ring-brand-200/80 dark:bg-brand-900/50 dark:text-brand-200',
        value: 'text-brand-900 dark:text-brand-100',
    },
    sky: {
        card: 'border-sky-200/50 bg-gradient-to-br from-sky-50/70 to-card dark:from-sky-950/25',
        icon: 'bg-sky-100 text-sky-700 ring-sky-200/70 dark:bg-sky-900/40 dark:text-sky-200',
        value: 'text-sky-950 dark:text-sky-100',
    },
    emerald: {
        card: 'border-emerald-200/50 bg-gradient-to-br from-emerald-50/70 to-card dark:from-emerald-950/25',
        icon: 'bg-emerald-100 text-emerald-700 ring-emerald-200/70 dark:bg-emerald-900/40 dark:text-emerald-200',
        value: 'text-emerald-950 dark:text-emerald-100',
    },
    amber: {
        card: 'border-amber-200/60 bg-gradient-to-br from-amber-50/80 to-card dark:from-amber-950/30',
        icon: 'bg-amber-100 text-amber-800 ring-amber-200/70 dark:bg-amber-900/40 dark:text-amber-200',
        value: 'text-amber-950 dark:text-amber-100',
    },
    violet: {
        card: 'border-violet-200/50 bg-gradient-to-br from-violet-50/70 to-card dark:from-violet-950/25',
        icon: 'bg-violet-100 text-violet-700 ring-violet-200/70 dark:bg-violet-900/40 dark:text-violet-200',
        value: 'text-violet-950 dark:text-violet-100',
    },
    rose: {
        card: 'border-rose-200/50 bg-gradient-to-br from-rose-50/60 to-card dark:from-rose-950/20',
        icon: 'bg-rose-100 text-rose-700 ring-rose-200/70 dark:bg-rose-900/40 dark:text-rose-200',
        value: 'text-rose-950 dark:text-rose-100',
    },
    slate: {
        card: 'border-border/70 bg-card',
        icon: 'bg-muted text-muted-foreground ring-border/60',
        value: 'text-foreground',
    },
};

type Props = {
    items: DashboardKpiItem[];
};

export function DashboardKpiGrid({ items }: Props) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {items.map((item) => {
                const Icon = item.icon;
                const accent = item.accent ?? 'slate';
                const styles = accentStyles[accent];

                const card = (
                    <article
                        className={cn(
                            'group relative overflow-hidden rounded-xl border p-4 shadow-sm transition-shadow hover:shadow-md',
                            styles.card,
                            item.highlight && 'ring-2 ring-amber-400/50 ring-offset-2 ring-offset-background',
                            item.href && 'cursor-pointer',
                        )}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-medium text-muted-foreground">{item.label}</p>
                                <p
                                    className={cn(
                                        'mt-1.5 text-2xl font-bold tabular-nums tracking-tight',
                                        styles.value,
                                    )}
                                >
                                    {item.value}
                                </p>
                            </div>
                            <div
                                className={cn(
                                    'flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 transition-transform group-hover:scale-105',
                                    styles.icon,
                                )}
                            >
                                <Icon className="size-5" aria-hidden />
                            </div>
                        </div>
                    </article>
                );

                if (item.href) {
                    return (
                        <Link key={item.key} href={item.href} className="block no-underline">
                            {card}
                        </Link>
                    );
                }

                return <div key={item.key}>{card}</div>;
            })}
        </div>
    );
}
