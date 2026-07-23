import { Printer } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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
import { normalizeTicketAncho } from '@/lib/ticket-ancho';
import type { TicketAnchoMm } from '@/lib/ticket-ancho';

export type ArqueoPrintFormato = 'a4' | TicketAnchoMm;

const FORMATOS: readonly ArqueoPrintFormato[] = ['a4', '56', '58', '80'];

export type ArqueoPrintDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** URL base del PDF de arqueo (sin query formato). */
    pdfBaseUrl: string;
    /** Ancho térmico por defecto (Configuración → General). */
    configAncho: TicketAnchoMm;
};

function buildArqueoPreviewUrl(baseUrl: string, formato: ArqueoPrintFormato, bust: number): string {
    const params = new URLSearchParams({
        formato,
        _pv: String(bust),
    });
    const sep = baseUrl.includes('?') ? '&' : '?';

    return `${baseUrl}${sep}${params.toString()}`;
}

/**
 * Vista previa e impresión del arqueo: A4 (reporte) o ticket 56/58/80 mm (resumen).
 */
export function ArqueoPrintDialog({
    open,
    onOpenChange,
    pdfBaseUrl,
    configAncho,
}: ArqueoPrintDialogProps) {
    const { t } = useTranslation(['caja', 'common']);
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const [formato, setFormato] = useState<ArqueoPrintFormato>('a4');
    const [iframeBust, setIframeBust] = useState(0);

    useEffect(() => {
        if (open) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setFormato('a4');
            setIframeBust((value) => value + 1);
        }
    }, [open, configAncho]);

    const iframeSrc = useMemo(
        () => buildArqueoPreviewUrl(pdfBaseUrl, formato, iframeBust),
        [pdfBaseUrl, formato, iframeBust],
    );

    const handleFormatoChange = (next: ArqueoPrintFormato) => {
        setFormato(next);
        setIframeBust((value) => value + 1);
    };

    const imprimir = () => {
        const win = iframeRef.current?.contentWindow;
        if (win) {
            win.focus();
            win.print();
        }
    };

    const hintKey =
        formato === 'a4'
            ? 'sesiones.dialog_imprimir.hint_a4'
            : 'sesiones.dialog_imprimir.hint_ticket';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] max-w-[calc(100%-1rem)] flex-col gap-3 p-4 sm:max-w-2xl sm:p-6">
                <DialogHeader className="shrink-0 space-y-1 pr-8 text-left">
                    <DialogTitle>{t('sesiones.dialog_imprimir.title')}</DialogTitle>
                    <DialogDescription>{t('sesiones.dialog_imprimir.description')}</DialogDescription>
                </DialogHeader>

                <div className="flex shrink-0 flex-col gap-2 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5">
                    <Label className="text-xs font-medium text-foreground">
                        {t('sesiones.dialog_imprimir.format_label')}
                    </Label>
                    <div
                        className="flex flex-wrap gap-2"
                        role="radiogroup"
                        aria-label={t('sesiones.dialog_imprimir.format_label')}
                    >
                        {FORMATOS.map((value) => (
                            <Button
                                key={value}
                                type="button"
                                size="sm"
                                variant={formato === value ? 'default' : 'outline'}
                                className="h-8 cursor-pointer px-3 text-xs"
                                role="radio"
                                aria-checked={formato === value}
                                onClick={() => handleFormatoChange(value)}
                            >
                                {value === 'a4'
                                    ? t('sesiones.dialog_imprimir.format_a4')
                                    : t(`common:ticket_ancho.${value}`)}
                            </Button>
                        ))}
                    </div>
                    <p className="text-[0.7rem] leading-snug text-muted-foreground">{t(hintKey)}</p>
                    {formato !== 'a4' ? (
                        <p className="text-[0.7rem] leading-snug text-muted-foreground">
                            {t('sesiones.dialog_imprimir.hint_config', {
                                ancho: t(`common:ticket_ancho.${normalizeTicketAncho(configAncho)}`),
                            })}
                        </p>
                    ) : null}
                </div>

                {open ? (
                    <iframe
                        ref={iframeRef}
                        title={t('sesiones.dialog_imprimir.iframe_title')}
                        src={iframeSrc}
                        className="min-h-[50vh] w-full flex-1 rounded-md border border-border bg-white"
                    />
                ) : null}

                <DialogFooter className="shrink-0 gap-2 sm:justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        className="cursor-pointer"
                    >
                        {t('common:actions.close')}
                    </Button>
                    <Button type="button" className="cursor-pointer gap-1.5" onClick={imprimir}>
                        <Printer className="size-4 shrink-0" aria-hidden />
                        {t('sesiones.dialog_imprimir.print')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
