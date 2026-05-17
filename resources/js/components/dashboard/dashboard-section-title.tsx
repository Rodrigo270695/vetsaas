import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type Accent = 'brand' | 'sky' | 'amber' | 'violet' | 'emerald';

const accentBar: Record<Accent, string> = {
    brand: 'bg-brand-500',
    sky: 'bg-sky-500',
    amber: 'bg-amber-500',
    violet: 'bg-violet-500',
    emerald: 'bg-emerald-500',
};

type Props = {
    title: string;
    description?: string;
    icon?: LucideIcon;
    accent?: Accent;
    className?: string;
};

export function DashboardSectionTitle({
    title,
    description,
    icon: Icon,
    accent = 'brand',
    className,
}: Props) {
    return (
        <div className={cn('flex items-start gap-3', className)}>
            <span className={cn('mt-1.5 h-6 w-1 shrink-0 rounded-full', accentBar[accent])} aria-hidden />
            <div className="min-w-0">
                <div className="flex items-center gap-2">
                    {Icon !== undefined && (
                        <Icon className="size-4 text-muted-foreground" aria-hidden />
                    )}
                    <h2 className="text-sm font-semibold tracking-tight text-foreground">{title}</h2>
                </div>
                {description !== undefined && description !== '' && (
                    <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
                )}
            </div>
        </div>
    );
}
