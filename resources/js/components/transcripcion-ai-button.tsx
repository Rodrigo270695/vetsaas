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
                'inline-flex h-10 shrink-0 cursor-pointer items-center gap-1.5 rounded-full border px-1 pr-3.5 text-sm font-medium',
                'border-slate-200/90 bg-white text-slate-800 shadow-sm',
                'transition-[background-color,border-color,box-shadow] duration-200',
                'hover:border-slate-300 hover:bg-slate-50 hover:shadow',
                'dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-slate-600 dark:hover:bg-slate-800',
                'active:scale-[0.98]',
                'focus-visible:ring-2 focus-visible:ring-slate-400/70 focus-visible:ring-offset-2 focus-visible:outline-none',
                'disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <span className="flex size-8 shrink-0 items-center justify-center" aria-hidden>
                <ThinkingOrb state="composing" size={32} className="block" />
            </span>
            <span className="whitespace-nowrap">{children}</span>
        </button>
    );
}
