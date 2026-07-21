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
import {
    buildTicketPreviewUrl,
    normalizeTicketAncho,
    resolveTicketAncho,
    TICKET_ANCHO_OPTIONS,
} from '@/lib/ticket-ancho';
import type { TicketAnchoMm } from '@/lib/ticket-ancho';

export type TicketPrintDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** URL base del ticket (sin query de ancho). */
    ticketBaseUrl: string;
    /** Valor por defecto desde Configuración → General. */
    configAncho: TicketAnchoMm;
    title: string;
    description: string;
    iframeTitle: string;
    printLabel: string;
    autoPrint?: boolean;
    onAutoPrintConsumed?: () => void;
};

/**
 * Modal de vista previa e impresión de ticket térmico.
 * Permite elegir 56 / 58 / 80 mm en cada impresión.
 * Cada apertura usa como base la configuración vigente del tenant.
 */
export function TicketPrintDialog({
    open,
    onOpenChange,
    ticketBaseUrl,
    configAncho,
    title,
    description,
    iframeTitle,
    printLabel,
    autoPrint = false,
    onAutoPrintConsumed,
}: TicketPrintDialogProps) {
    const { t } = useTranslation(['common']);
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const [ancho, setAncho] = useState<TicketAnchoMm>(() =>
        resolveTicketAncho(normalizeTicketAncho(configAncho)),
    );
    const [iframeBust, setIframeBust] = useState(0);

    useEffect(() => {
        if (open) {
            // Reinicia la selección cada vez que el modal usa la configuración del tenant.
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setAncho(resolveTicketAncho(normalizeTicketAncho(configAncho)));
            setIframeBust((value) => value + 1);
        }
    }, [open, configAncho]);

    const iframeSrc = useMemo(
        () =>
            buildTicketPreviewUrl(
                ticketBaseUrl,
                ancho,
                iframeBust,
                autoPrint,
            ),
        [ticketBaseUrl, ancho, iframeBust, autoPrint],
    );

    const handleAnchoChange = (next: TicketAnchoMm) => {
        setAncho(next);
        setIframeBust((value) => value + 1);
    };

    const imprimir = () => {
        const win = iframeRef.current?.contentWindow;

        if (win) {
            win.focus();
            win.print();
        }
    };

    const handleOpenChange = (next: boolean) => {
        onOpenChange(next);

        if (!next) {
            onAutoPrintConsumed?.();
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="flex max-h-[90vh] max-w-[calc(100%-1rem)] flex-col gap-3 p-4 sm:max-w-2xl sm:p-6">
                <DialogHeader className="shrink-0 space-y-1 pr-8 text-left">
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div className="flex shrink-0 flex-col gap-2 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5">
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
                                onClick={() => handleAnchoChange(value)}
                            >
                                {t(`common:ticket_ancho.${value}`)}
                            </Button>
                        ))}
                    </div>
                    <p className="text-[0.7rem] leading-snug text-muted-foreground">
                        {t('common:ticket_ancho.hint')}
                    </p>
                </div>

                {open ? (
                    <iframe
                        ref={iframeRef}
                        title={iframeTitle}
                        src={iframeSrc}
                        className="min-h-[50vh] w-full flex-1 rounded-md border border-border bg-white"
                    />
                ) : null}

                <DialogFooter className="shrink-0 gap-2 sm:justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleOpenChange(false)}
                        className="cursor-pointer"
                    >
                        {t('common:actions.close')}
                    </Button>
                    <Button
                        type="button"
                        className="cursor-pointer gap-1.5"
                        onClick={imprimir}
                    >
                        <Printer className="size-4 shrink-0" aria-hidden />
                        {printLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
