import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type QuickActionItem = {
    key: string;
    label: string;
    href: string;
    icon: LucideIcon;
    accent: 'brand' | 'sky' | 'emerald' | 'amber' | 'violet';
};

const tileStyles: Record<QuickActionItem['accent'], string> = {
    brand: 'border-brand-200/60 bg-brand-50/50 hover:bg-brand-50 hover:border-brand-300/60 dark:bg-brand-950/20 dark:hover:bg-brand-950/40',
    sky: 'border-sky-200/50 bg-sky-50/40 hover:bg-sky-50 dark:bg-sky-950/20',
    emerald: 'border-emerald-200/50 bg-emerald-50/40 hover:bg-emerald-50 dark:bg-emerald-950/20',
    amber: 'border-amber-200/50 bg-amber-50/40 hover:bg-amber-50 dark:bg-amber-950/20',
    violet: 'border-violet-200/50 bg-violet-50/40 hover:bg-violet-50 dark:bg-violet-950/20',
};

const iconStyles: Record<QuickActionItem['accent'], string> = {
    brand: 'bg-brand-100 text-brand-700',
    sky: 'bg-sky-100 text-sky-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    amber: 'bg-amber-100 text-amber-800',
    violet: 'bg-violet-100 text-violet-700',
};

type Props = {
    title: string;
    items: QuickActionItem[];
};

export function DashboardQuickActions({ title, items }: Props) {
    if (items.length === 0) {
        return null;
    }

    return (
        <Card className="min-w-0 border-border/80 shadow-sm">
            <CardHeader className="border-b border-border/50 pb-4">
                <CardTitle className="text-base font-semibold">{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-2 pt-4">
                {items.map((item) => {
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.key}
                            href={item.href}
                            className={cn(
                                'flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm font-medium text-foreground transition-colors',
                                tileStyles[item.accent],
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                    iconStyles[item.accent],
                                )}
                            >
                                <Icon className="size-4" aria-hidden />
                            </span>
                            <span className="truncate">{item.label}</span>
                        </Link>
                    );
                })}
            </CardContent>
        </Card>
    );
}
