import { CheckCircle2, Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type AuthStatusProps = {
    variant?: 'success' | 'info';
    children: ReactNode;
};

/**
 * Banner informativo usado en pantallas de auth (verde para éxito, azul para info).
 * Aparece encima del formulario para confirmar el resultado de una acción previa
 * (ej. "se envió el correo de recuperación").
 */
export default function AuthStatus({
    variant = 'success',
    children,
}: AuthStatusProps) {
    const Icon = variant === 'success' ? CheckCircle2 : Info;

    return (
        <div
            role="status"
            className={cn(
                'mb-6 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm font-medium backdrop-blur',
                variant === 'success' &&
                    'border-success/20 bg-success/10 text-success',
                variant === 'info' && 'border-info/20 bg-info/10 text-info',
            )}
        >
            <Icon
                aria-hidden="true"
                strokeWidth={2.25}
                className="mt-0.5 size-4 shrink-0"
            />
            <span className="text-pretty">{children}</span>
        </div>
    );
}
