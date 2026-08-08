import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    className?: string;
};

/**
 * Contenedor de tabs deslizable con el dedo en móvil.
 * Oculta la barra de scroll; el pan horizontal nativo hace el swipe.
 */
export function ApiPeruSwipeTabs({ children, className }: Props) {
    return (
        <div
            className={cn(
                '-mx-1 overflow-x-auto overscroll-x-contain px-1 pb-1',
                'touch-pan-x [-webkit-overflow-scrolling:touch]',
                '[scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden',
                className,
            )}
        >
            {children}
        </div>
    );
}
