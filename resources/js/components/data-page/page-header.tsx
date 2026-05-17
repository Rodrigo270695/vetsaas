import type { ReactNode } from 'react';
import { StatBadge, type StatBadgeProps } from './stat-badge';
import { cn } from '@/lib/utils';

export type PageHeaderStat = Omit<StatBadgeProps, 'className'>;

export type PageHeaderProps = {
    title: string;
    description?: string;
    stats?: PageHeaderStat[];
    /** Slot a la derecha del header: botones, dropdowns, etc. */
    action?: ReactNode;
    className?: string;
};

/**
 * Cabecera de página estándar para listados / CRUDs.
 *
 * Estructura responsive:
 * - Mobile: título arriba, descripción debajo, badges en wrap, acción full-width al final.
 * - Desktop: título + descripción a la izquierda, acción a la derecha en la misma fila.
 *
 * Los badges van debajo del título y aceptan icon + label + value + variant.
 */
export function PageHeader({
    title,
    description,
    stats = [],
    action,
    className,
}: PageHeaderProps) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 border-b border-border/60 pb-5 sm:flex-row sm:items-start sm:justify-between sm:gap-6',
                className,
            )}
        >
            <div className="flex min-w-0 flex-col gap-2">
                <h1 className="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                    {title}
                </h1>

                {description && (
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        {description}
                    </p>
                )}

                {stats.length > 0 && (
                    <div className="mt-1 flex flex-wrap items-center gap-1.5">
                        {stats.map((stat) => (
                            <StatBadge key={stat.label} {...stat} />
                        ))}
                    </div>
                )}
            </div>

            {action && (
                <div className="flex shrink-0 items-center gap-2 sm:self-start">
                    {action}
                </div>
            )}
        </header>
    );
}
