import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import clinica from '@/routes/clinica';

type Props = {
    pacienteId?: string | null;
    children: ReactNode;
    className?: string;
};

export function PacienteHcLink({ pacienteId, children, className }: Props) {
    const { can } = usePermission();
    const id = pacienteId?.trim() ?? '';
    const canOpen = id !== '' && can('pacientes.view');

    if (!canOpen) {
        return <span className={cn('text-foreground', className)}>{children}</span>;
    }

    return (
        <Link
            href={clinica.pacientes.show.url({ paciente: id })}
            className={cn('font-medium text-primary underline-offset-4 hover:underline', className)}
            onClick={(event) => event.stopPropagation()}
        >
            {children}
        </Link>
    );
}
