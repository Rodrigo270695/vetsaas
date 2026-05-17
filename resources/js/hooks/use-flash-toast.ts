import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toastManager, type ToastType } from '@/lib/toast';

type FlashShape = {
    id?: string | null;
    success?: string | null;
    error?: string | null;
    info?: string | null;
    warning?: string | null;
};

const FLASH_KEYS: { key: keyof FlashShape; type: ToastType }[] = [
    { key: 'success', type: 'success' },
    { key: 'error', type: 'error' },
    { key: 'info', type: 'info' },
    { key: 'warning', type: 'warning' },
];

function showFlashes(flash: FlashShape): void {
    for (const { key, type } of FLASH_KEYS) {
        const message = flash[key];

        if (typeof message === 'string' && message.length > 0) {
            toastManager.add({ type, title: message });
        }
    }
}

type InertiaPage = { props?: { flash?: FlashShape | null } };
type InertiaEvent = CustomEvent<{ page?: InertiaPage }>;

/**
 * Lee las flash sessions de Laravel compartidas por
 * `HandleInertiaRequests` (`flash.success`, `flash.error`, etc.) y las
 * convierte automáticamente en toasts.
 *
 * Importante: este hook se monta en el `<Toaster>` global, que vive
 * **fuera** del provider de Inertia (`createInertiaApp` lo renderiza
 * como sibling del componente raíz). Por eso NO se puede usar
 * `usePage()` aquí — usamos los **eventos** de Inertia (`success`,
 * `error`) que traen la nueva `page` directamente en `event.detail`.
 *
 * Dedupe por `flash.id`:
 *   El backend genera un `id` único por cada nuevo flash. En partial
 *   reloads que no incluyen `flash` en `only=`, Inertia mantiene el
 *   payload anterior en `page.props.flash` y dispara `success` con esa
 *   misma data, lo cual antes provocaba que el toast se duplicara cada
 *   vez que el usuario cambiaba un filtro. Recordando el último `id`
 *   procesado evitamos re-disparar el mismo flash.
 */
export function useFlashToast(): void {
    const lastShownIdRef = useRef<string | null>(null);

    useEffect(() => {
        const handle = (flash: FlashShape | null | undefined): void => {
            if (!flash || typeof flash.id !== 'string' || flash.id === '') {
                return;
            }

            if (lastShownIdRef.current === flash.id) {
                return;
            }

            lastShownIdRef.current = flash.id;
            showFlashes(flash);
        };

        const initialPage = (router as unknown as { page?: InertiaPage }).page;
        handle(initialPage?.props?.flash);

        const removeSuccess = router.on('success', (event) => {
            const detail = (event as InertiaEvent).detail;
            handle(detail?.page?.props?.flash);
        });

        const removeError = router.on('error', (event) => {
            const detail = (event as InertiaEvent).detail;
            handle(detail?.page?.props?.flash);
        });

        return () => {
            removeSuccess();
            removeError();
        };
    }, []);
}
