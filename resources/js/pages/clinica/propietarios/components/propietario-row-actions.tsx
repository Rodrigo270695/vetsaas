import { Link } from '@inertiajs/react';
import { ChevronRight, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import propietarios from '@/routes/clinica/propietarios';
import type { Propietario } from '../types';

export type PropietarioRowActionsProps = {
    propietario: Propietario;
    onEdit: (p: Propietario) => void;
    onDelete: (p: Propietario) => void;
    canUpdate: boolean;
    canDelete: boolean;
};

export function PropietarioRowActions({
    propietario,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
}: PropietarioRowActionsProps) {
    const { t } = useTranslation(['propietarios', 'common']);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={t('columns.acciones')}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem asChild>
                    <Link
                        href={propietarios.show(propietario.id).url}
                        className="flex cursor-pointer items-center gap-2"
                    >
                        <ChevronRight className="size-4" />
                        {t('actions.view_pets')}
                    </Link>
                </DropdownMenuItem>
                {canUpdate && (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onSelect={() => onEdit(propietario)}
                    >
                        <Pencil className="size-4" />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}
                {canDelete && (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onSelect={() => onDelete(propietario)}
                    >
                        <Trash2 className="size-4" />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
