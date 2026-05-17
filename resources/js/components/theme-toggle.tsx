import { Monitor, Moon, Sun } from 'lucide-react';
import {
    type Appearance,
    useAppearance,
} from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const NEXT_APPEARANCE: Record<Appearance, Appearance> = {
    light: 'dark',
    dark: 'system',
    system: 'light',
};

const APPEARANCE_LABEL: Record<Appearance, string> = {
    light: 'Tema: claro',
    dark: 'Tema: oscuro',
    system: 'Tema: sistema',
};

const APPEARANCE_ICON = {
    light: Sun,
    dark: Moon,
    system: Monitor,
} as const;

type ThemeToggleProps = {
    className?: string;
};

/**
 * Botón compacto que cicla light → dark → system.
 * Pensado para headers/toolbars; respeta la paleta semántica.
 */
export default function ThemeToggle({ className }: ThemeToggleProps) {
    const { appearance, updateAppearance } = useAppearance();
    const Icon = APPEARANCE_ICON[appearance];
    const label = APPEARANCE_LABEL[appearance];

    return (
        <button
            type="button"
            onClick={() => updateAppearance(NEXT_APPEARANCE[appearance])}
            aria-label={label}
            title={label}
            className={cn(
                'inline-flex size-9 cursor-pointer items-center justify-center rounded-full border border-border/70 bg-card/70 text-muted-foreground backdrop-blur transition-colors hover:border-primary/40 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                className,
            )}
        >
            <Icon className="size-4" />
        </button>
    );
}
