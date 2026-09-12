import { useLayoutEffect, useRef } from 'react';

type BounceNavDotProps = {
    /** Cambia cuando cambia la ruta activa del menú. */
    activeKey: string | null;
};

type Point = { x: number; y: number };

/** Última posición en el sidebar (sobrevive remounts de Inertia). */
let lastPoint: Point | null = null;

function readTransform(el: HTMLElement): Point | null {
    const t = getComputedStyle(el).transform;
    if (!t || t === 'none') {
        return null;
    }
    const m = new DOMMatrixReadOnly(t);
    return { x: m.m41, y: m.m42 };
}

function measureActive(root: HTMLElement, size: number): Point | null {
    const active = root.querySelector<HTMLElement>('[data-bounce-active="true"]');
    if (!active || active.offsetParent === null) {
        return null;
    }

    const rootRect = root.getBoundingClientRect();
    const elRect = active.getBoundingClientRect();
    if (elRect.height < 2) {
        return null;
    }

    return {
        x: elRect.left - rootRect.left + 6,
        y: elRect.top - rootRect.top + elRect.height / 2 - size / 2,
    };
}

/**
 * Punto que se desplaza visiblemente de un ítem activo a otro (no teletransporta).
 * El padre debe ser `position: relative` (SidebarMenu).
 */
export function BounceNavDot({ activeKey }: BounceNavDotProps) {
    const dotRef = useRef<HTMLSpanElement>(null);

    useLayoutEffect(() => {
        const dot = dotRef.current;
        const root = dot?.parentElement;
        if (!dot || !root) {
            return;
        }

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const size = 6;
        let cancelled = false;

        const place = (animate: boolean) => {
            if (cancelled) {
                return;
            }

            const to = measureActive(root, size);
            if (!to) {
                dot.style.opacity = '0';
                return;
            }

            const live = readTransform(dot);
            const from = live ?? lastPoint;
            lastPoint = to;
            dot.style.opacity = '1';

            const alreadyThere =
                from !== null && Math.abs(from.x - to.x) < 0.5 && Math.abs(from.y - to.y) < 0.5;

            if (!animate || reduce || from === null || alreadyThere) {
                dot.style.transition = 'none';
                dot.style.transform = `translate(${to.x}px, ${to.y}px)`;
                return;
            }

            dot.style.transition = 'none';
            dot.style.transform = `translate(${from.x}px, ${from.y}px)`;
            void dot.offsetHeight;
            dot.style.transition = 'transform 480ms cubic-bezier(0.22, 1, 0.36, 1)';
            dot.style.transform = `translate(${to.x}px, ${to.y}px)`;
        };

        place(true);

        const onEnd = (e: TransitionEvent) => {
            if (e.propertyName === 'transform') {
                lastPoint = readTransform(dot) ?? lastPoint;
            }
        };
        dot.addEventListener('transitionend', onEnd);

        return () => {
            cancelled = true;
            dot.removeEventListener('transitionend', onEnd);
        };
    }, [activeKey]);

    return (
        <span
            ref={dotRef}
            aria-hidden
            data-slot="bounce-nav-dot"
            className="pointer-events-none absolute top-0 left-0 z-10 size-1.5 rounded-full bg-primary opacity-0 shadow-[0_0_0_3px] shadow-primary/15 will-change-transform group-data-[collapsible=icon]:hidden"
        />
    );
}
