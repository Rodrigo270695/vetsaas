import { router } from '@inertiajs/react';
import { Bot, Loader2 } from 'lucide-react';
import { useMemo, useState } from 'react';
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

export type SubscriptionBotIaToggleDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    subscription: Subscription | null;
};

function toggleBotIaUrl(subscriptionId: string): string {
    const base = suscripciones.index().url.replace(/\/$/, '');

    return `${base}/${subscriptionId}/toggle-bot-ia`;
}

const formatPrice = (value: string | null | undefined): string => {
    if (!value) return '15.00';
    const num = Number(value);
    if (Number.isNaN(num)) return value;

    return num.toFixed(2);
};

export function SubscriptionBotIaToggleDialog({
    open,
    onOpenChange,
    subscription,
}: SubscriptionBotIaToggleDialogProps) {
    const { t } = useTranslation(['suscripciones', 'common']);
    const [processing, setProcessing] = useState(false);

    const tenantName =
        subscription?.tenant?.nombre_comercial ??
        subscription?.tenant?.razon_social ??
        subscription?.tenant?.slug ??
        '';

    const activating = subscription?.bot_ia_activo !== true;
    const monthlyPrice = formatPrice(subscription?.bot_ia_precio_mensual);

    const copy = useMemo(() => {
        if (activating) {
            return {
                title: t('suscripciones:bot_ia_toggle.activate_title'),
                descriptionKey: 'suscripciones:bot_ia_toggle.activate_description',
                hint: t('suscripciones:bot_ia_toggle.activate_hint', {
                    price: monthlyPrice,
                }),
                confirm: t('suscripciones:bot_ia_toggle.activate_confirm'),
                iconClass:
                    'bg-violet-500/10 text-violet-700 dark:text-violet-400',
                buttonClass: 'bg-violet-600 hover:bg-violet-700 text-white',
            };
        }

        return {
            title: t('suscripciones:bot_ia_toggle.deactivate_title'),
            descriptionKey: 'suscripciones:bot_ia_toggle.deactivate_description',
            hint: t('suscripciones:bot_ia_toggle.deactivate_hint'),
            confirm: t('suscripciones:bot_ia_toggle.deactivate_confirm'),
            iconClass: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
            buttonClass: '',
        };
    }, [activating, monthlyPrice, t]);

    const onConfirm = () => {
        if (!subscription) {
            return;
        }

        setProcessing(true);
        router.post(
            toggleBotIaUrl(subscription.id),
            { activo: activating },
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
                    <div
                        className={`flex size-11 items-center justify-center rounded-full ${copy.iconClass}`}
                    >
                        <Bot className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">{copy.title}</DialogTitle>
                    <DialogDescription>
                        <Trans
                            i18nKey={copy.descriptionKey}
                            values={{ name: tenantName, price: monthlyPrice }}
                            components={{ strong: <strong /> }}
                        />
                    </DialogDescription>
                </DialogHeader>

                <p className="text-sm text-muted-foreground">{copy.hint}</p>

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
                        variant={activating ? 'default' : 'destructive'}
                        className={`cursor-pointer gap-2 ${activating ? copy.buttonClass : ''}`}
                        disabled={processing || subscription === null}
                        onClick={onConfirm}
                    >
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Bot className="size-4" strokeWidth={2.25} />
                        )}
                        {copy.confirm}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
