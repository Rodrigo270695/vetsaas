import { router } from '@inertiajs/react';
import { FileText, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import cobros from '@/routes/plataforma/cobros';
import type { SubscriptionPayment } from '../types';

export type PaymentResendInvoiceDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payment: SubscriptionPayment | null;
};

/**
 * Confirmación rápida para reenviar la factura electrónica al cliente.
 *
 * El backend solo marca el momento del reenvío (`invoice_resent_at`);
 * el envío físico (email/PDF) lo dispara un job/evento que se conectará
 * cuando exista el módulo FEL. Hasta entonces, este botón es "noop útil
 * para soporte" (deja rastro pero no envía).
 */
export function PaymentResendInvoiceDialog({
    open,
    onOpenChange,
    payment,
}: PaymentResendInvoiceDialogProps) {
    const { t } = useTranslation(['cobros', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!payment) return;
        setProcessing(true);
        router.post(
            cobros.resendInvoice(payment.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <FileText
                            className="size-5"
                            strokeWidth={2.5}
                            aria-hidden="true"
                        />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('cobros:resend.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm" asChild>
                        <p>
                            <Trans
                                ns="cobros"
                                i18nKey="resend.description"
                                values={{
                                    email:
                                        payment?.tenant?.email_admin ?? '—',
                                    fel: payment?.fel_numero ?? '—',
                                }}
                                components={{
                                    strong: (
                                        <strong className="text-foreground" />
                                    ),
                                    mono: (
                                        <span className="font-mono text-xs" />
                                    ),
                                }}
                            />
                        </p>
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {processing
                            ? t('cobros:resend.loading')
                            : t('cobros:resend.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
