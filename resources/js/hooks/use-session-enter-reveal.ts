import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import {
    endSessionEnter,
    endViewEnter,
    isPartialOrPrefetchVisit,
    markViewEnter,
    restoreSessionEnterClass,
} from '@/lib/session-enter';

const SESSION_CHROME_MS = 2100;
const VIEW_CHROME_MS = 1150;

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function isLoginVisit(visit: { method: string; url: URL | string }): boolean {
    if (String(visit.method).toLowerCase() !== 'post') {
        return false;
    }

    const path = (
        typeof visit.url === 'string'
            ? new URL(visit.url, window.location.origin)
            : visit.url
    ).pathname.replace(/\/+$/, '');

    return path === '/login' || path.endsWith('/login');
}

/**
 * Entrada de chrome: login completo (sidebar + header + layout) y,
 * en cada vista siguiente, header + contenido con skeleton.
 */
export function useSessionEnterReveal(): void {
    const viewTimer = useRef(0);

    useEffect(() => {
        const reduce = prefersReducedMotion();

        if (restoreSessionEnterClass()) {
            const timer = window.setTimeout(() => {
                endSessionEnter();
            }, reduce ? 80 : SESSION_CHROME_MS);

            return () => window.clearTimeout(timer);
        }

        markViewEnter();
        viewTimer.current = window.setTimeout(() => {
            endViewEnter();
        }, reduce ? 80 : VIEW_CHROME_MS);

        return () => window.clearTimeout(viewTimer.current);
    }, []);

    useEffect(() => {
        const reduce = prefersReducedMotion();

        const offStart = router.on('start', (event) => {
            const visit = event.detail.visit;
            if (isPartialOrPrefetchVisit(visit) || isLoginVisit(visit)) {
                return;
            }
            endSessionEnter();
        });

        const offFinish = router.on('finish', (event) => {
            const visit = event.detail.visit;
            if (isPartialOrPrefetchVisit(visit) || isLoginVisit(visit)) {
                return;
            }
            if (document.documentElement.classList.contains('session-enter')) {
                return;
            }

            markViewEnter();
            window.clearTimeout(viewTimer.current);
            viewTimer.current = window.setTimeout(() => {
                endViewEnter();
            }, reduce ? 80 : VIEW_CHROME_MS);
        });

        return () => {
            offStart();
            offFinish();
            window.clearTimeout(viewTimer.current);
        };
    }, []);
}
