import { Copy, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toastManager } from '@/lib/toast';
import type { Sede } from '../types';

export type SedeRowActionsProps = {
    sede: Sede;
    onEdit: (sede: Sede) => void;
    onDelete: (sede: Sede) => void;
    /** Si false, no se renderiza la opción "Editar". */
    canUpdate?: boolean;
    /** Si false, no se renderiza la opción "Eliminar". */
    canDelete?: boolean;
};

/**
 * Dropdown de acciones por fila: copiar código / editar / eliminar.
 *
 * - "Copiar código" siempre disponible (no requiere permisos).
 * - "Editar" / "Eliminar" se filtran según `canUpdate` / `canDelete`.
 * - Si no hay ninguna acción permitida, no renderiza el botón.
 */
export function SedeRowActions({
    sede,
    onEdit,
    onDelete,
    canUpdate = true,
    canDelete = true,
}: SedeRowActionsProps) {
    const { t } = useTranslation(['sedes', 'common']);

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(sede.codigo);
            toastManager.success({
                title: t('sedes:toast.code_copied'),
                description: sede.codigo,
                duration: 2000,
            });
        } catch {
            toastManager.error({
                title: t('common:feedback.copy_error'),
            });
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('sedes:row.actions_for', { name: sede.nombre })}
                    className="size-8 cursor-pointer"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem
                    onSelect={handleCopy}
                    className="cursor-pointer gap-2"
                >
                    <Copy className="size-4" strokeWidth={2.25} />
                    {t('sedes:row.copy_code')}
                </DropdownMenuItem>

                {(canUpdate || canDelete) && <DropdownMenuSeparator />}

                {canUpdate && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(sede)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}

                {canDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(sede)}
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
