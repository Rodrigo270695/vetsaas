import { router } from '@inertiajs/react';
import { Loader2, Ban } from 'lucide-react';
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
import clinica from '@/routes/clinica';
import type { CitaRow } from '../types';

export type CitaCancelDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    cita: CitaRow | null;
};

export function CitaCancelDialog({ open, onOpenChange, cita }: CitaCancelDialogProps) {
    const { t } = useTranslation(['citas', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!cita) {
            return;
        }

        setProcessing(true);
        router.post(clinica.citas.cancelar({ cita: cita.id }).url, {}, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-400">
                        <Ban className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">{t('cancel.title')}</DialogTitle>
                    <DialogDescription className="text-sm">{t('cancel.description')}</DialogDescription>
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
                        variant="default"
                        onClick={onConfirm}
                        disabled={processing}
                        className="gap-2"
                    >
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('common:actions.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
