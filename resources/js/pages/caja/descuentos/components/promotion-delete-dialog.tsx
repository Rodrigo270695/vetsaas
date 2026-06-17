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
import type { Promotion } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    promotion: Promotion | null;
};

export function PromotionDeleteDialog({ open, onOpenChange, promotion }: Props) {
    const { t } = useTranslation(['descuentos-promociones', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!promotion) {
            return;
        }
        setProcessing(true);
        router.delete(`/caja/descuentos/${promotion.id}`, {
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
                    <DialogTitle className="pt-2 text-base">{t('common:actions.delete')}</DialogTitle>
                    <DialogDescription className="text-sm">
                        {promotion?.name ?? '—'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="button" variant="destructive" disabled={processing} onClick={onConfirm}>
                        {processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                        {t('common:actions.delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
