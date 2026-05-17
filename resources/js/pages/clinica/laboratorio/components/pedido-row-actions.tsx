import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PedidoLaboratorioRow } from '../types';

export type PedidoRowActionsProps = {
    pedido: PedidoLaboratorioRow;
    onEdit: (p: PedidoLaboratorioRow) => void;
    onDelete: (p: PedidoLaboratorioRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
};

export function PedidoRowActions({
    pedido,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
}: PedidoRowActionsProps) {
    const { t } = useTranslation(['laboratorio', 'common']);

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
            <DropdownMenuContent align="end" className="w-48">
                {canUpdate ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onClick={() => onEdit(pedido)}
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onClick={() => onDelete(pedido)}
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
