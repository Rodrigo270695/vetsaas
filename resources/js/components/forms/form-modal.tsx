import { ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useScrollHint } from '@/hooks/use-scroll-hint';
import { cn } from '@/lib/utils';

export type FormModalSize = 'sm' | 'md' | 'lg' | 'xl';

export type FormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    /** Tamaño máximo del modal (clases tailwind aplicadas internamente). */
    size?: FormModalSize;
    /** Si se provee, el body se envuelve en un `<form>` y dispara este handler. */
    onSubmit?: (event: FormEvent<HTMLFormElement>) => void;
    /** Slot del footer (botones cancelar/guardar). */
    footer?: ReactNode;
    /**
     * Si true (default), bloquea el cierre del modal cuando el usuario hace
     * click **fuera** del modal. Útil para que un click accidental no
     * descarte un formulario a medio escribir.
     *
     * Nota: la tecla `Escape` **siempre** cierra el modal (estándar de
     * accesibilidad). Si quieres también bloquearlo, usa `blockEscape`.
     */
    blockDismiss?: boolean;
    /**
     * Si true, también bloquea el cierre con la tecla `Escape`.
     * Default: false (Escape siempre cierra).
     */
    blockEscape?: boolean;
    children: ReactNode;
    className?: string;
};

const sizeClasses: Record<FormModalSize, string> = {
    sm: 'sm:max-w-md',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
};

/**
 * Modal padre para formularios de crear/editar.
 *
 * Características:
 * - Header con título y descripción opcional.
 * - Body con scroll cuando el contenido excede la pantalla (max-h dinámico).
 * - Footer sticky con slot libre (cancelar/guardar/etc).
 * - Si pasas `onSubmit`, el body se envuelve en `<form>` y captura Enter.
 * - Responsive: ocupa casi toda la pantalla en móvil, max-width en desktop.
 */
export function FormModal({
    open,
    onOpenChange,
    title,
    description,
    size = 'md',
    onSubmit,
    footer,
    blockDismiss = true,
    blockEscape = false,
    children,
    className,
}: FormModalProps) {
    const scrollRef = useRef<HTMLDivElement>(null);
    const { overflowTop, overflowBottom } = useScrollHint(scrollRef, open);

    // Hint inicial: cuando el modal recién se abre, mostramos el chip
    // durante unos segundos aunque la detección aún no haya corrido.
    // Esto garantiza que el usuario VEA el indicador visual ni bien se
    // abre el modal, sin depender 100% del timing del ResizeObserver.
    const [initialHint, setInitialHint] = useState(false);
    useEffect(() => {
        if (!open) {
            setInitialHint(false);
            return;
        }
        // Esperamos un frame para que el modal termine de animar entrada.
        const start = window.setTimeout(() => setInitialHint(true), 400);
        const end = window.setTimeout(() => setInitialHint(false), 4000);
        return () => {
            window.clearTimeout(start);
            window.clearTimeout(end);
        };
    }, [open]);

    const showHint = overflowBottom || initialHint;

    const ScrollArea = (
        <div className="relative">
            <div
                ref={scrollRef}
                className="scrollbar-hidden max-h-[min(50vh,calc(100dvh-14rem))] overflow-y-auto px-1 py-3 sm:max-h-[min(55vh,calc(100dvh-13rem))]"
            >
                {children}
            </div>

            {/* Fade superior: aparece solo cuando el usuario ya hizo scroll */}
            <div
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-x-0 top-0 h-8 bg-linear-to-b from-card via-card/80 to-transparent transition-opacity duration-200',
                    overflowTop ? 'opacity-100' : 'opacity-0',
                )}
            />

            {/* Fade inferior: degradado que insinúa contenido oculto */}
            <div
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-linear-to-t from-card via-card/85 to-transparent transition-opacity duration-300',
                    showHint ? 'opacity-100' : 'opacity-0',
                )}
            />
        </div>
    );

    const ScrollHintChip = (
        <div
            aria-hidden
            className={cn(
                'pointer-events-none absolute inset-x-0 z-30 flex justify-center transition-opacity duration-300',
                // Posicionado justo encima del footer del modal (footer ≈ 64-72px).
                footer ? 'bottom-[4.5rem]' : 'bottom-6',
                showHint ? 'opacity-100' : 'opacity-0',
            )}
        >
            <div className="animate-scroll-hint flex items-center gap-1.5 rounded-full bg-primary px-3 py-1.5 text-[11px] font-semibold tracking-wide text-primary-foreground shadow-lg shadow-primary/30 ring-1 ring-primary/40">
                <span>Hay más campos</span>
                <ChevronDown className="size-3.5" strokeWidth={3} />
            </div>
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(
                    // max-h: nunca exceder el alto del viewport. flex column para
                    // que el scrolleable interior pueda usar min-h-0 + max-h y
                    // realmente recortar contenido (en vez de empujar el modal).
                    'flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0 shadow-2xl shadow-foreground/15 ring-1 ring-border/40',
                    // Entrada: aparece desde abajo con un sutil escala + fade,
                    // usando un timing cubic-bezier "spring-out" que da una
                    // sensación más fluida y profesional que la default.
                    'data-[state=open]:duration-400 data-[state=open]:ease-[cubic-bezier(0.16,1,0.3,1)]',
                    'data-[state=open]:slide-in-from-bottom-6 data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    // Salida: más rápida y discreta.
                    'data-[state=closed]:duration-200 data-[state=closed]:ease-in',
                    'data-[state=closed]:slide-out-to-bottom-4 data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                    sizeClasses[size],
                    className,
                )}
                onPointerDownOutside={
                    blockDismiss ? (event) => event.preventDefault() : undefined
                }
                onInteractOutside={
                    blockDismiss ? (event) => event.preventDefault() : undefined
                }
                onEscapeKeyDown={
                    blockEscape ? (event) => event.preventDefault() : undefined
                }
            >
                <DialogHeader className="border-b border-border/60 px-6 pt-6 pb-4">
                    <DialogTitle className="text-lg font-semibold tracking-tight">
                        {title}
                    </DialogTitle>
                    {description && (
                        <DialogDescription className="text-sm text-muted-foreground">
                            {description}
                        </DialogDescription>
                    )}
                </DialogHeader>

                {onSubmit ? (
                    <form
                        onSubmit={onSubmit}
                        className="relative flex min-h-0 flex-1 flex-col overflow-hidden"
                        noValidate
                    >
                        <div className="min-h-0 flex-1 overflow-hidden px-6 pt-1">
                            {ScrollArea}
                        </div>
                        {footer ? (
                            <DialogFooter className="mt-0 shrink-0 gap-2 border-t border-border/60 bg-card px-6 py-4 sm:justify-end">
                                {footer}
                            </DialogFooter>
                        ) : null}
                        {ScrollHintChip}
                    </form>
                ) : (
                    <div className="relative flex min-h-0 flex-1 flex-col overflow-hidden">
                        <div className="min-h-0 flex-1 overflow-hidden px-6 pt-1">
                            {ScrollArea}
                        </div>
                        {footer ? (
                            <DialogFooter className="mt-0 shrink-0 gap-2 border-t border-border/60 bg-card px-6 py-4 sm:justify-end">
                                {footer}
                            </DialogFooter>
                        ) : null}
                        {ScrollHintChip}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
