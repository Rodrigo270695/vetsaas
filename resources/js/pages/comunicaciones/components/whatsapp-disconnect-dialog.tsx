import { router } from '@inertiajs/react';
import { Loader2, LogOut } from 'lucide-react';
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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    phone: string | null;
    onSuccess?: () => void;
};

export function WhatsAppDisconnectDialog({ open, onOpenChange, phone, onSuccess }: Props) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        setProcessing(true);
        router.post(
            '/comunicaciones/whatsapp/logout',
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    onOpenChange(false);
                    onSuccess?.();
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <LogOut className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">{t('whatsapp.disconnect_title')}</DialogTitle>
                    <DialogDescription className="text-sm">
                        {t('whatsapp.disconnect_description')}
                        {phone ? (
                            <span className="mt-2 block font-medium text-foreground">{phone}</span>
                        ) : null}
                    </DialogDescription>
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
                        {t('whatsapp.disconnect')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
