import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ChatShellProps = {
    children: ReactNode;
    className?: string;
};

/**
 * Contenedor fijo del chat: ocupa el alto del shell sin scroll de página.
 */
export function ChatShell({ children, className }: ChatShellProps) {
    return (
        <div
            data-fixed-viewport
            className={cn(
                'flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-card max-lg:rounded-none max-lg:border-0 max-lg:shadow-none lg:m-3 lg:rounded-2xl lg:border lg:border-border/60 lg:shadow-sm',
                className,
            )}
        >
            {children}
        </div>
    );
}
