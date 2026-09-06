import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';

export function PortalThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <button
            type="button"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            className="inline-flex size-11 min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-full bg-slate-100 text-slate-800 ring-1 ring-slate-200/80 transition hover:bg-slate-200 dark:bg-white/10 dark:text-white dark:ring-white/10"
            aria-label={isDark ? 'Modo claro' : 'Modo oscuro'}
        >
            {isDark ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </button>
    );
}
