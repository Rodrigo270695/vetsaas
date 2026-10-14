import { router } from '@inertiajs/react';
import { Loader2, Mail, MessageCircle, TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import type { CotizacionRow } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    cotizacion: CotizacionRow | null;
};

export function CotizacionWhatsAppDialog({ open, onOpenChange, cotizacion }: Props) {
    const [telefono, setTelefono] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setTelefono(cotizacion?.cliente_telefono ?? '');
            setError(null);
            setProcessing(false);
        }
    }, [open, cotizacion]);

    const submit = () => {
        if (!cotizacion) {
            return;
        }
        if (telefono.trim() === '') {
            setError('Indica el número de WhatsApp del cliente.');

            return;
        }
        setProcessing(true);
        router.post(
            `/caja/cotizaciones/${cotizacion.id}/enviar-whatsapp`,
            { telefono: telefono.trim() },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => setError(Object.values(errs)[0] ?? 'No se pudo enviar.'),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <MessageCircle className="size-5 text-emerald-600" />
                        Enviar por WhatsApp
                    </DialogTitle>
                    <DialogDescription>
                        Se enviará el PDF de la cotización {cotizacion?.numero} a {cotizacion?.cliente_nombre} desde el
                        WhatsApp de la clínica.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2 py-2">
                    <Label htmlFor="cot-wa-phone">Número de WhatsApp</Label>
                    <Input
                        id="cot-wa-phone"
                        type="tel"
                        inputMode="tel"
                        placeholder="987654321"
                        value={telefono}
                        disabled={processing}
                        onChange={(e) => setTelefono(e.target.value)}
                        aria-invalid={Boolean(error)}
                    />
                    <p className="text-xs text-muted-foreground">Número de Perú de 9 dígitos o con código de país.</p>
                    {error ? <p className="text-sm text-destructive">{error}</p> : null}
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button type="button" className="gap-2" disabled={processing || !cotizacion} onClick={submit}>
                        {processing ? <Loader2 className="size-4 animate-spin" /> : <MessageCircle className="size-4" />}
                        {processing ? 'Enviando…' : 'Enviar PDF'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function CotizacionCorreoDialog({ open, onOpenChange, cotizacion }: Props) {
    const [email, setEmail] = useState('');
    const [mensaje, setMensaje] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setEmail(cotizacion?.cliente_email ?? '');
            setMensaje('');
            setError(null);
            setProcessing(false);
        }
    }, [open, cotizacion]);

    const submit = () => {
        if (!cotizacion) {
            return;
        }
        if (email.trim() === '') {
            setError('Indica el correo del cliente.');

            return;
        }
        setProcessing(true);
        router.post(
            `/caja/cotizaciones/${cotizacion.id}/enviar-correo`,
            { email: email.trim(), mensaje: mensaje.trim() || null },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => setError(errs.email ?? Object.values(errs)[0] ?? 'No se pudo enviar.'),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Mail className="size-5 text-primary" />
                        Enviar por correo
                    </DialogTitle>
                    <DialogDescription>
                        Se enviará la cotización {cotizacion?.numero} con el PDF adjunto.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="cot-mail">Correo del cliente</Label>
                        <Input
                            id="cot-mail"
                            type="email"
                            value={email}
                            disabled={processing}
                            onChange={(e) => setEmail(e.target.value)}
                            aria-invalid={Boolean(error)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cot-mail-msg">Mensaje (opcional)</Label>
                        <Textarea
                            id="cot-mail-msg"
                            rows={3}
                            maxLength={1000}
                            value={mensaje}
                            disabled={processing}
                            onChange={(e) => setMensaje(e.target.value)}
                        />
                    </div>
                    {error ? <p className="text-sm text-destructive">{error}</p> : null}
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button type="button" className="gap-2" disabled={processing || !cotizacion} onClick={submit}>
                        {processing ? <Loader2 className="size-4 animate-spin" /> : <Mail className="size-4" />}
                        {processing ? 'Enviando…' : 'Enviar correo'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function CotizacionDeleteDialog({ open, onOpenChange, cotizacion }: Props) {
    const [processing, setProcessing] = useState(false);

    const onConfirm = () => {
        if (!cotizacion) {
            return;
        }
        setProcessing(true);
        router.delete(`/caja/cotizaciones/${cotizacion.id}`, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <TriangleAlert className="size-5" strokeWidth={2.5} />
                    </div>
                    <DialogTitle className="pt-2 text-base">Eliminar cotización</DialogTitle>
                    <DialogDescription>
                        {cotizacion ? `${cotizacion.numero} · ${cotizacion.cliente_nombre}` : '—'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button type="button" variant="destructive" disabled={processing} onClick={onConfirm}>
                        {processing ? <Loader2 className="mr-2 size-4 animate-spin" /> : null}
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
