import { useForm } from '@inertiajs/react';
import { Clock3, MessageCircle, Send } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ClinicalHistoryShareTarget = {
    url: string;
    label: string;
} | null;

type Props = {
    target: ClinicalHistoryShareTarget;
    defaultPhone: string;
    onOpenChange: (open: boolean) => void;
};

export function ClinicalHistoryWhatsAppDialog({
    target,
    defaultPhone,
    onOpenChange,
}: Props) {
    const { t } = useTranslation(['pacientes']);
    const form = useForm({ telefono: defaultPhone });

    useEffect(() => {
        if (target) {
            form.setData('telefono', defaultPhone);
            form.clearErrors();
        }
        // `form` is intentionally excluded: its identity is not stable.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [target, defaultPhone]);

    const submit = () => {
        if (!target) {
            return;
        }

        form.post(target.url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={target !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="mb-1 flex size-11 items-center justify-center rounded-xl bg-emerald-500/12 text-emerald-600 dark:text-emerald-400">
                        <MessageCircle className="size-5" />
                    </div>
                    <DialogTitle>{t('historial.whatsapp_title')}</DialogTitle>
                    <DialogDescription>
                        {t('historial.whatsapp_description', {
                            document: target?.label ?? '',
                        })}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-1">
                    <div className="space-y-2">
                        <Label htmlFor="clinical-history-whatsapp-phone">
                            {t('historial.whatsapp_phone')}
                        </Label>
                        <Input
                            id="clinical-history-whatsapp-phone"
                            type="tel"
                            inputMode="tel"
                            autoComplete="tel"
                            placeholder="51999999999"
                            value={form.data.telefono}
                            onChange={(event) => form.setData('telefono', event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    submit();
                                }
                            }}
                        />
                        <InputError message={form.errors.telefono} />
                    </div>

                    <div className="flex gap-2 rounded-lg border border-sky-500/20 bg-sky-500/8 p-3 text-xs leading-relaxed text-sky-900 dark:text-sky-100">
                        <Clock3 className="mt-0.5 size-4 shrink-0" />
                        <p>{t('historial.whatsapp_link_hint')}</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={form.processing}
                    >
                        {t('historial.whatsapp_cancel')}
                    </Button>
                    <Button
                        type="button"
                        className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                        onClick={submit}
                        disabled={form.processing || form.data.telefono.trim() === ''}
                    >
                        <Send className="size-4" />
                        {form.processing
                            ? t('historial.whatsapp_sending')
                            : t('historial.whatsapp_send')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
