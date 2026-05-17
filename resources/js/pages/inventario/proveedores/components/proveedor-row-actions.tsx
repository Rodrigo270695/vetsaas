import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ProveedorFila } from '../types';

type ProveedorRowActionsProps = {
    proveedor: ProveedorFila;
    onEdit: (p: ProveedorFila) => void;
    onDelete: (p: ProveedorFila) => void;
    canUpdate: boolean;
    canDelete: boolean;
};

export function ProveedorRowActions({ proveedor, onEdit, onDelete, canUpdate, canDelete }: ProveedorRowActionsProps) {
    const { t } = useTranslation(['proveedores-inventario', 'common']);

    if (!canUpdate && !canDelete) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="icon" className="size-8 cursor-pointer">
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                {canUpdate && (
                    <DropdownMenuItem onSelect={() => onEdit(proveedor)} className="cursor-pointer gap-2">
                        <Pencil className="size-4" />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}
                {canDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(proveedor)}
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
