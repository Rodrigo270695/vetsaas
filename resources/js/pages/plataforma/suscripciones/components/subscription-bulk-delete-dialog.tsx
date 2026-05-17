import { router } from '@inertiajs/react';
import { Loader2, TriangleAlert } from 'lucide-react';
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
import suscripciones from '@/routes/plataforma/suscripciones';

export type SubscriptionBulkDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    ids: string[];
    onCompleted?: () => void;
};

/**
 * Confirmación para eliminar múltiples suscripciones.
 *
 * El backend solo elimina las que están en estado `cancelled`. Las
 * activas se omiten automáticamente para preservar contratos vivos.
 */
export function SubscriptionBulkDeleteDialog({
    open,
    onOpenChange,
    ids,
    onCompleted,
}: SubscriptionBulkDeleteDialogProps) {
    const { t } = useTranslation(['suscripciones', 'common']);
    const [processing, setProcessing] = useState(false);

    const count = ids.length;

    const onConfirm = () => {
        if (count === 0) {
            return;
        }
        setProcessing(true);
        router.delete(suscripciones.bulkDestroy().url, {
            data: { ids },
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                onCompleted?.();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <TriangleAlert
                            className="size-5"
                            strokeWidth={2.5}
                            aria-hidden="true"
                        />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('suscripciones:bulk.delete_title', { count })}
                    </DialogTitle>
                    <DialogDescription className="text-sm">
                        {t('suscripciones:bulk.delete_description', { count })}
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
                        variant="destructive"
                        onClick={onConfirm}
                        disabled={processing || count === 0}
                        className="cursor-pointer gap-2"
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {count === 1
                            ? t('suscripciones:bulk.delete_confirm_singular')
                            : t('suscripciones:bulk.delete_confirm_plural', {
                                  count,
                              })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
