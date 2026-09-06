import { router } from '@inertiajs/react';
import { Loader2, RotateCcw } from 'lucide-react';
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
import caja from '@/routes/caja';
import type { QueryParams } from '@/wayfinder';
import type { CajaSesionRow } from '../types';

type SesionReabrirDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sesion: CajaSesionRow | null;
    listQuery: QueryParams;
};

export function SesionReabrirDialog({
    open,
    onOpenChange,
    sesion,
    listQuery,
}: SesionReabrirDialogProps) {
    const { t } = useTranslation(['caja', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!sesion) {
            return;
        }

        setProcessing(true);
        router.post(
            caja.sesiones.reabrir.url({ caja_sesion: sesion.id }, { query: listQuery }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open && sesion !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                        <RotateCcw className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">
                        {t('caja:sesiones.dialog_reabrir.title')}
                    </DialogTitle>
                    <DialogDescription className="text-sm">
                        {t('caja:sesiones.dialog_reabrir.description')}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="cursor-pointer"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        className="cursor-pointer gap-2"
                        onClick={onConfirm}
                        disabled={processing || !sesion}
                    >
                        {processing ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
                        {t('caja:sesiones.dialog_reabrir.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
