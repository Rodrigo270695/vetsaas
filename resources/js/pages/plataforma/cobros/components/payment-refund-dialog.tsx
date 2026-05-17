import { router } from '@inertiajs/react';
import { Loader2, Undo2 } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import cobros from '@/routes/plataforma/cobros';
import type { SubscriptionPayment } from '../types';

export type PaymentRefundDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payment: SubscriptionPayment | null;
};

/**
 * Diálogo para marcar un cobro como **reembolsado manualmente**.
 *
 * Uso típico: el cliente pidió la devolución, tú procesaste el
 * reembolso por fuera de la pasarela (transferencia bancaria,
 * por ejemplo). Acá registras la decisión para auditoría.
 *
 * El motivo es OBLIGATORIO (mín 5 chars) y queda guardado junto con
 * el usuario que lo marcó y la fecha.
 */
export function PaymentRefundDialog({
    open,
    onOpenChange,
    payment,
}: PaymentRefundDialogProps) {
    const { t } = useTranslation(['cobros', 'common']);
    const [processing, setProcessing] = useState(false);
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | null>(null);

    const MIN_REASON = 5;

    useEffect(() => {
        if (open) {
            setReason('');
            setError(null);
            setProcessing(false);
        }
    }, [open]);

    const canSubmit = reason.trim().length >= MIN_REASON && !processing;

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!payment || !canSubmit) return;

        setProcessing(true);
        setError(null);
        router.post(
            cobros.markRefunded(payment.id).url,
            { reason: reason.trim() },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => {
                    setError(
                        errs?.reason ?? t('common:feedback.save_error'),
                    );
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={onSubmit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <div className="flex size-11 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <Undo2
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden="true"
                            />
                        </div>
                        <DialogTitle className="pt-2 text-base">
                            {t('cobros:refund.title')}
                        </DialogTitle>
                        <DialogDescription className="text-sm" asChild>
                            <p>
                                <Trans
                                    ns="cobros"
                                    i18nKey="refund.description"
                                    values={{
                                        tenant:
                                            payment?.tenant?.razon_social ??
                                            payment?.tenant?.slug ??
                                            '',
                                        total: payment
                                            ? `S/. ${Number(payment.total).toFixed(2)}`
                                            : '',
                                    }}
                                    components={{
                                        strong: (
                                            <strong className="text-foreground" />
                                        ),
                                    }}
                                />
                            </p>
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="refund-reason">
                            {t('cobros:refund.reason_label')}{' '}
                            <span
                                className="text-destructive"
                                aria-hidden="true"
                            >
                                *
                            </span>
                        </Label>
                        <Textarea
                            id="refund-reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder={t(
                                'cobros:refund.reason_placeholder',
                            )}
                            rows={3}
                            autoFocus
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('cobros:refund.reason_hint', { min: MIN_REASON })}
                        </p>
                    </div>

                    {error && <p className="text-xs text-destructive">{error}</p>}

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
                            type="submit"
                            disabled={!canSubmit}
                            className="cursor-pointer gap-2 bg-amber-600 text-white hover:bg-amber-700 focus-visible:ring-amber-500/40 disabled:cursor-not-allowed"
                        >
                            {processing && (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            {processing
                                ? t('cobros:refund.loading')
                                : t('cobros:refund.confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
