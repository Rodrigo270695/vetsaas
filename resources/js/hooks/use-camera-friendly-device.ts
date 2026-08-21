import { useSyncExternalStore } from 'react';

/**
 * Celular / tablet (viewport ≤ 1023px) o app instalada (PWA standalone).
 * Usado para mostrar captura con cámara + galería.
 */
const COMPACT_QUERY = '(max-width: 1023px)';

function isStandalonePwa(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    if (window.matchMedia('(display-mode: standalone)').matches) {
        return true;
    }

    const nav = window.navigator as Navigator & { standalone?: boolean };

    return Boolean(nav.standalone);
}

function subscribe(callback: () => void) {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const mql = window.matchMedia(COMPACT_QUERY);
    const onChange = () => callback();
    mql.addEventListener('change', onChange);
    // display-mode no dispara change al instalar en todos los browsers;
    // el viewport sí cubre tablet/phone en uso normal.
    return () => mql.removeEventListener('change', onChange);
}

function getSnapshot(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia(COMPACT_QUERY).matches || isStandalonePwa();
}

function getServerSnapshot(): boolean {
    return false;
}

export function useIsCameraFriendlyDevice(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
