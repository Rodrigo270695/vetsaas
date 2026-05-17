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
};

/**
 * Panel del punto de venta: altura uniforme en grillas, cabecera con icono
 * y contenido que puede estirarse (`flex-1`) para alinear columnas vecinas.
 */
export function PosPanel({
    title,
    description,
    icon: Icon,
    badge,
    children,
    className,
    contentClassName,
}: PosPanelProps) {
    return (
        <section
            className={cn(
                'flex h-full min-h-[220px] flex-col overflow-hidden rounded-xl border border-border/60 bg-card shadow-xs ring-1 ring-border/15',
                className,
            )}
        >
            <header className="flex shrink-0 items-start justify-between gap-3 border-b border-border/50 bg-muted/25 px-4 py-3.5 sm:px-5">
                <div className="flex min-w-0 items-start gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/15">
                        <Icon className="size-4.5" strokeWidth={2.25} aria-hidden />
                    </span>
                    <div className="min-w-0">
                        <h2 className="text-sm font-semibold tracking-tight text-foreground">{title}</h2>
                        {description ? (
                            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">{description}</p>
                        ) : null}
                    </div>
                </div>
                {badge ? <div className="shrink-0">{badge}</div> : null}
            </header>
            <div className={cn('flex flex-1 flex-col gap-4 p-4 sm:px-5 sm:py-4', contentClassName)}>
                {children}
            </div>
        </section>
    );
}
