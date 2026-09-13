import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import {
    clearPendingViewEnter,
    consumePendingViewEnter,
    endSessionEnter,
    endViewEnter,
    isPartialOrPrefetchVisit,
    markViewEnter,
    restoreSessionEnterClass,
} from '@/lib/session-enter';

const SESSION_CHROME_MS = 2100;
const VIEW_CHROME_MS = 720;

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
 * Login: entrada completa. Clic en el sidebar: entrada de vista.
 * Acciones dentro de la pantalla (crear, editar, eliminar, filtros) no animan.
 */
export function useSessionEnterReveal(): void {
    const viewTimer = useRef(0);

    useEffect(() => {
        if (!restoreSessionEnterClass()) {
            return;
        }

        const wait = prefersReducedMotion() ? 80 : SESSION_CHROME_MS;
        const timer = window.setTimeout(() => {
            endSessionEnter();
        }, wait);

        return () => window.clearTimeout(timer);
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
            if (!consumePendingViewEnter()) {
                return;
            }

            markViewEnter();
            window.clearTimeout(viewTimer.current);
            viewTimer.current = window.setTimeout(() => {
                endViewEnter();
            }, reduce ? 80 : VIEW_CHROME_MS);
        });

        const offCancel = router.on('cancel', () => {
            clearPendingViewEnter();
        });

        return () => {
            offStart();
            offFinish();
            offCancel();
            window.clearTimeout(viewTimer.current);
        };
    }, []);
}
