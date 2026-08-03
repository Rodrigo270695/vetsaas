import { BedDouble, Scissors } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ServicioAgendaFormPrefill, ServicioAgendaTipo } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    prefill?: ServicioAgendaFormPrefill | null;
    canGrooming: boolean;
    canHotel: boolean;
    onPick: (tipo: ServicioAgendaTipo) => void;
};

export function ServicioTipoPickerDialog({
    open,
    onOpenChange,
    canGrooming,
    canHotel,
    onPick,
}: Props) {
    const { t } = useTranslation('servicios-agenda');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('tipo_picker.title')}</DialogTitle>
                    <DialogDescription>{t('tipo_picker.description')}</DialogDescription>
                </DialogHeader>
                <div className="grid gap-3 sm:grid-cols-2">
                    {canGrooming ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="h-auto cursor-pointer flex-col items-start gap-2 border-emerald-200 bg-emerald-50/60 py-4 text-left hover:bg-emerald-100/80 dark:border-emerald-900 dark:bg-emerald-950/40"
                            onClick={() => onPick('grooming')}
                        >
                            <Scissors className="size-5 text-emerald-700 dark:text-emerald-300" />
                            <span className="text-sm font-semibold text-emerald-950 dark:text-emerald-100">
                                {t('tipo.grooming')}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t('tipo_picker.grooming_hint')}
                            </span>
                        </Button>
                    ) : null}
                    {canHotel ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="h-auto cursor-pointer flex-col items-start gap-2 border-sky-200 bg-sky-50/60 py-4 text-left hover:bg-sky-100/80 dark:border-sky-900 dark:bg-sky-950/40"
                            onClick={() => onPick('hotel')}
                        >
                            <BedDouble className="size-5 text-sky-700 dark:text-sky-300" />
                            <span className="text-sm font-semibold text-sky-950 dark:text-sky-100">
                                {t('tipo.hotel')}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t('tipo_picker.hotel_hint')}
                            </span>
                        </Button>
                    ) : null}
                </div>
            </DialogContent>
        </Dialog>
    );
}
