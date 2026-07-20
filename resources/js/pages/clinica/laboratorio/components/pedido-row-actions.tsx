import { MessageCircle, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PedidoLaboratorioRow } from '../types';
import { pedidoDocumentCount } from '../types';

export type PedidoRowActionsProps = {
    pedido: PedidoLaboratorioRow;
    onEdit: (p: PedidoLaboratorioRow) => void;
    onDelete: (p: PedidoLaboratorioRow) => void;
    onWhatsApp: (p: PedidoLaboratorioRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canWhatsApp: boolean;
};

export function PedidoRowActions({
    pedido,
    onEdit,
    onDelete,
    onWhatsApp,
    canUpdate,
    canDelete,
    canWhatsApp,
}: PedidoRowActionsProps) {
    const { t } = useTranslation(['laboratorio', 'common']);
    const hasDocs = pedidoDocumentCount(pedido) > 0;
    const showWhatsApp = canWhatsApp && hasDocs;

    if (!canUpdate && !canDelete && !showWhatsApp) {
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
                {canUpdate ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2"
                        onClick={() => onEdit(pedido)}
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {showWhatsApp ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-emerald-600 focus:text-emerald-600 dark:text-emerald-400 dark:focus:text-emerald-400"
                        onClick={() => onWhatsApp(pedido)}
                    >
                        <MessageCircle className="size-4 text-emerald-600 dark:text-emerald-400" strokeWidth={2.25} />
                        {t('actions.send_whatsapp')}
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
