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
    consultaId: string;
    /** Se invoca tras cerrar la consulta con éxito. */
    onClosed?: () => void;
    /** Se invoca si el usuario elige mantenerla abierta. */
    onKeepOpen?: () => void;
};

export function ConsultaCerrarPromptDialog({
    open,
    onOpenChange,
    consultaId,
    onClosed,
    onKeepOpen,
}: Props) {
    const { t } = useTranslation(['historias-clinicas', 'common']);
    const [processing, setProcessing] = useState(false);

    const handleClose = () => {
        setProcessing(true);
        router.post(
            clinica.historiasClinicas.consultas.cerrar.url({ consulta: consultaId }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    onOpenChange(false);
                    onClosed?.();
                },
            },
        );
    };

    const handleKeepOpen = () => {
        onOpenChange(false);
        onKeepOpen?.();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('cierre_prompt.title')}</DialogTitle>
                    <DialogDescription>{t('cierre_prompt.description')}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        className="cursor-pointer"
                        disabled={processing}
                        onClick={handleKeepOpen}
                    >
                        {t('cierre_prompt.keep_open')}
                    </Button>
                    <Button
                        type="button"
                        className="cursor-pointer gap-2"
                        disabled={processing}
                        onClick={handleClose}
                    >
                        {processing && <Loader2 className="size-4 animate-spin" aria-hidden />}
                        {t('form.cerrar')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
