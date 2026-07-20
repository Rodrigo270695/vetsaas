import { router } from '@inertiajs/react';
import { Loader2, MessageCircle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import type { PedidoLaboratorioRow } from '../types';
import { propietarioDisplayName, pedidoDocumentCount } from '../types';

export type PedidoWhatsAppModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    pedido: PedidoLaboratorioRow | null;
};

export function PedidoWhatsAppModal({
    open,
    onOpenChange,
    pedido,
}: PedidoWhatsAppModalProps) {
    const { t } = useTranslation(['laboratorio', 'common']);
    const [telefono, setTelefono] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const storedPhone = pedido?.paciente?.propietario?.telefono?.trim() ?? '';
    const hasStoredPhone = storedPhone !== '';
    const ownerName = useMemo(
        () => (pedido ? propietarioDisplayName(pedido) : ''),
        [pedido],
    );
    const docsCount = pedido ? pedidoDocumentCount(pedido) : 0;

    useEffect(() => {
        if (!open || !pedido) {
            return;
        }

        setTelefono(storedPhone);
        setError(null);
        setProcessing(false);
    }, [open, pedido, storedPhone]);

    const submit = () => {
        if (!pedido) {
            return;
        }

        const phoneToSend = telefono.trim();
        if (!hasStoredPhone && phoneToSend === '') {
            setError(t('whatsapp.phone_hint'));

            return;
        }

        setProcessing(true);
        setError(null);

        router.post(
            `/clinica/laboratorio/${pedido.id}/enviar-whatsapp`,
            { telefono: phoneToSend || undefined },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => {
                    const msg = errs.telefono ?? Object.values(errs)[0];
                    setError(
                        typeof msg === 'string' ? msg : t('whatsapp.phone_hint'),
                    );
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
                        {t('whatsapp.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {pedido
                            ? hasStoredPhone
                                ? t('whatsapp.description_with_phone', {
                                      paciente: pedido.paciente.nombre,
                                      propietario: ownerName,
                                      telefono: storedPhone,
                                      count: docsCount,
                                  })
                                : t('whatsapp.description_no_phone', {
                                      paciente: pedido.paciente.nombre,
                                      propietario: ownerName,
                                      count: docsCount,
                                  })
                            : null}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="lab-wa-phone">
                            {t('whatsapp.phone_label')}
                        </Label>
                        <Input
                            id="lab-wa-phone"
                            type="tel"
                            inputMode="tel"
                            placeholder={t('whatsapp.phone_placeholder')}
                            value={telefono}
                            onChange={(e) => setTelefono(e.target.value)}
                            disabled={processing}
                            aria-invalid={Boolean(error)}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('whatsapp.phone_hint')}
                        </p>
                    </div>

                    {error ? (
                        <p className="text-sm text-destructive">{error}</p>
                    ) : null}
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('whatsapp.cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || !pedido || docsCount === 0}
                        onClick={submit}
                        className="gap-2"
                    >
                        {processing ? (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden
                            />
                        ) : (
                            <MessageCircle className="size-4" aria-hidden />
                        )}
                        {processing
                            ? t('whatsapp.sending')
                            : t('whatsapp.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
