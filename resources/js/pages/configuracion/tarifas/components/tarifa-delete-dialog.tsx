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
import type { GroomingTarifa, HotelTarifa, TarifaTab } from '../types';

type TarifaDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kind: TarifaTab | null;
    tarifa: GroomingTarifa | HotelTarifa | null;
    nombre: string;
};

export function TarifaDeleteDialog({
    open,
    onOpenChange,
    kind,
    tarifa,
    nombre,
}: TarifaDeleteDialogProps) {
    const { t } = useTranslation(['tarifas-servicios', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!tarifa || !kind) {
            return;
        }

        const url =
            kind === 'grooming'
                ? `/configuracion/tarifas/grooming/${tarifa.id}`
                : `/configuracion/tarifas/hotel/${tarifa.id}`;

        setProcessing(true);
        router.delete(url, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                <DialogHeader className="space-y-3 px-6 pt-6 pb-4">
                    <div className="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <TriangleAlert className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="text-base">{t('delete.title')}</DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed">
                        {t('delete.description', { nombre: nombre || '—' })}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="border-t border-border/60 px-6 py-4">
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        {t('form.cancelar')}
                    </Button>
                    <Button type="button" variant="destructive" disabled={processing} onClick={onConfirm}>
                        {processing ? <Loader2 className="mr-2 size-4 animate-spin" /> : null}
                        {t('delete.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
