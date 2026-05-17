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
import planes from '@/routes/plataforma/planes';
import type { Plan } from '../types';

export type PlanDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    plan: Plan | null;
};

/**
 * Diálogo de confirmación para eliminar un plan.
 *
 * Si el plan tiene suscripciones asociadas, mostramos un lock
 * informativo y NO renderizamos el botón. El backend también lo
 * rechaza (la FK es ON DELETE RESTRICT por defecto), esto es
 * defensa en UI.
 */
export function PlanDeleteDialog({
    open,
    onOpenChange,
    plan,
}: PlanDeleteDialogProps) {
    const { t } = useTranslation(['planes', 'common']);
    const [processing, setProcessing] = useState(false);

    const isProtected = plan !== null && plan.subscriptions_count > 0;

    const onConfirm = () => {
        if (!plan || isProtected) {
            return;
        }
        setProcessing(true);
        router.delete(planes.destroy(plan.id).url, {
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
                        {t('planes:delete.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm" asChild>
                        {isProtected ? (
                            <p>
                                {t('planes:delete.has_subscriptions', {
                                    count: plan?.subscriptions_count ?? 0,
                                })}
                            </p>
                        ) : (
                            <p>
                                <Trans
                                    ns="planes"
                                    i18nKey="delete.description"
                                    values={{ name: plan?.nombre ?? '' }}
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
                                ? t('planes:delete.loading')
                                : t('planes:delete.confirm')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
