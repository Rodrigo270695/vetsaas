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
                'gap-4 border-border/60 bg-card/60 backdrop-blur-sm ring-1 ring-border/20',
                className,
            )}
        >
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    {Icon && (
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/15">
                            <Icon className="size-4.5" strokeWidth={2.25} />
                        </span>
                    )}
                    <div className="flex flex-col gap-0.5">
                        <CardTitle className="text-base font-semibold tracking-tight">
                            {title}
                        </CardTitle>
                        {description && (
                            <CardDescription className="text-xs leading-relaxed">
                                {description}
                            </CardDescription>
                        )}
                    </div>
                </div>

                {badge && <div className="shrink-0">{badge}</div>}
            </CardHeader>

            <CardContent>{children}</CardContent>
        </Card>
    );
}
