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
import inventario from '@/routes/inventario';
import type { CompraFila } from '../types';

type CompraAnularDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    compra: CompraFila | null;
};

export function CompraAnularDialog({ open, onOpenChange, compra }: CompraAnularDialogProps) {
    const { t } = useTranslation(['compras-inventario', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!compra) {
            return;
        }

        setProcessing(true);
        router.delete(inventario.compras.destroy.url({ compra: compra.id }), {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    const doc = compra ? [compra.serie, compra.numero_documento].filter(Boolean).join('-') : '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <TriangleAlert className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">{t('anular.title')}</DialogTitle>
                    <DialogDescription className="text-sm">
                        {t('anular.description', {
                            doc: doc || t('anular.sin_numero'),
                        })}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="button" variant="destructive" disabled={processing} onClick={onConfirm}>
                        {processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                        {t('anular.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
