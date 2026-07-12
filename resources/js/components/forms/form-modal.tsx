import { useRef, type FormEvent, type ReactNode } from 'react';
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
 * Modal estándar para formularios de crear/editar.
 *
 * - Cabecera con tinte pastel del color de marca del tenant.
 * - Body con scroll nativo cuando el contenido excede la pantalla.
 * - Footer sticky con slot libre (cancelar/guardar/etc).
 * - Si pasas `onSubmit`, el body se envuelve en `<form>` y captura Enter.
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

    const ScrollArea = (
        <div className="relative min-h-0">
            <div
                ref={scrollRef}
                className="max-h-[min(62vh,calc(100dvh-11.5rem))] overflow-y-auto overscroll-contain px-1 py-3 sm:max-h-[min(68vh,calc(100dvh-10.5rem))]"
            >
                {children}
            </div>

            <div
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-x-0 top-0 h-8 bg-linear-to-b from-card via-card/80 to-transparent transition-opacity duration-300',
                    overflowTop ? 'opacity-100' : 'opacity-0',
                )}
            />

            <div
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-x-0 bottom-0 h-10 bg-linear-to-t from-card via-card/90 to-transparent transition-opacity duration-300',
                    overflowBottom ? 'opacity-100' : 'opacity-0',
                )}
            />
        </div>
    );

    const bodyWrapperClass = 'relative flex min-h-0 flex-1 flex-col overflow-hidden';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                data-form-modal
                className={cn(
                    'flex max-h-[calc(100dvh-1.5rem)] flex-col gap-0 overflow-hidden border-border/60 bg-card p-0 shadow-xl shadow-foreground/8 ring-1 ring-border/50 dark:border-border/80 dark:shadow-black/25 dark:ring-border/60',
                    'data-[state=open]:animate-in data-[state=closed]:animate-out',
                    'data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0',
                    'data-[state=open]:zoom-in-[0.97] data-[state=closed]:zoom-out-[0.98]',
                    'data-[state=open]:slide-in-from-bottom-4 data-[state=closed]:slide-out-to-bottom-3',
                    'data-[state=open]:duration-500 data-[state=open]:ease-[cubic-bezier(0.16,1,0.3,1)]',
                    'data-[state=closed]:duration-250 data-[state=closed]:ease-in',
                    '[&_[data-slot=dialog-close]]:top-5 [&_[data-slot=dialog-close]]:text-muted-foreground [&_[data-slot=dialog-close]]:hover:bg-brand-50/60 [&_[data-slot=dialog-close]]:hover:text-foreground dark:[&_[data-slot=dialog-close]]:hover:bg-brand-950/30',
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
                <DialogHeader className="shrink-0 border-b border-border/50 bg-linear-to-br from-brand-50/35 via-brand-50/15 to-card px-6 pt-6 pb-4 dark:border-border/60 dark:from-brand-950/25 dark:via-brand-950/10 dark:to-card">
                    <DialogTitle className="pr-8 text-lg font-semibold tracking-tight text-foreground">
                        {title}
                    </DialogTitle>
                    {description ? (
                        <DialogDescription className="text-sm text-muted-foreground">
                            {description}
                        </DialogDescription>
                    ) : null}
                </DialogHeader>

                {onSubmit ? (
                    <form
                        onSubmit={onSubmit}
                        className={bodyWrapperClass}
                        noValidate
                    >
                        <div className="min-h-0 flex-1 overflow-hidden px-6 pt-1">{ScrollArea}</div>
                        {footer ? (
                            <DialogFooter className="mt-0 shrink-0 gap-2 border-t border-border/50 bg-muted/25 px-6 py-4 sm:justify-end dark:bg-muted/10">
                                {footer}
                            </DialogFooter>
                        ) : null}
                    </form>
                ) : (
                    <div className={bodyWrapperClass}>
                        <div className="min-h-0 flex-1 overflow-hidden px-6 pt-1">{ScrollArea}</div>
                        {footer ? (
                            <DialogFooter className="mt-0 shrink-0 gap-2 border-t border-border/50 bg-muted/25 px-6 py-4 sm:justify-end dark:bg-muted/10">
                                {footer}
                            </DialogFooter>
                        ) : null}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
