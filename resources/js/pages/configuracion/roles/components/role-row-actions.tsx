import { Copy, KeyRound, Lock, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
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
import type { Role } from '../types';

export type RoleRowActionsProps = {
    role: Role;
    onEdit: (role: Role) => void;
    onDelete: (role: Role) => void;
    /** Abre el modal pequeño con el tree de permisos. */
    onManagePermissions: (role: Role) => void;
    /** Si false, no se renderiza la opción "Editar". */
    canUpdate?: boolean;
    /** Si false, no se renderiza la opción "Eliminar". */
    canDelete?: boolean;
};

/**
 * Dropdown de acciones por fila para roles.
 *
 * Opciones:
 *  - "Copiar nombre" → siempre disponible.
 *  - "Gestionar permisos" → abre modal compacto con tree-view.
 *    Permite asignar permisos sin tener que editar metadata del rol.
 *  - "Editar" / "Eliminar" → solo en roles personalizados y con permisos.
 *  - Roles del sistema muestran un item informativo "Bloqueado".
 */
export function RoleRowActions({
    role,
    onEdit,
    onDelete,
    onManagePermissions,
    canUpdate = true,
    canDelete = true,
}: RoleRowActionsProps) {
    const { t } = useTranslation(['roles', 'common']);

    const showEdit = canUpdate && !role.is_system;
    const showDelete = canDelete && !role.is_system;
    // Para roles del sistema mostramos igual la opción pero como
    // "Ver permisos" (modo solo lectura). Así el usuario entiende qué
    // tiene asignado el superadmin sin pelearse con un menú oculto.
    const showPermissions = canUpdate;

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(role.name);
            toastManager.success({
                title: t('roles:toast.name_copied'),
                description: role.name,
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
                    aria-label={t('roles:row.actions_for', { name: role.name })}
                    className="size-8 cursor-pointer"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuItem
                    onSelect={handleCopy}
                    className="cursor-pointer gap-2"
                >
                    <Copy className="size-4" strokeWidth={2.25} />
                    {t('roles:row.copy_name')}
                </DropdownMenuItem>

                {showPermissions && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => onManagePermissions(role)}
                            className="cursor-pointer gap-2 text-primary focus:text-primary"
                        >
                            <KeyRound className="size-4" strokeWidth={2.25} />
                            {t('roles:row.manage_permissions')}
                        </DropdownMenuItem>
                    </>
                )}

                {role.is_system && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled
                            className="gap-2 text-xs text-muted-foreground"
                        >
                            <Lock className="size-3.5" strokeWidth={2.25} />
                            {t('roles:row.system_locked')}
                        </DropdownMenuItem>
                    </>
                )}

                {showEdit && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(role)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}

                {showDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(role)}
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
