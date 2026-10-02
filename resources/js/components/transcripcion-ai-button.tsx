import { Mic, Sparkles } from 'lucide-react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'> & {
    children: ReactNode;
};

/**
 * Pastilla de transcripción por voz. Verde de marca, para reutilizar
 * en consulta, desparasitación, antipulgas y cualquier ficha que dicte.
 */
export function TranscripcionAiButton({ children, className, type = 'button', ...props }: Props) {
    return (
        <button
            type={type}
            className={cn(
                'inline-flex h-9 shrink-0 cursor-pointer items-center gap-2 rounded-full px-3.5 text-sm font-semibold text-white',
                'bg-gradient-to-r from-brand-800 via-brand-600 to-emerald-500',
                'shadow-md shadow-brand-700/30',
                'transition-[filter,transform,box-shadow] duration-200 hover:brightness-110 hover:shadow-lg hover:shadow-brand-600/35',
                'active:scale-[0.98]',
                'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:outline-none',
                'disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <Sparkles className="size-3.5 shrink-0" strokeWidth={2.25} />
            <span className="whitespace-nowrap">{children}</span>
            <span className="flex size-6 items-center justify-center rounded-full bg-white/18">
                <Mic className="size-3.5" strokeWidth={2.25} />
            </span>
        </button>
    );
}
