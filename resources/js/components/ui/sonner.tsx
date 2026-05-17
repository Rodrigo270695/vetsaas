import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';

/**
 * Toaster global de la aplicación.
 *
 * - Tema sincronizado con la apariencia (claro/oscuro/sistema).
 * - Estilo "rich" con iconos por tipo (success/error/info/warning).
 * - Animaciones suaves (slide + fade), respetuoso de `prefers-reduced-motion`.
 * - Conectado a las flash sessions de Laravel vía `useFlashToast`.
 */
function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="top-right"
            richColors
            closeButton
            expand
            duration={4000}
            toastOptions={{
                classNames: {
                    toast: 'group toast pointer-events-auto rounded-xl border border-border/60 bg-card text-foreground shadow-lg shadow-foreground/5 ring-1 ring-border/30 backdrop-blur-sm',
                    title: 'text-sm font-semibold',
                    description: 'text-xs text-muted-foreground',
                    actionButton:
                        'rounded-md bg-primary px-2.5 py-1 text-xs font-semibold text-primary-foreground cursor-pointer hover:bg-primary/90',
                    cancelButton:
                        'rounded-md bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground cursor-pointer hover:bg-muted/80',
                    closeButton:
                        'cursor-pointer rounded-md text-muted-foreground hover:bg-muted hover:text-foreground',
                },
            }}
            style={
                {
                    '--normal-bg': 'var(--card)',
                    '--normal-text': 'var(--foreground)',
                    '--normal-border': 'var(--border)',
                    '--success-bg': 'oklch(0.98 0.02 165)',
                    '--success-text': 'var(--primary)',
                    '--success-border': 'oklch(0.85 0.08 165)',
                    '--error-bg': 'oklch(0.97 0.03 25)',
                    '--error-text': 'var(--destructive)',
                    '--error-border': 'oklch(0.85 0.1 25)',
                    '--info-bg': 'oklch(0.97 0.03 240)',
                    '--info-text': 'oklch(0.5 0.18 240)',
                    '--info-border': 'oklch(0.85 0.1 240)',
                    '--warning-bg': 'oklch(0.98 0.04 80)',
                    '--warning-text': 'oklch(0.55 0.16 70)',
                    '--warning-border': 'oklch(0.85 0.12 75)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
