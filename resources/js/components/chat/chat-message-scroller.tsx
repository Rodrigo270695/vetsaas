import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ChatMessageScrollerProps = {
    children: ReactNode;
    className?: string;
    contentClassName?: string;
};

/**
 * Área de mensajes anclada abajo (estilo WhatsApp): el hilo crece hacia arriba.
 */
export function ChatMessageScroller({
    children,
    className,
    contentClassName,
}: ChatMessageScrollerProps) {
    return (
        <div
            className={cn(
                'min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-4 sm:px-4',
                className,
            )}
        >
            <div
                className={cn(
                    'flex min-h-full flex-col justify-end gap-2.5',
                    contentClassName,
                )}
            >
                {children}
            </div>
        </div>
    );
}
