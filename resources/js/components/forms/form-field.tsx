import { AlertCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type FormFieldProps = {
    /** ID del input al que se asocia el label (htmlFor). */
    id: string;
    label: string;
    /** Clases extra para el `<Label>` (p. ej. tamaño compacto en tablas). */
    labelClassName?: string;
    /** Mensaje de error a mostrar; activa estado visual de error. */
    error?: string;
    /** Texto auxiliar mostrado debajo del input. */
    hint?: string;
    required?: boolean;
    /** El input/select/textarea. */
    children: ReactNode;
    className?: string;
};

/**
 * Wrapper de campo de formulario: label + control + error/hint.
 *
 * Pasa el `id` al child manualmente, o usa el patrón cloneElement si lo prefieres.
 * Por ahora el child es responsable de aceptar el `id` propagado por accesibilidad.
 */
export function FormField({
    id,
    label,
    labelClassName,
    error,
    hint,
    required,
    children,
    className,
}: FormFieldProps) {
    const hasError = Boolean(error);

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <Label
                htmlFor={id}
                className={cn(
                    'text-sm font-medium',
                    labelClassName,
                    hasError && 'text-destructive',
                )}
            >
                {label}
                {required && (
                    <span aria-hidden="true" className="ml-0.5 text-destructive">
                        *
                    </span>
                )}
            </Label>

            {children}

            {hasError ? (
                <p
                    role="alert"
                    className="flex items-start gap-1 text-xs text-destructive"
                >
                    <AlertCircle
                        className="mt-0.5 size-3 shrink-0"
                        strokeWidth={2.5}
                    />
                    <span>{error}</span>
                </p>
            ) : hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
        </div>
    );
}
