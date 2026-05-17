import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type EmptyStateProps = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
};

/**
 * Estado vacío estandarizado: icono circular, título, descripción opcional y acción.
 * Pensado para usarse dentro del `emptyState` prop de DataTable, o como bloque suelto.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 px-4 py-10 text-center',
                className,
            )}
        >
            {Icon && (
                <div className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Icon className="size-6" strokeWidth={2} />
                </div>
            )}

            <h3 className="text-sm font-semibold text-foreground">{title}</h3>

            {description && (
                <p className="max-w-sm text-xs text-muted-foreground sm:text-sm">
                    {description}
                </p>
            )}

            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
