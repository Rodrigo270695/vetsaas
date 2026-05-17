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
import type { Plan } from '../types';

export type PlanRowActionsProps = {
    plan: Plan;
    onEdit: (plan: Plan) => void;
    onManageFeatures: (plan: Plan) => void;
    onDelete: (plan: Plan) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
};

/**
 * Dropdown de acciones por fila para planes.
 *
 * Opciones:
 *  - "Copiar código" → siempre disponible.
 *  - "Editar"        → solo si hay permiso `update`.
 *  - "Gestionar features" → solo si hay `update` (mismo permiso).
 *  - "Eliminar"      → solo si hay `delete` y el plan no tiene
 *                       suscripciones activas. Si las tiene, mostramos
 *                       un item informativo con lock.
 */
export function PlanRowActions({
    plan,
    onEdit,
    onManageFeatures,
    onDelete,
    canUpdate = true,
    canDelete = true,
}: PlanRowActionsProps) {
    const { t } = useTranslation(['planes', 'common']);

    const hasSubscriptions = plan.subscriptions_count > 0;
    const showEdit = canUpdate;
    const showFeatures = canUpdate;
    const showDelete = canDelete && !hasSubscriptions;

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(plan.codigo);
            toastManager.success({
                title: t('planes:toast.code_copied'),
                description: plan.codigo,
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
                    aria-label={t('planes:row.actions_for', {
                        name: plan.nombre,
                    })}
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
                    {t('planes:row.copy_code')}
                </DropdownMenuItem>

                {(showEdit || showFeatures || showDelete) && (
                    <DropdownMenuSeparator />
                )}

                {showEdit && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(plan)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}

                {showFeatures && (
                    <DropdownMenuItem
                        onSelect={() => onManageFeatures(plan)}
                        className="cursor-pointer gap-2 text-primary focus:text-primary"
                    >
                        <KeyRound className="size-4" strokeWidth={2.25} />
                        {t('planes:row.manage_features')}
                    </DropdownMenuItem>
                )}

                {showDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(plan)}
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}

                {hasSubscriptions && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled
                            className="gap-2 text-xs text-muted-foreground"
                        >
                            <Lock className="size-3.5" strokeWidth={2.25} />
                            {t('planes:row.has_subscriptions_locked', {
                                count: plan.subscriptions_count,
                            })}
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
