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
import servicios from '@/routes/servicios';
import type { HotelEstanciaRow } from '../types';

export type HotelDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    estancia: HotelEstanciaRow | null;
};

export function HotelDeleteDialog({ open, onOpenChange, estancia }: HotelDeleteDialogProps) {
    const { t } = useTranslation(['hotel', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!estancia) {
            return;
        }

        setProcessing(true);
        router.delete(servicios.hotel.destroy({ hotel_estancia: estancia.id }).url, {
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
                        <TriangleAlert className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">{t('delete.title')}</DialogTitle>
                    <DialogDescription className="text-sm">{t('delete.description')}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={onConfirm}
                        disabled={processing}
                        className="gap-2"
                    >
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('common:actions.delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
