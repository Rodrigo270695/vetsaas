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
import roles from '@/routes/configuracion/roles';

export type RoleBulkDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** IDs (numéricos) de roles a eliminar. */
    ids: number[];
    /** Callback al completar la operación con éxito. */
    onCompleted?: () => void;
};

/**
 * Confirmación para eliminar múltiples roles a la vez.
 * Hace DELETE a `roles.bulk-destroy` con `{ ids: [...] }`.
 *
 * El backend descarta automáticamente los IDs de roles del sistema y
 * devuelve un mensaje informativo cuando todos los seleccionados eran
 * de sistema (no rompe la UX si el usuario incluye uno por error).
 */
export function RoleBulkDeleteDialog({
    open,
    onOpenChange,
    ids,
    onCompleted,
}: RoleBulkDeleteDialogProps) {
    const { t } = useTranslation(['roles', 'common']);
    const [processing, setProcessing] = useState(false);

    const count = ids.length;

    const onConfirm = () => {
        if (count === 0) {
            return;
        }
        setProcessing(true);
        router.delete(roles.bulkDestroy().url, {
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
                        {t('roles:bulk.delete_title', { count })}
                    </DialogTitle>
                    <DialogDescription className="text-sm">
                        {t('roles:bulk.delete_description', { count })}
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
                            ? t('roles:bulk.delete_confirm_singular')
                            : t('roles:bulk.delete_confirm_plural', { count })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
