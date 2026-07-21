import { router } from '@inertiajs/react';
import { Loader2, MessageCircle } from 'lucide-react';
import { useState } from 'react';
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
import type { SubscriptionPayment } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payment: SubscriptionPayment | null;
};

export function PaymentRenewalWhatsAppDialog({
    open,
    onOpenChange,
    payment,
}: Props) {
    const { t } = useTranslation(['cobros', 'common']);
    const [processing, setProcessing] = useState(false);

    const tenantName =
        payment?.tenant?.nombre_comercial ??
        payment?.tenant?.razon_social ??
        payment?.tenant?.slug ??
        '';

    const onConfirm = () => {
        const subscriptionId = payment?.subscription?.id ?? payment?.subscription_id;
        if (!subscriptionId) {
            return;
        }

        setProcessing(true);
        router.post(
            `/plataforma/suscripciones/${subscriptionId}/send-renewal-whatsapp`,
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
            <DialogContent className="border-emerald-200 sm:max-w-md dark:border-emerald-800">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <MessageCircle className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2">
                        {t('cobros:renewal_whatsapp.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('cobros:renewal_whatsapp.description', {
                            name: tenantName,
                        })}
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100">
                    {t('cobros:renewal_whatsapp.hint')}
                </div>

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
                        type="button"
                        className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                        disabled={processing || payment === null}
                        onClick={onConfirm}
                    >
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <MessageCircle className="size-4" />
                        )}
                        {t('cobros:renewal_whatsapp.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
