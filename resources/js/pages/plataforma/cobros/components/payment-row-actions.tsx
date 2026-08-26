import {
    CalendarSync,
    Copy,
    Eye,
    FileText,
    Lock,
    MessageCircle,
    MoreHorizontal,
    Sparkles,
    StickyNote,
    Undo2,
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
import { toastManager } from '@/lib/toast';
import type { SubscriptionPayment } from '../types';

export type PaymentRowActionsProps = {
    payment: SubscriptionPayment;
    onViewDetail: (p: SubscriptionPayment) => void;
    onAddNote: (p: SubscriptionPayment) => void;
    onMarkRefunded: (p: SubscriptionPayment) => void;
    onResendInvoice: (p: SubscriptionPayment) => void;
    onSendRenewalWhatsApp?: (p: SubscriptionPayment) => void;
    onWinBackWhatsApp?: (p: SubscriptionPayment) => void;
    onManualRenew?: (p: SubscriptionPayment) => void;
    canAddNote?: boolean;
    canRefund?: boolean;
    canResend?: boolean;
    canSendRenewalWhatsApp?: boolean;
    canWinBackWhatsApp?: boolean;
    canManualRenew?: boolean;
    /** true si la suscripción viva está vencida (days < 0) o suspended. */
    isExpiredSubscription?: boolean;
};

/**
 * Dropdown de acciones por fila para cobros.
 */
export function PaymentRowActions({
    payment,
    onViewDetail,
    onAddNote,
    onMarkRefunded,
    onResendInvoice,
    onSendRenewalWhatsApp,
    onWinBackWhatsApp,
    onManualRenew,
    canAddNote = true,
    canRefund = true,
    canResend = true,
    canSendRenewalWhatsApp = false,
    canWinBackWhatsApp = false,
    canManualRenew = false,
    isExpiredSubscription = false,
}: PaymentRowActionsProps) {
    const { t } = useTranslation(['cobros', 'common']);

    const hasPaymentRecord = payment.has_payment_record !== false;
    const isSinCobro = payment.estado === 'sin_cobro' || !hasPaymentRecord;
    const isRefunded = payment.estado === 'reembolsado';
    const isFailed = payment.estado === 'fallido';
    const isPending = payment.estado === 'pendiente';
    const hasFel = payment.fel_emitido;
    const hasTxId = !!payment.pasarela_transaction_id;

    const showCopy = hasTxId;
    const showNote = canAddNote && hasPaymentRecord;
    const showResend = canResend && hasFel && hasPaymentRecord;
    const showRefund = canRefund && !isRefunded && !isFailed && !isPending && hasPaymentRecord;
    const showRenewalWhatsApp =
        canSendRenewalWhatsApp &&
        onSendRenewalWhatsApp !== undefined &&
        payment.subscription !== null &&
        payment.subscription.estado !== 'cancelled';
    const showWinBack =
        canWinBackWhatsApp &&
        onWinBackWhatsApp !== undefined &&
        isExpiredSubscription &&
        payment.subscription !== null &&
        payment.subscription.estado !== 'cancelled';
    const showManualRenew =
        canManualRenew &&
        onManualRenew !== undefined &&
        payment.subscription !== null &&
        payment.subscription.estado !== 'cancelled';

    const handleCopyTxId = async () => {
        if (!payment.pasarela_transaction_id) {
            return;
        }

        try {
            await navigator.clipboard.writeText(payment.pasarela_transaction_id);
            toastManager.success({
                title: t('cobros:toast.tx_id_copied'),
                description: payment.pasarela_transaction_id,
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
                    aria-label={t('cobros:row.actions_for', {
                        name:
                            payment.tenant?.razon_social ??
                            payment.tenant?.slug ??
                            '',
                    })}
                    className="size-8 cursor-pointer"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuItem
                    onSelect={() => onViewDetail(payment)}
                    className="cursor-pointer gap-2"
                >
                    <Eye className="size-4" strokeWidth={2.25} />
                    {t('cobros:row.view_detail')}
                </DropdownMenuItem>

                {showCopy ? (
                    <DropdownMenuItem
                        onSelect={handleCopyTxId}
                        className="cursor-pointer gap-2"
                    >
                        <Copy className="size-4" strokeWidth={2.25} />
                        {t('cobros:row.copy_tx_id')}
                    </DropdownMenuItem>
                ) : null}

                {showRenewalWhatsApp ? (
                    <DropdownMenuItem
                        onSelect={() => onSendRenewalWhatsApp(payment)}
                        className="cursor-pointer gap-2 text-emerald-700 focus:text-emerald-700 dark:text-emerald-400"
                    >
                        <MessageCircle className="size-4" strokeWidth={2.25} />
                        {t('cobros:row.send_payment_whatsapp')}
                    </DropdownMenuItem>
                ) : null}

                {showWinBack ? (
                    <DropdownMenuItem
                        onSelect={() => onWinBackWhatsApp(payment)}
                        className="cursor-pointer gap-2 text-violet-700 focus:text-violet-700 dark:text-violet-300"
                    >
                        <Sparkles className="size-4" strokeWidth={2.25} />
                        {t('cobros:row.win_back_whatsapp')}
                    </DropdownMenuItem>
                ) : null}

                {showManualRenew ? (
                    <DropdownMenuItem
                        onSelect={() => onManualRenew(payment)}
                        className="cursor-pointer gap-2 text-emerald-700 focus:text-emerald-700 dark:text-emerald-400"
                    >
                        <CalendarSync className="size-4" strokeWidth={2.25} />
                        {t('cobros:row.manual_renewal')}
                    </DropdownMenuItem>
                ) : null}

                {(showNote || showResend || showRefund) ? <DropdownMenuSeparator /> : null}

                {showNote ? (
                    <DropdownMenuItem
                        onSelect={() => onAddNote(payment)}
                        className="cursor-pointer gap-2"
                    >
                        <StickyNote className="size-4" strokeWidth={2.25} />
                        {payment.internal_note
                            ? t('cobros:row.edit_note')
                            : t('cobros:row.add_note')}
                    </DropdownMenuItem>
                ) : null}

                {showResend ? (
                    <DropdownMenuItem
                        onSelect={() => onResendInvoice(payment)}
                        className="cursor-pointer gap-2 text-primary focus:text-primary"
                    >
                        <FileText className="size-4" strokeWidth={2.25} />
                        {t('cobros:row.resend_invoice')}
                    </DropdownMenuItem>
                ) : null}

                {showRefund ? (
                    <DropdownMenuItem
                        onSelect={() => onMarkRefunded(payment)}
                        className="cursor-pointer gap-2 text-amber-700 focus:text-amber-700 dark:text-amber-400"
                    >
                        <Undo2 className="size-4" strokeWidth={2.25} />
                        {t('cobros:row.mark_refunded')}
                    </DropdownMenuItem>
                ) : null}

                {isSinCobro ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled
                            className="gap-2 text-xs text-muted-foreground"
                        >
                            <Lock className="size-3.5" strokeWidth={2.25} />
                            {t('cobros:row.no_payment_record')}
                        </DropdownMenuItem>
                    </>
                ) : null}

                {isRefunded ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled
                            className="gap-2 text-xs text-muted-foreground"
                        >
                            <Lock className="size-3.5" strokeWidth={2.25} />
                            {t('cobros:row.refunded_locked')}
                        </DropdownMenuItem>
                    </>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
