import { Link } from '@inertiajs/react';
import { ClipboardList, MoreHorizontal, Pencil, Receipt, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { HotelEstanciaRow } from '../types';

export type HotelRowActionsProps = {
    estancia: HotelEstanciaRow;
    onEdit: (e: HotelEstanciaRow) => void;
    onDelete: (e: HotelEstanciaRow) => void;
    onDiarios: (e: HotelEstanciaRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canCobrar: boolean;
    canDiarios: boolean;
};

export function HotelRowActions({
    estancia,
    onEdit,
    onDelete,
    onDiarios,
    canUpdate,
    canDelete,
    canCobrar,
    canDiarios,
}: HotelRowActionsProps) {
    const { t } = useTranslation(['hotel', 'common']);

    const puedeVerCargos = canUpdate || canCobrar;
    const urlCargos = puedeVerCargos ? `/servicios/hotel/${estancia.id}/cargos` : null;

    if (!canUpdate && !canDelete && !urlCargos && !canDiarios) {
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
                {canDiarios ? (
                    <DropdownMenuItem className="cursor-pointer gap-2" onClick={() => onDiarios(estancia)}>
                        <ClipboardList className="size-4 shrink-0" strokeWidth={2.25} />
                        {t('actions.diarios')}
                    </DropdownMenuItem>
                ) : null}
                {urlCargos ? (
                    <DropdownMenuItem asChild>
                        <Link href={urlCargos} className="flex cursor-pointer items-center gap-2">
                            <Receipt className="size-4 shrink-0" strokeWidth={2.25} />
                            {t('actions.cargos')}
                        </Link>
                    </DropdownMenuItem>
                ) : null}
                {canUpdate ? (
                    <DropdownMenuItem className="cursor-pointer gap-2" onClick={() => onEdit(estancia)}>
                        <Pencil className="size-4 shrink-0" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onClick={() => onDelete(estancia)}
                    >
                        <Trash2 className="size-4 shrink-0" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
