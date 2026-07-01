import { Link, router } from '@inertiajs/react';
import {
    Ban,
    Bot,
    CalendarPlus,
    Lock,
    MessageCircle,
    MoreHorizontal,
    Send,
    Pencil,
    Receipt,
    Repeat,
    Trash2,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import cobros from '@/routes/plataforma/cobros';
import type { Subscription } from '../types';

export type SubscriptionRowActionsProps = {
    subscription: Subscription;
    onEdit: (s: Subscription) => void;
    onExtendTrial: (s: Subscription) => void;
    onChangePlan: (s: Subscription) => void;
    onCancel: (s: Subscription) => void;
    onDelete: (s: Subscription) => void;
    onRenewalPreview?: (s: Subscription) => void;
    onRenewalSend?: (s: Subscription) => void;
    canUpdate?: boolean;
    canViewRenewalPreview?: boolean;
    canSendRenewalWhatsApp?: boolean;
    canDelete?: boolean;
    canExtendTrial?: boolean;
    canChangePlan?: boolean;
    canCancel?: boolean;
    canToggleBotIa?: boolean;
};

/**
 * Dropdown de acciones por fila para suscripciones.
 *
 * Lógica de visibilidad:
 *  - Editar      → si hay `update` y NO está cancelada.
 *  - Extender trial → si hay `extend-trial` y NO está cancelada.
 *  - Cambiar plan   → si hay `change-plan` y NO está cancelada.
 *  - Cancelar       → si hay `cancel` y NO está cancelada.
 *  - Eliminar       → si hay `delete` y la suscripción está cancelada
 *                       (defensa en UI: el backend rechaza si no).
 *  - Si la suscripción está cancelada, mostramos un item informativo.
 */
export function SubscriptionRowActions({
    subscription,
    onEdit,
    onExtendTrial,
    onChangePlan,
    onCancel,
    onDelete,
    onRenewalPreview,
    onRenewalSend,
    canUpdate = true,
    canViewRenewalPreview = true,
    canSendRenewalWhatsApp = true,
    canDelete = true,
    canExtendTrial = true,
    canChangePlan = true,
    canCancel = true,
    canToggleBotIa = true,
}: SubscriptionRowActionsProps) {
    const { t } = useTranslation(['suscripciones', 'common']);
    const { can } = usePermission();
    const canViewPayments = can('plataforma-cobros.view');

    const isCancelled = subscription.estado === 'cancelled';
    const showEdit = canUpdate && !isCancelled;
    const showExtendTrial = canExtendTrial && !isCancelled;
    const showChangePlan = canChangePlan && !isCancelled;
    const showCancel = canCancel && !isCancelled;
    const showDelete = canDelete && isCancelled;
    const showRenewalPreview =
        canViewRenewalPreview && !isCancelled && onRenewalPreview !== undefined;
    const showRenewalSend =
        canSendRenewalWhatsApp && !isCancelled && onRenewalSend !== undefined;
    const showToggleBotIa = canToggleBotIa && !isCancelled;
    const botIaActive = subscription.bot_ia_activo === true;

    const handleToggleBotIa = () => {
        const next = !botIaActive;
        const message = next
            ? t('suscripciones:row.bot_ia_activate_confirm', {
                  name:
                      subscription.tenant?.razon_social ??
                      subscription.tenant?.slug ??
                      '',
              })
            : t('suscripciones:row.bot_ia_deactivate_confirm', {
                  name:
                      subscription.tenant?.razon_social ??
                      subscription.tenant?.slug ??
                      '',
              });

        if (!window.confirm(message)) {
            return;
        }

        router.post(
            `/plataforma/suscripciones/${subscription.id}/toggle-bot-ia`,
            { activo: next },
            { preserveScroll: true },
        );
    };

    // Link al historial de cobros filtrado por esta suscripción.
    // El filtro `subscription_id` lo lee el SubscriptionPaymentController.
    const paymentsHref =
        cobros.index().url +
        '?subscription_id=' +
        encodeURIComponent(subscription.id);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('suscripciones:row.actions_for', {
                        name:
                            subscription.tenant?.razon_social ??
                            subscription.tenant?.slug ??
                            '',
                    })}
                    className="size-8 cursor-pointer"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                {canViewPayments && (
                    <DropdownMenuItem asChild className="cursor-pointer gap-2">
                        <Link href={paymentsHref}>
                            <Receipt className="size-4" strokeWidth={2.25} />
                            {t('suscripciones:row.view_payments')}
                        </Link>
                    </DropdownMenuItem>
                )}

                {showRenewalPreview && (
                    <DropdownMenuItem
                        onSelect={() => onRenewalPreview(subscription)}
                        className="cursor-pointer gap-2 text-emerald-700 focus:text-emerald-700 dark:text-emerald-400"
                    >
                        <MessageCircle className="size-4" strokeWidth={2.25} />
                        {t('suscripciones:row.renewal_preview')}
                    </DropdownMenuItem>
                )}

                {showRenewalSend && (
                    <DropdownMenuItem
                        onSelect={() => onRenewalSend(subscription)}
                        className="cursor-pointer gap-2 text-emerald-700 focus:text-emerald-700 dark:text-emerald-400"
                    >
                        <Send className="size-4" strokeWidth={2.25} />
                        {t('suscripciones:row.renewal_send')}
                    </DropdownMenuItem>
                )}

                {showEdit && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(subscription)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}

                {showExtendTrial && (
                    <DropdownMenuItem
                        onSelect={() => onExtendTrial(subscription)}
                        className="cursor-pointer gap-2 text-sky-700 focus:text-sky-700 dark:text-sky-400"
                    >
                        <CalendarPlus
                            className="size-4"
                            strokeWidth={2.25}
                        />
                        {t('suscripciones:row.extend_trial')}
                    </DropdownMenuItem>
                )}

                {showChangePlan && (
                    <DropdownMenuItem
                        onSelect={() => onChangePlan(subscription)}
                        className="cursor-pointer gap-2 text-primary focus:text-primary"
                    >
                        <Repeat className="size-4" strokeWidth={2.25} />
                        {t('suscripciones:row.change_plan')}
                    </DropdownMenuItem>
                )}

                {showToggleBotIa && (
                    <DropdownMenuItem
                        onSelect={handleToggleBotIa}
                        className={cn(
                            'cursor-pointer gap-2',
                            botIaActive
                                ? 'text-amber-700 focus:text-amber-700 dark:text-amber-400'
                                : 'text-violet-700 focus:text-violet-700 dark:text-violet-400',
                        )}
                    >
                        <Bot className="size-4" strokeWidth={2.25} />
                        {botIaActive
                            ? t('suscripciones:row.bot_ia_deactivate')
                            : t('suscripciones:row.bot_ia_activate')}
                    </DropdownMenuItem>
                )}

                {(showEdit ||
                    showExtendTrial ||
                    showChangePlan) &&
                    (showCancel || showDelete) && <DropdownMenuSeparator />}

                {showCancel && (
                    <DropdownMenuItem
                        onSelect={() => onCancel(subscription)}
                        className="cursor-pointer gap-2 text-amber-700 focus:text-amber-700 dark:text-amber-400"
                    >
                        <Ban className="size-4" strokeWidth={2.25} />
                        {t('suscripciones:row.cancel')}
                    </DropdownMenuItem>
                )}

                {showDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(subscription)}
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}

                {isCancelled && !showDelete && (
                    <DropdownMenuItem
                        disabled
                        className="gap-2 text-xs text-muted-foreground"
                    >
                        <Lock className="size-3.5" strokeWidth={2.25} />
                        {t('suscripciones:row.cancelled_locked')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
