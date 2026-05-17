import { toast } from 'sonner';

export type ToastType = 'default' | 'success' | 'error' | 'info' | 'warning' | 'loading';

export type ToastAction = {
    label: string;
    onClick: () => void;
};

export type ToastOptions = {
    /** Identificador estable: si ya existe se actualiza en lugar de stackear. */
    id?: string | number;
    /** Tipo visual del toast. */
    type?: ToastType;
    /** Título principal. */
    title: string;
    /** Descripción secundaria, opcional. */
    description?: string;
    /** Botón de acción (ej. "Deshacer"). */
    action?: ToastAction;
    /** Duración en ms. Default = 4000. Usa `Infinity` para que no se cierre. */
    duration?: number;
    /** Posición específica (sobrescribe la global del Toaster). */
    position?:
        | 'top-left'
        | 'top-right'
        | 'top-center'
        | 'bottom-left'
        | 'bottom-right'
        | 'bottom-center';
};

export type ToastPromiseOptions<T> = {
    loading: string | { title: string; description?: string };
    success:
        | string
        | { title: string; description?: string }
        | ((data: T) => string | { title: string; description?: string });
    error:
        | string
        | { title: string; description?: string }
        | ((err: unknown) => string | { title: string; description?: string });
};

/**
 * Normaliza ToastOptions a la API que sonner espera.
 */
function buildSonnerOptions(opts: ToastOptions) {
    return {
        id: opts.id,
        description: opts.description,
        duration: opts.duration,
        position: opts.position,
        action: opts.action
            ? {
                  label: opts.action.label,
                  onClick: opts.action.onClick,
              }
            : undefined,
    };
}

/**
 * `toastManager` con la API "estilo coss.com" pero apoyado en sonner.
 *
 * Uso:
 * ```ts
 * import { toastManager } from '@/lib/toast';
 *
 * toastManager.add({ title: 'Guardado', type: 'success' });
 * toastManager.success({ title: 'Sede creada', description: 'SEDE-005' });
 *
 * // Update in place
 * toastManager.add({ id: 'save-status', title: 'Guardado', type: 'success' });
 *
 * // Promise (sonner)
 * toastManager.promise(api.save(payload), {
 *     loading: 'Guardando…',
 *     success: 'Sede actualizada',
 *     error: 'No se pudo guardar',
 * });
 * ```
 */
export const toastManager = {
    add(opts: ToastOptions): string | number {
        const sonnerOpts = buildSonnerOptions(opts);
        switch (opts.type) {
            case 'success':
                return toast.success(opts.title, sonnerOpts);
            case 'error':
                return toast.error(opts.title, sonnerOpts);
            case 'info':
                return toast.info(opts.title, sonnerOpts);
            case 'warning':
                return toast.warning(opts.title, sonnerOpts);
            case 'loading':
                return toast.loading(opts.title, sonnerOpts);
            default:
                return toast(opts.title, sonnerOpts);
        }
    },

    success(opts: Omit<ToastOptions, 'type'>) {
        return this.add({ ...opts, type: 'success' });
    },

    error(opts: Omit<ToastOptions, 'type'>) {
        return this.add({ ...opts, type: 'error' });
    },

    info(opts: Omit<ToastOptions, 'type'>) {
        return this.add({ ...opts, type: 'info' });
    },

    warning(opts: Omit<ToastOptions, 'type'>) {
        return this.add({ ...opts, type: 'warning' });
    },

    loading(opts: Omit<ToastOptions, 'type'>) {
        return this.add({ ...opts, type: 'loading' });
    },

    close(id: string | number) {
        toast.dismiss(id);
    },

    dismissAll() {
        toast.dismiss();
    },

    /**
     * Maneja toda la vida útil de una promesa con un solo toast:
     * muestra "loading", luego "success" o "error" según resuelva.
     */
    promise<T>(promise: Promise<T>, opts: ToastPromiseOptions<T>) {
        return toast.promise(promise, opts);
    },
};
