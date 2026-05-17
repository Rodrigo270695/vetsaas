import { router } from '@inertiajs/react';
import { Loader2, StickyNote } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import cobros from '@/routes/plataforma/cobros';
import type { SubscriptionPayment } from '../types';

export type PaymentNoteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payment: SubscriptionPayment | null;
};

/**
 * Diálogo para crear/editar/eliminar la nota interna del cobro.
 *
 * Si el cobro ya tiene una nota, el textarea se hidrata con ella.
 * Si el campo se deja vacío y se envía, el backend la elimina.
 */
export function PaymentNoteDialog({
    open,
    onOpenChange,
    payment,
}: PaymentNoteDialogProps) {
    const { t } = useTranslation(['cobros', 'common']);
    const [processing, setProcessing] = useState(false);
    const [note, setNote] = useState('');
    const [error, setError] = useState<string | null>(null);

    const isEdit = !!payment?.internal_note;

    useEffect(() => {
        if (open) {
            setNote(payment?.internal_note ?? '');
            setError(null);
            setProcessing(false);
        }
    }, [open, payment?.internal_note]);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!payment) return;

        setProcessing(true);
        setError(null);
        router.post(
            cobros.addNote(payment.id).url,
            { note: note.trim() === '' ? null : note.trim() },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => {
                    setError(errs?.note ?? t('common:feedback.save_error'));
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={onSubmit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <div className="flex size-11 items-center justify-center rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <StickyNote
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden="true"
                            />
                        </div>
                        <DialogTitle className="pt-2 text-base">
                            {isEdit
                                ? t('cobros:note.title_edit')
                                : t('cobros:note.title_create')}
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            {t('cobros:note.description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="payment-note">
                            {t('cobros:note.note_label')}
                        </Label>
                        <Textarea
                            id="payment-note"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            placeholder={t('cobros:note.note_placeholder')}
                            rows={5}
                            autoFocus
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('cobros:note.note_hint')}
                        </p>
                    </div>

                    {error && <p className="text-xs text-destructive">{error}</p>}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                            className="cursor-pointer"
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                        >
                            {processing && (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            {processing
                                ? t('cobros:note.loading')
                                : isEdit
                                  ? t('cobros:note.confirm_edit')
                                  : t('cobros:note.confirm_create')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
