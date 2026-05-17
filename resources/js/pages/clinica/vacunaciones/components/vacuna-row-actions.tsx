import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { VacunaAplicadaRow } from '../types';

export type VacunaRowActionsProps = {
    vacuna: VacunaAplicadaRow;
    onEdit: (v: VacunaAplicadaRow) => void;
    onDelete: (v: VacunaAplicadaRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
};

export function VacunaRowActions({
    vacuna,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
}: VacunaRowActionsProps) {
    const { t } = useTranslation(['vacunaciones', 'common']);

    if (!canUpdate && !canDelete) {
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
            <DropdownMenuContent align="end" className="w-44">
                {canUpdate ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onClick={() => onEdit(vacuna)}
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onClick={() => onDelete(vacuna)}
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
