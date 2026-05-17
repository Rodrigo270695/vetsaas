import { Eye, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { InternamientoRow } from '../types';

export type InternamientoRowActionsProps = {
    internamiento: InternamientoRow;
    onEdit: (row: InternamientoRow) => void;
    onDelete: (row: InternamientoRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
};

export function InternamientoRowActions({
    internamiento,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
}: InternamientoRowActionsProps) {
    const { t } = useTranslation(['hospitalizacion', 'common']);

    const showHref = `/clinica/hospitalizacion/${internamiento.id}`;

    if (!canUpdate && !canDelete) {
        return (
            <Button type="button" variant="ghost" size="icon" className="size-8" asChild>
                <Link href={showHref} aria-label={t('actions.ver_detalle')}>
                    <Eye className="size-4" strokeWidth={2.25} />
                </Link>
            </Button>
        );
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
                <DropdownMenuItem className="cursor-pointer gap-2" asChild>
                    <Link href={showHref}>
                        <Eye className="size-4" strokeWidth={2.25} />
                        {t('actions.ver_detalle')}
                    </Link>
                </DropdownMenuItem>
                {canUpdate ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onClick={() => onEdit(internamiento)}
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onClick={() => onDelete(internamiento)}
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
