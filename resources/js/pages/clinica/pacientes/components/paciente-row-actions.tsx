import { Link } from '@inertiajs/react';
import { ClipboardList, ExternalLink, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import clinica from '@/routes/clinica';
import type { Paciente } from '../../propietarios/types';

export type PacienteRowActionsProps = {
    paciente: Paciente;
    onEdit: (p: Paciente) => void;
    onDelete: (p: Paciente) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canDownloadCarnetVacunas?: boolean;
    carnetVacunasPdfUrl?: string;
    canViewHistorial?: boolean;
};

export function PacienteRowActions({
    paciente,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
    canDownloadCarnetVacunas = false,
    carnetVacunasPdfUrl,
    canViewHistorial = false,
}: PacienteRowActionsProps) {
    const { t } = useTranslation(['pacientes', 'common']);

    if (!canUpdate && !canDelete && !canDownloadCarnetVacunas && !canViewHistorial) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 cursor-pointer text-muted-foreground"
                    aria-label={t('columns.acciones')}
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                {canViewHistorial ? (
                    <DropdownMenuItem asChild className="cursor-pointer p-0">
                        <Link
                            href={clinica.pacientes.show.url({ paciente: paciente.id })}
                            className="flex cursor-pointer items-center gap-2 px-2 py-1.5 text-sm"
                        >
                            <ClipboardList className="size-4" strokeWidth={2.25} />
                            {t('actions.open_historial')}
                        </Link>
                    </DropdownMenuItem>
                ) : null}
                {canDownloadCarnetVacunas && carnetVacunasPdfUrl ? (
                    <DropdownMenuItem asChild className="cursor-pointer p-0">
                        <a
                            href={carnetVacunasPdfUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex cursor-pointer items-center gap-2 px-2 py-1.5 text-sm"
                        >
                            <ExternalLink className="size-4" strokeWidth={2.25} />
                            {t('actions.open_carnet_vacunas')}
                        </a>
                    </DropdownMenuItem>
                ) : null}
                {canUpdate && (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onClick={() => onEdit(paciente)}
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}
                {canDelete && (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onClick={() => onDelete(paciente)}
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
