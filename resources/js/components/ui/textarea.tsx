import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * Variante multilinea del `Input`: comparte exactamente el mismo look
 * (border, hover, focus ring, aria-invalid) para mantener consistencia
 * visual cuando un formulario mezcla campos cortos y largos.
 */
function Textarea({
    className,
    ...props
}: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'border-input file:text-foreground placeholder:text-muted-foreground/70 selection:bg-primary selection:text-primary-foreground flex min-h-20 w-full min-w-0 rounded-md border bg-card/70 px-3 py-2 text-sm shadow-xs backdrop-blur-sm transition-all outline-none hover:border-input/80 hover:bg-card/85 focus-visible:bg-card/85 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                'focus-visible:border-ring focus-visible:ring-ring/25 focus-visible:ring-2',
                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };
