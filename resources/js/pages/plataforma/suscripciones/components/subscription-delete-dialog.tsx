import { router } from '@inertiajs/react';
import { Loader2, Lock, TriangleAlert } from 'lucide-react';
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

export type SubscriptionDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    subscription: Subscription | null;
};

/**
 * Diálogo de confirmación para eliminar una suscripción.
 *
 * Solo se permite eliminar suscripciones `cancelled` (defensa en UI
 * y en backend). Para las demás, hay que cancelarlas primero vía
 * `SubscriptionCancelDialog`.
 */
export function SubscriptionDeleteDialog({
    open,
    onOpenChange,
    subscription,
}: SubscriptionDeleteDialogProps) {
    const { t } = useTranslation(['suscripciones', 'common']);
    const [processing, setProcessing] = useState(false);

    const isProtected =
        subscription !== null && subscription.estado !== 'cancelled';

    const onConfirm = () => {
        if (!subscription || isProtected) {
            return;
        }
        setProcessing(true);
        router.delete(suscripciones.destroy(subscription.id).url, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div
                        className={
                            isProtected
                                ? 'flex size-11 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                : 'flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive'
                        }
                    >
                        {isProtected ? (
                            <Lock
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden="true"
                            />
                        ) : (
                            <TriangleAlert
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden="true"
                            />
                        )}
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('suscripciones:delete.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm" asChild>
                        {isProtected ? (
                            <p>{t('suscripciones:delete.not_cancelled')}</p>
                        ) : (
                            <p>
                                <Trans
                                    ns="suscripciones"
                                    i18nKey="delete.description"
                                    values={{
                                        tenant:
                                            subscription?.tenant?.razon_social ??
                                            subscription?.tenant?.slug ??
                                            '',
                                    }}
                                    components={{
                                        strong: (
                                            <strong className="text-foreground" />
                                        ),
                                    }}
                                />
                            </p>
                        )}
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
                    {!isProtected && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={onConfirm}
                            disabled={processing}
                            className="cursor-pointer gap-2"
                        >
                            {processing && (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            {processing
                                ? t('suscripciones:delete.loading')
                                : t('suscripciones:delete.confirm')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
