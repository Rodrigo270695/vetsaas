import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { ThinkingOrb } from 'thinking-orbs';
import { cn } from '@/lib/utils';

type Props = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'> & {
    children: ReactNode;
};

/**
 * Pastilla de transcripción por voz. Superficie neutra para que funcione
 * en consulta, desparasitación y el resto de fichas; el orbe es el estado
 * «Thinking» de thinking-orbs (state composing).
 */
export function TranscripcionAiButton({ children, className, type = 'button', ...props }: Props) {
    return (
        <button
            type={type}
            className={cn(
                'inline-flex h-10 shrink-0 cursor-pointer items-center gap-1.5 rounded-full border border-transparent px-1 pr-3.5 text-sm font-medium text-white',
                'bg-brand-600 shadow-sm shadow-brand-700/25',
                'transition-[background-color,box-shadow] duration-200',
                'hover:bg-brand-700 hover:shadow-md hover:shadow-brand-700/30',
                'dark:bg-brand-500 dark:hover:bg-brand-600',
                'active:scale-[0.98]',
                'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:outline-none',
                'disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <span className="flex size-8 shrink-0 items-center justify-center" aria-hidden>
                <ThinkingOrb state="composing" size={32} color="#ffffff" className="block" />
            </span>
            <span className="whitespace-nowrap">{children}</span>
        </button>
    );
}
