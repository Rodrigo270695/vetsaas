import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { KpiAccent } from '@/components/dashboard/dashboard-kpi-grid';

type ChartAccent = KpiAccent;

const headerAccent: Record<ChartAccent, string> = {
    brand:
        'border-brand-200/60 bg-gradient-to-br from-brand-50/95 via-brand-50/50 to-brand-50/10 dark:border-brand-800/40 dark:from-brand-950/50 dark:via-brand-950/25 dark:to-transparent',
    sky: 'border-sky-200/60 bg-gradient-to-br from-sky-50/95 via-sky-50/50 to-sky-50/10 dark:border-sky-800/40 dark:from-sky-950/50 dark:via-sky-950/25 dark:to-transparent',
    emerald:
        'border-emerald-200/60 bg-gradient-to-br from-emerald-50/95 via-emerald-50/50 to-emerald-50/10 dark:border-emerald-800/40 dark:from-emerald-950/50 dark:via-emerald-950/25 dark:to-transparent',
    amber:
        'border-amber-200/60 bg-gradient-to-br from-amber-50/95 via-amber-50/50 to-amber-50/10 dark:border-amber-800/40 dark:from-amber-950/50 dark:via-amber-950/25 dark:to-transparent',
    violet:
        'border-violet-200/60 bg-gradient-to-br from-violet-50/95 via-violet-50/50 to-violet-50/10 dark:border-violet-800/40 dark:from-violet-950/50 dark:via-violet-950/25 dark:to-transparent',
    rose: 'border-rose-200/60 bg-gradient-to-br from-rose-50/95 via-rose-50/50 to-rose-50/10 dark:border-rose-800/40 dark:from-rose-950/50 dark:via-rose-950/25 dark:to-transparent',
    slate:
        'border-border/70 bg-gradient-to-br from-muted/80 via-muted/40 to-transparent dark:from-muted/30 dark:via-muted/15',
};

const iconAccent: Record<ChartAccent, string> = {
    brand: 'bg-brand-100 text-brand-700 ring-1 ring-brand-200/60 dark:bg-brand-900/50 dark:text-brand-200',
    sky: 'bg-sky-100 text-sky-700 ring-1 ring-sky-200/50 dark:bg-sky-900/40 dark:text-sky-200',
    emerald: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200/50 dark:bg-emerald-900/40 dark:text-emerald-200',
    amber: 'bg-amber-100 text-amber-800 ring-1 ring-amber-200/50 dark:bg-amber-900/40 dark:text-amber-200',
    violet: 'bg-violet-100 text-violet-700 ring-1 ring-violet-200/50 dark:bg-violet-900/40 dark:text-violet-200',
    rose: 'bg-rose-100 text-rose-700 ring-1 ring-rose-200/50 dark:bg-rose-900/40 dark:text-rose-200',
    slate: 'bg-muted text-muted-foreground ring-1 ring-border/50',
};

type Props = {
    title: string;
    description?: string;
    icon: LucideIcon;
    accent?: KpiAccent;
    children: ReactNode;
    className?: string;
};

export function DashboardChartCard({
    title,
    description,
    icon: Icon,
    accent = 'brand',
    children,
    className,
}: Props) {
    return (
        <Card
            className={cn(
                'min-w-0 gap-0 overflow-hidden border-border/80 py-0 shadow-sm transition-shadow hover:shadow-md',
                className,
            )}
        >
            <CardHeader className={cn('border-b pb-4 pt-5', headerAccent[accent])}>
                <div className="flex items-start gap-3">
                    <div
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            iconAccent[accent],
                        )}
                    >
                        <Icon className="size-4" aria-hidden />
                    </div>
                    <div className="min-w-0">
                        <CardTitle className="text-base font-semibold">{title}</CardTitle>
                        {description !== undefined && description !== '' && (
                            <CardDescription className="mt-0.5">{description}</CardDescription>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="min-w-0 bg-muted/20 px-6 pb-6 pt-4">{children}</CardContent>
        </Card>
    );
}
