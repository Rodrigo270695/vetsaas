import { Copy, FileStack, Lock, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
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
import type { User } from '../types';

export type UserRowActionsProps = {
    user: User;
    /** ID del usuario autenticado, para mostrar lock en su propia fila. */
    currentUserId: string | null;
    onEdit: (user: User) => void;
    onDocuments: (user: User) => void;
    onDelete: (user: User) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
};

/**
 * Dropdown de acciones por fila para usuarios.
 *
 * Opciones:
 *  - "Copiar email" → siempre.
 *  - "Editar" / "Documentos / CV" / "Eliminar" → según permiso.
 */
export function UserRowActions({
    user,
    currentUserId,
    onEdit,
    onDocuments,
    onDelete,
    canUpdate = true,
    canDelete = true,
}: UserRowActionsProps) {
    const { t } = useTranslation(['usuarios', 'common']);

    const isSelf = currentUserId === user.id;
    const isSuperadmin = user.roles.some((r) => r.name === 'superadmin');
    const showEdit = canUpdate;
    const showDelete = canDelete && !isSelf && !isSuperadmin;

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(user.email);
            toastManager.success({
                title: t('usuarios:toast.email_copied'),
                description: user.email,
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
                    aria-label={t('usuarios:row.actions_for', { name: user.name })}
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
                    {t('usuarios:row.copy_email')}
                </DropdownMenuItem>

                {(showEdit || showDelete) && <DropdownMenuSeparator />}

                {showEdit && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(user)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}

                {showEdit && (
                    <DropdownMenuItem
                        onSelect={() => onDocuments(user)}
                        className="cursor-pointer gap-2"
                    >
                        <FileStack className="size-4" strokeWidth={2.25} />
                        {t('usuarios:row.documents')}
                    </DropdownMenuItem>
                )}

                {showDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(user)}
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}

                {(isSelf || isSuperadmin) && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled
                            className="gap-2 text-xs text-muted-foreground"
                        >
                            <Lock className="size-3.5" strokeWidth={2.25} />
                            {isSelf
                                ? t('usuarios:row.self_locked')
                                : t('usuarios:row.superadmin_locked')}
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
