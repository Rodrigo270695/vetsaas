import { Link } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2, Wallet } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { GroomingTurnoRow } from '../types';

export type GroomingRowActionsProps = {
    turno: GroomingTurnoRow;
    onEdit: (t: GroomingTurnoRow) => void;
    onDelete: (t: GroomingTurnoRow) => void;
    canUpdate: boolean;
    canDelete: boolean;
    canCobrar: boolean;
};

export function GroomingRowActions({
    turno,
    onEdit,
    onDelete,
    canUpdate,
    canDelete,
    canCobrar,
}: GroomingRowActionsProps) {
    const { t } = useTranslation(['grooming', 'common']);

    const puedeCobrar =
        canCobrar && turno.estado === 'completada' && turno.venta_id === null && Boolean(turno.paciente?.propietario);

    const urlCobrar = puedeCobrar ? `/caja/ventas/desde-grooming/${turno.id}` : null;

    if (!canUpdate && !canDelete && !urlCobrar) {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-1">
            {urlCobrar ? (
                <Button variant="default" size="sm" className="h-8 gap-1.5" asChild>
                    <Link href={urlCobrar}>
                        <Wallet className="size-3.5" aria-hidden />
                        {t('actions.cobrar')}
                    </Link>
                </Button>
            ) : null}
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
                    <DropdownMenuItem className="cursor-pointer gap-2" onClick={() => onEdit(turno)}>
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                    <DropdownMenuItem
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                        onClick={() => onDelete(turno)}
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
        </div>
    );
}
