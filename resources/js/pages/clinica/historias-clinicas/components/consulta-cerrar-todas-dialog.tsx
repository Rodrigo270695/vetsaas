import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    abiertasTotal: number;
};

export function ConsultaCerrarTodasDialog({ open, onOpenChange, abiertasTotal }: Props) {
    const { t } = useTranslation(['historias-clinicas', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        setProcessing(true);
        router.post(
            clinica.historiasClinicas.consultas.cerrarAbiertas.url(),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('cerrar_abiertas.title')}</DialogTitle>
                    <DialogDescription>
                        {t('cerrar_abiertas.description', { count: abiertasTotal })}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        className="cursor-pointer"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        className="cursor-pointer gap-2"
                        disabled={processing}
                        onClick={onConfirm}
                    >
                        {processing && <Loader2 className="size-4 animate-spin" aria-hidden />}
                        {t('cerrar_abiertas.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
