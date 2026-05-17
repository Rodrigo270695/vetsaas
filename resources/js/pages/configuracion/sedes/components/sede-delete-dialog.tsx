import { router } from '@inertiajs/react';
import { Loader2, TriangleAlert } from 'lucide-react';
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
import sedes from '@/routes/configuracion/sedes';
import type { Sede } from '../types';

export type SedeDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sede: Sede | null;
};

/**
 * Diálogo de confirmación para eliminar una sede.
 * Hace soft delete vía DELETE a `sedes.destroy`.
 */
export function SedeDeleteDialog({
    open,
    onOpenChange,
    sede,
}: SedeDeleteDialogProps) {
    const { t } = useTranslation(['sedes', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!sede) {
            return;
        }
        setProcessing(true);
        router.delete(sedes.destroy(sede.id).url, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
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
                        {t('delete.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm" asChild>
                        <p>
                            <Trans
                                ns="sedes"
                                i18nKey="delete.description"
                                values={{
                                    name: sede?.nombre ?? '',
                                    code: sede?.codigo ?? '',
                                }}
                                components={{
                                    strong: (
                                        <strong className="text-foreground" />
                                    ),
                                }}
                            />
                        </p>
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
                        disabled={processing}
                        className="cursor-pointer gap-2"
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {processing ? t('delete.loading') : t('delete.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
