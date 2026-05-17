import { Ban, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { CitaRow } from '../types';

const NO_CANCEL: ReadonlySet<string> = new Set(['cancelada', 'completada']);

export type CitaRowActionsProps = {
    cita: CitaRow;
    onEdit: (c: CitaRow) => void;
    onDelete: (c: CitaRow) => void;
    onCancel: (c: CitaRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canCancel: boolean;
};

export function CitaRowActions({
    cita,
    onEdit,
    onDelete,
    onCancel,
    canUpdate,
    canDelete,
    canCancel,
}: CitaRowActionsProps) {
    const { t } = useTranslation(['citas', 'common']);
    const puedeCancelar = canCancel && !NO_CANCEL.has(cita.estado);

    if (!canUpdate && !canDelete && !puedeCancelar) {
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
            <DropdownMenuContent align="end" className="w-48">
                {canUpdate ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onClick={() => onEdit(cita)}
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {puedeCancelar ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onClick={() => onCancel(cita)}
                    >
                        <Ban className="size-4" strokeWidth={2.25} />
                        {t('actions.cancel_appointment')}
                    </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onClick={() => onDelete(cita)}
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
