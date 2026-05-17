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
import planes from '@/routes/plataforma/planes';

export type PlanBulkDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** UUIDs de planes a eliminar. */
    ids: string[];
    onCompleted?: () => void;
};

/**
 * Confirmación para eliminar múltiples planes.
 *
 * El backend descarta automáticamente los planes con suscripciones
 * asociadas. Si todos los IDs caen en esa categoría, recibimos un
 * flash `info` y la tabla no cambia.
 */
export function PlanBulkDeleteDialog({
    open,
    onOpenChange,
    ids,
    onCompleted,
}: PlanBulkDeleteDialogProps) {
    const { t } = useTranslation(['planes', 'common']);
    const [processing, setProcessing] = useState(false);

    const count = ids.length;

    const onConfirm = () => {
        if (count === 0) {
            return;
        }
        setProcessing(true);
        router.delete(planes.bulkDestroy().url, {
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
                        {t('planes:bulk.delete_title', { count })}
                    </DialogTitle>
                    <DialogDescription className="text-sm">
                        {t('planes:bulk.delete_description', { count })}
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
                            ? t('planes:bulk.delete_confirm_singular')
                            : t('planes:bulk.delete_confirm_plural', { count })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
