/**
 * Recupera PWAs instaladas que se quedan en el splash al abrir
 * (SW que toma el control a mitad de carga, caché de /build/ viejo).
 */
const SW_RELOAD_KEY = 'vetsaas-sw-reload';

declare global {
    interface Window {
        __vetsaasHideBoot?: () => void;
    }
}

export function recoverPwaBoot(): void {
    window.__vetsaasHideBoot?.();

    if (!('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (sessionStorage.getItem(SW_RELOAD_KEY) === '1') {
            return;
        }

        sessionStorage.setItem(SW_RELOAD_KEY, '1');
        window.location.reload();
    });

    window.addEventListener('load', () => {
        window.setTimeout(() => {
            sessionStorage.removeItem(SW_RELOAD_KEY);
        }, 5000);
    });
}
