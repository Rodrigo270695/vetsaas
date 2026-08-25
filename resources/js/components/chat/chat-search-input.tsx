import { Search } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type ChatSearchInputProps = Omit<ComponentProps<'input'>, 'type'> & {
    /** Clases extra del contenedor relativo. */
    containerClassName?: string;
};

/**
 * Campo de búsqueda estándar del chat: icono lupa a la izquierda + input.
 * Usar en lista de conversaciones, DM y búsqueda dentro del hilo.
 */
export function ChatSearchInput({
    className,
    containerClassName,
    ...props
}: ChatSearchInputProps) {
    return (
        <div className={cn('relative', containerClassName)}>
            <Search
                aria-hidden
                className="pointer-events-none absolute top-1/2 left-2.5 z-10 size-3.5 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                type="search"
                autoComplete="off"
                className={cn(
                    'h-9 border-border/60 bg-background/80 pr-3 pl-8 text-sm',
                    className,
                )}
                {...props}
            />
        </div>
    );
}
