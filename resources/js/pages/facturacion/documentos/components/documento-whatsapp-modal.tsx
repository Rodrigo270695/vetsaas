import { router } from '@inertiajs/react';
import { FileText, Loader2, MessageCircle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { DocumentoDownloadRow } from './documento-download-menu';

export type DocumentoWhatsAppRow = DocumentoDownloadRow & {
    estado: string;
    receptor_nombre: string;
    cliente_telefono: string | null;
};

export type DocumentoWhatsAppModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documento: DocumentoWhatsAppRow | null;
};

type AttachmentKey = 'pdf_ticket' | 'pdf_a4' | 'xml' | 'cdr';

type AttachmentOption = {
    key: AttachmentKey;
    available: boolean;
};

function defaultAttachments(documento: DocumentoWhatsAppRow | null): Record<AttachmentKey, boolean> {
    if (!documento) {
        return { pdf_ticket: false, pdf_a4: false, xml: false, cdr: false };
    }

    return {
        pdf_ticket: Boolean(documento.url_pdf_ticket),
        pdf_a4: false,
        xml: documento.tiene_xml,
        cdr: documento.tiene_cdr,
    };
}

export function DocumentoWhatsAppModal({ open, onOpenChange, documento }: DocumentoWhatsAppModalProps) {
    const { t } = useTranslation('facturacion-documentos');
    const [telefono, setTelefono] = useState('');
    const [adjuntos, setAdjuntos] = useState<Record<AttachmentKey, boolean>>(defaultAttachments(null));
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const hasStoredPhone = Boolean(documento?.cliente_telefono?.trim());

    const options: AttachmentOption[] = useMemo(
        () => [
            { key: 'pdf_ticket', available: Boolean(documento?.url_pdf_ticket) },
            { key: 'pdf_a4', available: Boolean(documento?.url_pdf_a4) },
            { key: 'xml', available: Boolean(documento?.tiene_xml) },
            { key: 'cdr', available: Boolean(documento?.tiene_cdr) },
        ],
        [documento],
    );

    const selectedCount = useMemo(
        () => options.filter((opt) => opt.available && adjuntos[opt.key]).length,
        [adjuntos, options],
    );

    useEffect(() => {
        if (!open || !documento) {
            return;
        }

        setTelefono(documento.cliente_telefono?.trim() ?? '');
        setAdjuntos(defaultAttachments(documento));
        setError(null);
        setProcessing(false);
    }, [open, documento]);

    const toggleAdjunto = (key: AttachmentKey, checked: boolean) => {
        setAdjuntos((prev) => ({ ...prev, [key]: checked }));
    };

    const submit = () => {
        if (!documento) {
            return;
        }

        const phoneToSend = telefono.trim();
        if (!hasStoredPhone && phoneToSend === '') {
            setError(t('whatsapp.phone_hint'));

            return;
        }

        if (selectedCount === 0) {
            setError(t('whatsapp.sin_adjuntos'));

            return;
        }

        setProcessing(true);
        setError(null);

        router.post(
            `/facturacion/documentos/${documento.id}/enviar-whatsapp`,
            {
                telefono: phoneToSend || undefined,
                pdf_ticket: adjuntos.pdf_ticket,
                pdf_a4: adjuntos.pdf_a4,
                xml: adjuntos.xml,
                cdr: adjuntos.cdr,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
                onError: (errs) => {
                    const msg = errs.telefono ?? Object.values(errs)[0];
                    setError(typeof msg === 'string' ? msg : t('whatsapp.phone_hint'));
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
                        {documento
                            ? hasStoredPhone
                                ? t('whatsapp.description_with_phone', {
                                      numero: documento.numero_completo,
                                      cliente: documento.receptor_nombre,
                                      telefono: documento.cliente_telefono,
                                  })
                                : t('whatsapp.description_no_phone', {
                                      cliente: documento.receptor_nombre,
                                  })
                            : null}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="doc-wa-phone">{t('whatsapp.phone_label')}</Label>
                        <Input
                            id="doc-wa-phone"
                            type="tel"
                            inputMode="tel"
                            placeholder={t('whatsapp.phone_placeholder')}
                            value={telefono}
                            onChange={(e) => setTelefono(e.target.value)}
                            disabled={processing}
                            aria-invalid={Boolean(error)}
                        />
                        <p className="text-xs text-muted-foreground">{t('whatsapp.phone_hint')}</p>
                    </div>

                    <div className="grid gap-2 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5">
                        <Label className="text-xs font-medium text-foreground">
                            {t('whatsapp.adjuntos_label')}
                        </Label>
                        <div className="grid gap-2">
                            {options.map((opt) => (
                                <label
                                    key={opt.key}
                                    className={`flex cursor-pointer items-center gap-2.5 rounded-md border px-2.5 py-2 text-sm ${
                                        opt.available
                                            ? 'border-border/60 bg-background'
                                            : 'cursor-not-allowed border-dashed border-border/40 bg-muted/30 opacity-60'
                                    }`}
                                >
                                    <Checkbox
                                        checked={adjuntos[opt.key]}
                                        disabled={processing || !opt.available}
                                        onCheckedChange={(checked) =>
                                            toggleAdjunto(opt.key, checked === true)
                                        }
                                    />
                                    <FileText className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                                    <span className="flex-1">{t(`whatsapp.adjunto_${opt.key}`)}</span>
                                    {!opt.available ? (
                                        <span className="text-[0.65rem] text-muted-foreground">
                                            {t('whatsapp.no_disponible')}
                                        </span>
                                    ) : null}
                                </label>
                            ))}
                        </div>
                        <p className="text-[0.7rem] leading-snug text-muted-foreground">
                            {t('whatsapp.adjuntos_hint')}
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
                        {t('whatsapp.cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || !documento || selectedCount === 0}
                        onClick={submit}
                        className="gap-2"
                    >
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                        ) : (
                            <MessageCircle className="size-4" aria-hidden />
                        )}
                        {processing ? t('whatsapp.sending') : t('whatsapp.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
