import { useEffect, useState, type RefObject } from 'react';

/**
 * Observa un contenedor scrolleable y reporta si hay contenido oculto
 * arriba/abajo para mostrar gradientes de scroll.
 *
 * Reacciona a:
 *  - scroll del contenedor
 *  - cambios de tamaño del contenedor (ResizeObserver)
 *  - cambios de tamaño de su contenido
 *  - reactivación del flag `active` (por ejemplo cuando el modal se abre).
 */
export function useScrollHint(
    ref: RefObject<HTMLElement | null>,
    active: boolean = true,
) {
    const [overflowTop, setOverflowTop] = useState(false);
    const [overflowBottom, setOverflowBottom] = useState(false);

    useEffect(() => {
        if (!active) {
            setOverflowTop(false);
            setOverflowBottom(false);
            return;
        }

        const el = ref.current;
        if (!el) {
            return;
        }

        const update = () => {
            const hasOverflow = el.scrollHeight - el.clientHeight > 1;
            const atTop = el.scrollTop <= 1;
            const atBottom =
                el.scrollTop + el.clientHeight >= el.scrollHeight - 1;
            setOverflowTop(hasOverflow && !atTop);
            setOverflowBottom(hasOverflow && !atBottom);
        };

        // Múltiples mediciones diferidas: cubrir el caso en que el contenedor
        // se mide ANTES de que terminen las animaciones de entrada del modal
        // y del stagger de secciones internas (que cambian el layout final).
        const raf = requestAnimationFrame(update);
        const timeouts = [50, 250, 600, 1000].map((ms) =>
            window.setTimeout(update, ms),
        );

        el.addEventListener('scroll', update, { passive: true });

        const ro = new ResizeObserver(update);
        ro.observe(el);
        // Observar también los hijos descendientes directos para detectar
        // cambios de altura producidos por validaciones o secciones colapsables.
        const observeDescendants = (node: Element) => {
            ro.observe(node);
            Array.from(node.children).forEach((child) => observeDescendants(child));
        };
        Array.from(el.children).forEach(observeDescendants);

        return () => {
            cancelAnimationFrame(raf);
            timeouts.forEach((t) => window.clearTimeout(t));
            el.removeEventListener('scroll', update);
            ro.disconnect();
        };
    }, [ref, active]);

    return { overflowTop, overflowBottom };
}
