import { router } from '@inertiajs/react';
import { Loader2, MessageCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { VentaRow } from '../types';

export type VentaWhatsAppModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    venta: VentaRow | null;
};

export function VentaWhatsAppModal({ open, onOpenChange, venta }: VentaWhatsAppModalProps) {
    const { t } = useTranslation('caja');
    const [telefono, setTelefono] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const hasStoredPhone = Boolean(venta?.cliente_telefono?.trim());

    useEffect(() => {
        if (!open || !venta) {
            return;
        }

        setTelefono(venta.cliente_telefono?.trim() ?? '');
        setError(null);
        setProcessing(false);
    }, [open, venta]);

    const submit = () => {
        if (!venta) {
            return;
        }

        const phoneToSend = telefono.trim();
        if (!hasStoredPhone && phoneToSend === '') {
            setError(t('caja:ventas.whatsapp.phone_hint'));

            return;
        }

        setProcessing(true);
        setError(null);

        router.post(
            `/caja/ventas/${venta.id}/enviar-whatsapp`,
            { telefono: phoneToSend || undefined },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => {
                    const msg = errs.telefono ?? Object.values(errs)[0];
                    setError(typeof msg === 'string' ? msg : t('caja:ventas.whatsapp.phone_hint'));
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <MessageCircle className="size-5 text-primary" />
                        {t('caja:ventas.whatsapp.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {venta
                            ? hasStoredPhone
                                ? t('caja:ventas.whatsapp.description_with_phone', {
                                      numero: venta.numero_display,
                                      cliente: venta.cliente,
                                      telefono: venta.cliente_telefono,
                                  })
                                : t('caja:ventas.whatsapp.description_no_phone', {
                                      cliente: venta.cliente,
                                  })
                            : null}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2 py-2">
                    <Label htmlFor="venta-wa-phone">{t('caja:ventas.whatsapp.phone_label')}</Label>
                    <Input
                        id="venta-wa-phone"
                        type="tel"
                        inputMode="tel"
                        placeholder={t('caja:ventas.whatsapp.phone_placeholder')}
                        value={telefono}
                        onChange={(e) => setTelefono(e.target.value)}
                        disabled={processing}
                        aria-invalid={Boolean(error)}
                    />
                    <p className="text-xs text-muted-foreground">{t('caja:ventas.whatsapp.phone_hint')}</p>
                    {error ? <p className="text-sm text-destructive">{error}</p> : null}
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('caja:ventas.whatsapp.cancel')}
                    </Button>
                    <Button type="button" disabled={processing || !venta} onClick={submit} className="gap-2">
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                        ) : (
                            <MessageCircle className="size-4" aria-hidden />
                        )}
                        {processing
                            ? t('caja:ventas.whatsapp.sending')
                            : t('caja:ventas.whatsapp.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
