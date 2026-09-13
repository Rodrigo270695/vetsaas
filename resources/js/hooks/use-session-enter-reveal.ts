import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import {
    endSessionEnter,
    restoreSessionEnterClass,
} from '@/lib/session-enter';

const CHROME_MS = 2100;

/**
 * Quita la clase de ingreso tras la animación del chrome.
 * El CSS aplica en el primer paint; esto solo cierra el ciclo.
 */
export function useSessionEnterReveal(): void {
    useEffect(() => {
        if (!restoreSessionEnterClass()) {
            return;
        }

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const wait = reduce ? 80 : CHROME_MS;
        const timer = window.setTimeout(() => {
            endSessionEnter();
        }, wait);

        const offStart = router.on('start', endSessionEnter);

        return () => {
            window.clearTimeout(timer);
            offStart();
        };
    }, []);
}
