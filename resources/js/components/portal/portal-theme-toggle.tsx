import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';

export function PortalThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <button
            type="button"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            className="inline-flex size-10 items-center justify-center rounded-full bg-white/80 text-foreground shadow-sm ring-1 ring-black/5 backdrop-blur dark:bg-white/10 dark:ring-white/10"
            aria-label={isDark ? 'Modo claro' : 'Modo oscuro'}
        >
            {isDark ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </button>
    );
}
