import {
    Copy,
    Eye,
    FileText,
    Lock,
    MoreHorizontal,
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
    canAddNote?: boolean;
    canRefund?: boolean;
    canResend?: boolean;
};

/**
 * Dropdown de acciones por fila para cobros.
 *
 * Disponibilidad de acciones:
 *   - "Ver detalle"              → siempre disponible.
 *   - "Copiar transaction ID"    → si existe pasarela_transaction_id.
 *   - "Nota interna"             → si hay permiso `add-note`.
 *   - "Reenviar factura"         → si hay permiso `resend-invoice`
 *                                   Y el cobro tiene FEL emitida.
 *   - "Marcar reembolso manual"  → si hay permiso `refund`
 *                                   Y el cobro NO es fallido (no tiene
 *                                   sentido reembolsar algo que no llegó
 *                                   a procesarse)
 *                                   Y NO está ya reembolsado.
 *   - Si el cobro está reembolsado, mostramos un item informativo
 *     con lock al final.
 */
export function PaymentRowActions({
    payment,
    onViewDetail,
    onAddNote,
    onMarkRefunded,
    onResendInvoice,
    canAddNote = true,
    canRefund = true,
    canResend = true,
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

    const handleCopyTxId = async () => {
        if (!payment.pasarela_transaction_id) return;
        try {
            await navigator.clipboard.writeText(
                payment.pasarela_transaction_id,
            );
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
            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuItem
                    onSelect={() => onViewDetail(payment)}
                    className="cursor-pointer gap-2"
                >
                    <Eye className="size-4" strokeWidth={2.25} />
                    {t('cobros:row.view_detail')}
                </DropdownMenuItem>

                {showCopy && (
                    <DropdownMenuItem
                        onSelect={handleCopyTxId}
                        className="cursor-pointer gap-2"
                    >
                        <Copy className="size-4" strokeWidth={2.25} />
                        {t('cobros:row.copy_tx_id')}
                    </DropdownMenuItem>
                )}

                {(showNote || showResend || showRefund) && (
                    <DropdownMenuSeparator />
                )}

                {showNote && (
                    <DropdownMenuItem
                        onSelect={() => onAddNote(payment)}
                        className="cursor-pointer gap-2"
                    >
                        <StickyNote className="size-4" strokeWidth={2.25} />
                        {payment.internal_note
                            ? t('cobros:row.edit_note')
                            : t('cobros:row.add_note')}
                    </DropdownMenuItem>
                )}

                {showResend && (
                    <DropdownMenuItem
                        onSelect={() => onResendInvoice(payment)}
                        className="cursor-pointer gap-2 text-primary focus:text-primary"
                    >
                        <FileText className="size-4" strokeWidth={2.25} />
                        {t('cobros:row.resend_invoice')}
                    </DropdownMenuItem>
                )}

                {showRefund && (
                    <DropdownMenuItem
                        onSelect={() => onMarkRefunded(payment)}
                        className="cursor-pointer gap-2 text-amber-700 focus:text-amber-700 dark:text-amber-400"
                    >
                        <Undo2 className="size-4" strokeWidth={2.25} />
                        {t('cobros:row.mark_refunded')}
                    </DropdownMenuItem>
                )}

                {isSinCobro && (
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
                )}

                {isRefunded && (
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
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
