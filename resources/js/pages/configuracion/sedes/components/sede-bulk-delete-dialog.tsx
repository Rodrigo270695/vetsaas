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
import sedes from '@/routes/configuracion/sedes';

export type SedeBulkDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** IDs de sedes a eliminar. */
    ids: string[];
    /** Callback al completar la operación con éxito. */
    onCompleted?: () => void;
};

/**
 * Confirmación para eliminar múltiples sedes a la vez.
 * Hace DELETE a `sedes.bulk-destroy` con `{ ids: [...] }`.
 */
export function SedeBulkDeleteDialog({
    open,
    onOpenChange,
    ids,
    onCompleted,
}: SedeBulkDeleteDialogProps) {
    const { t } = useTranslation(['sedes', 'common']);
    const [processing, setProcessing] = useState(false);

    const count = ids.length;

    const onConfirm = () => {
        if (count === 0) {
            return;
        }
        setProcessing(true);
        router.delete(sedes.bulkDestroy().url, {
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
                        {t('bulk.delete_title', { count })}
                    </DialogTitle>
                    <DialogDescription className="text-sm">
                        {t('bulk.delete_description', { count })}
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
                            ? t('bulk.delete_confirm_singular')
                            : t('bulk.delete_confirm_plural', { count })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
