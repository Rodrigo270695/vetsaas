import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type SectionCardProps = {
    title: string;
    description?: string;
    icon?: LucideIcon;
    /** Insignia opcional a la derecha del título (ej. "Configurado"). */
    badge?: ReactNode;
    children: ReactNode;
    className?: string;
};

/**
 * Tarjeta-contenedor de cada bloque del formulario de Configuración → General.
 *
 * Mantiene la misma paleta y tipografía que el resto del panel (Verde
 * Bosque Clínico) y deja al `FormSection` interno encargarse del grid de
 * campos. El icono va en un círculo translúcido al estilo `StatBadge`
 * para reforzar la asociación visual con el header de la página.
 *
 * En pantallas pequeñas el badge/CTA pasa debajo del título para no
 * truncar textos ni botones.
 */
export function SectionCard({
    title,
    description,
    icon: Icon,
    badge,
    children,
    className,
}: SectionCardProps) {
    return (
        <Card
            className={cn(
                'relative min-w-0 gap-3 overflow-hidden border-border/60 bg-card/75 py-4 shadow-sm ring-1 ring-border/20 backdrop-blur-sm transition-shadow before:absolute before:inset-x-0 before:top-0 before:h-0.5 before:bg-linear-to-r before:from-primary/80 before:via-emerald-400/50 before:to-cyan-400/30 hover:shadow-md',
                className,
            )}
        >
            <CardHeader className="flex flex-col gap-2 px-4 sm:flex-row sm:items-start sm:justify-between sm:gap-3 sm:px-5">
                <div className="flex min-w-0 flex-1 items-start gap-3">
                    {Icon && (
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-linear-to-br from-primary/18 to-emerald-400/10 text-primary shadow-sm ring-1 ring-primary/15">
                            <Icon className="size-4.5" strokeWidth={2.25} />
                        </span>
                    )}
                    <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                        <CardTitle className="text-base font-semibold tracking-tight">
                            {title}
                        </CardTitle>
                        {description && (
                            <CardDescription className="text-xs leading-relaxed wrap-break-word">
                                {description}
                            </CardDescription>
                        )}
                    </div>
                </div>

                {badge ? (
                    <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0 sm:justify-end">
                        {badge}
                    </div>
                ) : null}
            </CardHeader>

            <CardContent className="min-w-0 px-4 sm:px-5">
                {children}
            </CardContent>
        </Card>
    );
}
