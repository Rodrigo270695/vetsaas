import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type PosPanelProps = {
    title: string;
    description?: string;
    icon: LucideIcon;
    badge?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
    /** Cabecera y padding reducidos; oculta la descripción. */
    compact?: boolean;
};

/**
 * Panel del punto de venta. Modo `compact` para POS: menos altura fija,
 * cabecera en una línea y contenido denso.
 */
export function PosPanel({
    title,
    description,
    icon: Icon,
    badge,
    children,
    className,
    contentClassName,
    compact = false,
}: PosPanelProps) {
    return (
        <section
            className={cn(
                'flex flex-col overflow-hidden rounded-lg border border-border/60 bg-card shadow-xs ring-1 ring-border/10',
                !compact && 'min-h-[220px] h-full',
                className,
            )}
        >
            <header
                className={cn(
                    'flex shrink-0 items-center justify-between gap-2 border-b border-border/50 bg-muted/20',
                    compact ? 'px-3 py-2' : 'px-4 py-3.5 sm:px-5',
                )}
            >
                <div className="flex min-w-0 items-center gap-2">
                    <span
                        className={cn(
                            'flex shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary ring-1 ring-primary/15',
                            compact ? 'size-7' : 'size-9 rounded-lg',
                        )}
                    >
                        <Icon className={compact ? 'size-3.5' : 'size-4.5'} strokeWidth={2.25} aria-hidden />
                    </span>
                    <div className="min-w-0">
                        <h2
                            className={cn(
                                'truncate font-semibold tracking-tight text-foreground',
                                compact ? 'text-xs uppercase tracking-wide text-muted-foreground' : 'text-sm',
                            )}
                        >
                            {title}
                        </h2>
                        {!compact && description ? (
                            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">{description}</p>
                        ) : null}
                    </div>
                </div>
                {badge ? <div className="shrink-0">{badge}</div> : null}
            </header>
            <div
                className={cn(
                    'flex flex-1 flex-col min-h-0',
                    compact ? 'gap-2.5 p-3' : 'gap-4 p-4 sm:px-5 sm:py-4',
                    contentClassName,
                )}
            >
                {children}
            </div>
        </section>
    );
}
