import type { ReactNode } from 'react';
import { MessagesSquare } from 'lucide-react';
import { cn } from '@/lib/utils';

type ChatPageHeaderProps = {
    title: string;
    subtitle?: string;
    /** Ocultar en móvil cuando hay hilo abierto (igual que chat interno). */
    hideOnMobileWhenThread?: boolean;
    actions?: ReactNode;
    className?: string;
};

export function ChatPageHeader({
    title,
    subtitle,
    hideOnMobileWhenThread = false,
    actions,
    className,
}: ChatPageHeaderProps) {
    return (
        <div
            className={cn(
                'flex items-center justify-between gap-3 border-b border-border/60 bg-linear-to-r from-emerald-50/90 via-card to-teal-50/40 px-4 py-3 dark:from-emerald-950/40 dark:via-card dark:to-teal-950/20',
                hideOnMobileWhenThread && 'max-lg:hidden',
                className,
            )}
        >
            <div className="flex min-w-0 items-center gap-2.5">
                <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30">
                    <MessagesSquare className="size-4" aria-hidden />
                </span>
                <div className="min-w-0">
                    <h1 className="truncate text-base font-semibold tracking-tight">
                        {title}
                    </h1>
                    {subtitle ? (
                        <p className="truncate text-xs text-muted-foreground max-sm:hidden">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
            </div>
            {actions ? (
                <div className="flex shrink-0 items-center gap-1.5">{actions}</div>
            ) : null}
        </div>
    );
}
