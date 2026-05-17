import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type BulkActionBarProps = {
    /** Cantidad de filas seleccionadas. */
    count: number;
    /**
     * Etiqueta en singular y plural para acompañar al badge numérico.
     *
     * IMPORTANTE: NO incluyas el `{{count}}` o el número en estos strings:
     * la barra ya renderiza el contador en su propio badge a la izquierda.
     * Pasa solo el sustantivo + estado, e.g.:
     *
     *     singular: "tenant seleccionado"
     *     plural:   "tenants seleccionados"
     *
     * El render final queda: `[ 3 ]  tenants seleccionados`.
     */
    labels?: {
        singular: string;
        plural: string;
    };
    /** Callback para limpiar selección (X button). */
    onClear: () => void;
    /** Acciones específicas del módulo (botones). */
    children: ReactNode;
    className?: string;
};

/**
 * Barra flotante que aparece cuando hay filas seleccionadas en una tabla.
 *
 * - Animación de entrada (slide-up + fade) gracias a `data-[state]`.
 * - Layout responsive: en mobile ocupa todo el ancho, en desktop se
 *   centra abajo.
 * - Accesible: `role="region"` con label vivo.
 */
export function BulkActionBar({
    count,
    labels = { singular: 'seleccionada', plural: 'seleccionadas' },
    onClear,
    children,
    className,
}: BulkActionBarProps) {
    if (count === 0) {
        return null;
    }

    return (
        <div
            role="region"
            aria-label="Acciones para filas seleccionadas"
            className={cn(
                'fixed inset-x-3 bottom-3 z-40 mx-auto flex max-w-2xl items-center gap-2 rounded-2xl border border-border/70 bg-card/95 p-2 shadow-xl ring-1 ring-primary/10 backdrop-blur-md',
                'animate-in fade-in slide-in-from-bottom-4 duration-300',
                'sm:bottom-6',
                className,
            )}
        >
            <button
                type="button"
                onClick={onClear}
                aria-label="Limpiar selección"
                className="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            >
                <X className="size-4" strokeWidth={2.25} />
            </button>

            <div className="flex flex-1 items-center gap-2 px-1">
                <span className="inline-flex h-7 min-w-7 items-center justify-center rounded-md bg-primary/10 px-2 text-xs font-semibold tabular-nums text-primary">
                    {count}
                </span>
                <span className="text-xs font-medium text-foreground sm:text-sm">
                    {count === 1 ? labels.singular : labels.plural}
                </span>
            </div>

            <div className="flex shrink-0 items-center gap-1.5">{children}</div>
        </div>
    );
}

/**
 * Versión "default" de un botón dentro de la BulkActionBar para mantener
 * consistencia visual entre módulos. Acepta todas las props de Button.
 */
export const BulkAction = Button;
