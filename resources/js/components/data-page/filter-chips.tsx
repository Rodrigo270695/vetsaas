import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type FilterChip<TValue extends string> = {
    value: TValue;
    label: string;
    /** Conteo opcional al lado de la etiqueta (ej. "Activas (13)"). */
    count?: number;
    icon?: ReactNode;
};

export type FilterChipsProps<TValue extends string> = {
    /** Etiqueta semántica para accesibilidad. */
    ariaLabel: string;
    value: TValue;
    onChange: (value: TValue) => void;
    options: readonly FilterChip<TValue>[];
    className?: string;
};

/**
 * Grupo de "chips" segmentado tipo iOS / shadcn Tabs. Útil como filtro
 * rápido entre dos o tres estados (ej. todas/activas/inactivas).
 *
 * - Estilo segmented control: un solo background, el activo destaca.
 * - Genérico sobre el valor (string literal) para tipado fuerte.
 * - Accesible: `role="radiogroup"` y cada chip es `role="radio"` con
 *   `aria-checked`. Soporta navegación con teclado (Tab + Enter/Space).
 */
export function FilterChips<TValue extends string>({
    ariaLabel,
    value,
    onChange,
    options,
    className,
}: FilterChipsProps<TValue>) {
    return (
        <div
            role="radiogroup"
            aria-label={ariaLabel}
            className={cn(
                'flex w-full max-w-full min-w-0 items-center gap-0.5 overflow-x-auto rounded-lg border border-border/60 bg-muted/40 p-0.5 shadow-xs',
                'scrollbar-none [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden',
                className,
            )}
        >
            {options.map((opt) => {
                const isActive = opt.value === value;

                return (
                    <button
                        key={opt.value}
                        type="button"
                        role="radio"
                        aria-checked={isActive}
                        onClick={() => onChange(opt.value)}
                        className={cn(
                            'inline-flex h-7 shrink-0 cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-md px-2.5 text-xs font-medium transition-all',
                            'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            isActive
                                ? 'bg-card text-foreground shadow-sm ring-1 ring-border/60'
                                : 'text-muted-foreground hover:bg-card/50 hover:text-foreground',
                        )}
                    >
                        {opt.icon}
                        <span>{opt.label}</span>
                        {typeof opt.count === 'number' && (
                            <span
                                className={cn(
                                    'tabular-nums',
                                    isActive
                                        ? 'text-primary'
                                        : 'text-muted-foreground/70',
                                )}
                            >
                                {opt.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
