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
import type { InternamientoSignoRow } from '../types';

export type SignoDeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    internamientoId: string;
    signo: InternamientoSignoRow | null;
};

export function SignoDeleteDialog({ open, onOpenChange, internamientoId, signo }: SignoDeleteDialogProps) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!signo) {
            return;
        }

        setProcessing(true);
        router.delete(`/clinica/hospitalizacion/${internamientoId}/signos/${signo.id}`, {
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
                    <DialogTitle className="pt-2 text-base">{t('signos.delete_title')}</DialogTitle>
                    <DialogDescription className="text-sm">{t('signos.delete_description')}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
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
