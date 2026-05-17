import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type FormSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    /** Si true, los children se acomodan en grid 2-col en desktop. */
    columns?: 1 | 2;
    /**
     * Índice opcional para crear un efecto stagger cuando varias secciones
     * aparecen juntas. Cada sección espera `index * 80ms` para animarse.
     */
    index?: number;
    className?: string;
};

/**
 * Sección de formulario con encabezado + grid configurable.
 * Útil para agrupar campos relacionados dentro de un mismo modal.
 *
 * Lleva una animación de entrada (fade + slide desde abajo) que se compone
 * con el efecto del modal padre. Si pasas `index`, las secciones se animan
 * una tras otra (stagger).
 */
export function FormSection({
    title,
    description,
    children,
    columns = 1,
    index = 0,
    className,
}: FormSectionProps) {
    return (
        <section
            style={{ animationDelay: `${index * 80}ms` }}
            className={cn(
                'animate-in fade-in slide-in-from-bottom-2 fill-mode-both flex flex-col gap-3 duration-500',
                className,
            )}
        >
            <header className="flex flex-col gap-0.5">
                <h3 className="text-sm font-semibold text-foreground">
                    {title}
                </h3>
                {description && (
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </header>

            <div
                className={cn(
                    'grid gap-4',
                    columns === 2 && 'sm:grid-cols-2 sm:gap-x-5',
                )}
            >
                {children}
            </div>
        </section>
    );
}
