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
import {
    normalizeTicketAncho,
    resolveTicketAncho,
    TICKET_ANCHO_OPTIONS,
    writeStoredTicketAncho,
    type TicketAnchoMm,
} from '@/lib/ticket-ancho';
import type { VentaRow } from '../types';

export type VentaWhatsAppModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    venta: VentaRow | null;
    configAncho: TicketAnchoMm;
};

export function VentaWhatsAppModal({ open, onOpenChange, venta, configAncho }: VentaWhatsAppModalProps) {
    const { t } = useTranslation(['caja', 'common']);
    const [telefono, setTelefono] = useState('');
    const [ancho, setAncho] = useState<TicketAnchoMm>(() => resolveTicketAncho(normalizeTicketAncho(configAncho)));
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const hasStoredPhone = Boolean(venta?.cliente_telefono?.trim());
    const esCpeEmitido = venta?.fel_estado === 'emitido' && Boolean(venta.pdf_url?.trim());

    useEffect(() => {
        if (!open || !venta) {
            return;
        }

        setTelefono(venta.cliente_telefono?.trim() ?? '');
        setAncho(resolveTicketAncho(normalizeTicketAncho(configAncho)));
        setError(null);
        setProcessing(false);
    }, [open, venta, configAncho]);

    const handleAnchoChange = (next: TicketAnchoMm) => {
        setAncho(next);
        writeStoredTicketAncho(next);
    };

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
            { telefono: phoneToSend || undefined, ancho },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => {
                    const msg = errs.telefono ?? errs.ancho ?? Object.values(errs)[0];
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

                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
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
                    </div>

                    <div className="grid gap-2 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5">
                        <Label className="text-xs font-medium text-foreground">
                            {t('common:ticket_ancho.label')}
                        </Label>
                        <div
                            className="flex flex-wrap gap-2"
                            role="radiogroup"
                            aria-label={t('common:ticket_ancho.label')}
                        >
                            {TICKET_ANCHO_OPTIONS.map((value) => (
                                <Button
                                    key={value}
                                    type="button"
                                    size="sm"
                                    variant={ancho === value ? 'default' : 'outline'}
                                    className="h-8 cursor-pointer px-3 text-xs"
                                    role="radio"
                                    aria-checked={ancho === value}
                                    disabled={processing}
                                    onClick={() => handleAnchoChange(value)}
                                >
                                    {t(`common:ticket_ancho.${value}`)}
                                </Button>
                            ))}
                        </div>
                        <p className="text-[0.7rem] leading-snug text-muted-foreground">
                            {esCpeEmitido
                                ? t('caja:ventas.whatsapp.ancho_cpe_hint')
                                : t('caja:ventas.whatsapp.ancho_hint')}
                        </p>
                    </div>

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
