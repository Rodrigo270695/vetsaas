import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { KpiAccent } from '@/components/dashboard/dashboard-kpi-grid';

type ChartAccent = KpiAccent;

const headerAccent: Record<ChartAccent, string> = {
    brand: 'from-brand-500/20 via-transparent to-transparent',
    sky: 'from-sky-500/20 via-transparent to-transparent',
    emerald: 'from-emerald-500/20 via-transparent to-transparent',
    amber: 'from-amber-500/20 via-transparent to-transparent',
    violet: 'from-violet-500/20 via-transparent to-transparent',
    rose: 'from-rose-500/15 via-transparent to-transparent',
    slate: 'from-muted/40 via-transparent to-transparent',
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
                'min-w-0 overflow-hidden border-border/80 shadow-sm transition-shadow hover:shadow-md',
                className,
            )}
        >
            <CardHeader className="relative border-b border-border/50 bg-gradient-to-r pb-4">
                <div
                    className={cn(
                        'pointer-events-none absolute inset-0 bg-gradient-to-br',
                        headerAccent[accent],
                    )}
                    aria-hidden
                />
                <div className="relative flex items-start gap-3">
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
            <CardContent className="min-w-0 bg-muted/20 pt-4">{children}</CardContent>
        </Card>
    );
}
