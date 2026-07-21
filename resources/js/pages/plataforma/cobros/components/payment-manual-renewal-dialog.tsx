import { router } from '@inertiajs/react';
import { CalendarSync, Loader2, ReceiptText } from 'lucide-react';
import { useEffect, useState  } from 'react';
import type {FormEvent} from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { SubscriptionPayment } from '../types';

type ManualPaymentMethod =
    | 'yape'
    | 'transferencia'
    | 'deposito'
    | 'efectivo'
    | 'otro';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payment: SubscriptionPayment | null;
};

export function PaymentManualRenewalDialog({
    open,
    onOpenChange,
    payment,
}: Props) {
    const { t } = useTranslation(['cobros', 'common']);
    const [amount, setAmount] = useState('');
    const [method, setMethod] = useState<ManualPaymentMethod>('yape');
    const [reference, setReference] = useState('');
    const [note, setNote] = useState('');
    const [idempotencyKey, setIdempotencyKey] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    /* eslint-disable react-hooks/set-state-in-effect -- reinicia el formulario para cada renovación */
    useEffect(() => {
        if (!open || !payment) {
            return;
        }

        const suggestedAmount =
            payment.manual_renewal_suggested_amount ??
            payment.subscription?.precio_pactado ??
            payment.total ??
            '';

        setAmount(
            suggestedAmount !== ''
                ? Number(suggestedAmount).toFixed(2)
                : '',
        );
        setMethod('yape');
        setReference('');
        setNote('');
        setIdempotencyKey(window.crypto.randomUUID());
        setProcessing(false);
        setError(null);
    }, [open, payment]);
    /* eslint-enable react-hooks/set-state-in-effect */

    const subscription = payment?.subscription;
    const tenantName =
        payment?.tenant?.razon_social ??
        payment?.tenant?.nombre_comercial ??
        payment?.tenant?.slug ??
        '';
    const parsedAmount = Number(amount);
    const canSubmit =
        subscription !== null &&
        subscription !== undefined &&
        subscription.estado !== 'cancelled' &&
        Number.isFinite(parsedAmount) &&
        parsedAmount > 0 &&
        reference.trim().length >= 3 &&
        idempotencyKey !== '' &&
        !processing;

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!subscription || !canSubmit) {
            return;
        }

        setProcessing(true);
        setError(null);

        router.post(
            `/plataforma/cobros/suscripciones/${subscription.id}/renovacion-manual`,
            {
                amount: parsedAmount,
                method,
                reference: reference.trim(),
                note: note.trim() || null,
                idempotency_key: idempotencyKey,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errors) => {
                    setError(
                        errors.manual_renewal ??
                            errors.amount ??
                            errors.method ??
                            errors.reference ??
                            t('common:feedback.save_error'),
                    );
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={onSubmit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <div className="flex size-11 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                            <CalendarSync
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden
                            />
                        </div>
                        <DialogTitle className="pt-2 text-base">
                            {t('cobros:manual_renewal.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('cobros:manual_renewal.description', {
                                tenant: tenantName,
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs leading-relaxed text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100">
                        <div className="flex gap-2">
                            <ReceiptText className="mt-0.5 size-4 shrink-0" />
                            <span>{t('cobros:manual_renewal.effect_hint')}</span>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="manual-renewal-amount">
                                {t('cobros:manual_renewal.amount')}
                            </Label>
                            <Input
                                id="manual-renewal-amount"
                                type="number"
                                min="0.01"
                                max="999999.99"
                                step="0.01"
                                value={amount}
                                onChange={(event) =>
                                    setAmount(event.target.value)
                                }
                                className="tabular-nums"
                                required
                            />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="manual-renewal-method">
                                {t('cobros:manual_renewal.method')}
                            </Label>
                            <Select
                                value={method}
                                onValueChange={(value) =>
                                    setMethod(value as ManualPaymentMethod)
                                }
                            >
                                <SelectTrigger id="manual-renewal-method">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {(
                                        [
                                            'yape',
                                            'transferencia',
                                            'deposito',
                                            'efectivo',
                                            'otro',
                                        ] as const
                                    ).map((value) => (
                                        <SelectItem key={value} value={value}>
                                            {t(
                                                `cobros:manual_renewal.methods.${value}`,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="manual-renewal-reference">
                            {t('cobros:manual_renewal.reference')} *
                        </Label>
                        <Input
                            id="manual-renewal-reference"
                            value={reference}
                            minLength={3}
                            maxLength={120}
                            onChange={(event) =>
                                setReference(event.target.value)
                            }
                            placeholder={t(
                                'cobros:manual_renewal.reference_placeholder',
                            )}
                            required
                        />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="manual-renewal-note">
                            {t('cobros:manual_renewal.note')}
                        </Label>
                        <Textarea
                            id="manual-renewal-note"
                            value={note}
                            maxLength={1000}
                            rows={2}
                            onChange={(event) => setNote(event.target.value)}
                            placeholder={t(
                                'cobros:manual_renewal.note_placeholder',
                            )}
                        />
                    </div>

                    {error ? (
                        <p className="text-xs text-destructive">{error}</p>
                    ) : null}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => onOpenChange(false)}
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={!canSubmit}
                            className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <CalendarSync className="size-4" />
                            )}
                            {t('cobros:manual_renewal.confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
