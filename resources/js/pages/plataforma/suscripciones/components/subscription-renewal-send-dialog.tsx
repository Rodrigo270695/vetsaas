import { router } from '@inertiajs/react';
import { Loader2, Send } from 'lucide-react';
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
import suscripciones from '@/routes/plataforma/suscripciones';
import type { Subscription } from '../types';

export type SubscriptionRenewalSendDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    subscription: Subscription | null;
};

function sendRenewalUrl(subscriptionId: string): string {
    const base = suscripciones.index().url.replace(/\/$/, '');

    return `${base}/${subscriptionId}/send-renewal-whatsapp`;
}

export function SubscriptionRenewalSendDialog({
    open,
    onOpenChange,
    subscription,
}: SubscriptionRenewalSendDialogProps) {
    const { t } = useTranslation(['suscripciones', 'common']);
    const [processing, setProcessing] = useState(false);

    const tenantName =
        subscription?.tenant?.nombre_comercial ??
        subscription?.tenant?.razon_social ??
        subscription?.tenant?.slug ??
        '';

    const onConfirm = () => {
        if (!subscription) {
            return;
        }

        setProcessing(true);
        router.post(sendRenewalUrl(subscription.id), {}, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                        <Send className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('suscripciones:renewal_send.title')}
                    </DialogTitle>
                    <DialogDescription>
                        <Trans
                            i18nKey="suscripciones:renewal_send.description"
                            values={{ name: tenantName }}
                            components={{ strong: <strong /> }}
                        />
                    </DialogDescription>
                </DialogHeader>

                <p className="text-sm text-muted-foreground">
                    {t('suscripciones:renewal_send.hint')}
                </p>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="cursor-pointer"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        className="cursor-pointer gap-2"
                        disabled={processing || subscription === null}
                        onClick={onConfirm}
                    >
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Send className="size-4" strokeWidth={2.25} />
                        )}
                        {t('suscripciones:renewal_send.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
