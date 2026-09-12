import { useLayoutEffect, useRef } from 'react';

type BounceNavDotProps = {
    /** Cambia cuando el item activo de esta lista cambia (href o null). */
    activeKey: string | null;
};

/**
 * Punto que salta en arco entre ítems del menú (efecto Rare UI Bounce Sidebar).
 * El padre debe ser `position: relative` (p. ej. SidebarMenuSub).
 */
export function BounceNavDot({ activeKey }: BounceNavDotProps) {
    const dotRef = useRef<HTMLSpanElement>(null);
    const prevY = useRef<number | null>(null);

    useLayoutEffect(() => {
        const dot = dotRef.current;
        const root = dot?.parentElement;
        if (!dot || !root) {
            return;
        }

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const dpr = window.devicePixelRatio || 1;
        const size = Math.round(6 * dpr) / dpr;

        const snap = () => {
            const active = root.querySelector<HTMLElement>(':scope > [data-bounce-active="true"]');
            if (!active) {
                dot.style.opacity = '0';
                return;
            }

            dot.style.opacity = '1';
            const rootRect = root.getBoundingClientRect();
            const elRect = active.getBoundingClientRect();
            const toY =
                Math.round(
                    (elRect.top - rootRect.top + root.scrollTop + elRect.height / 2 - size / 2) * dpr,
                ) / dpr;

            const fromY = prevY.current;
            prevY.current = toY;

            if (fromY === null || reduce || fromY === toY) {
                dot.getAnimations().forEach((a) => a.cancel());
                dot.style.transform = `translate(0px, ${toY}px)`;
                return;
            }

            const midY = (fromY + toY) / 2;
            const bulge = toY > fromY ? 7 : -7;

            dot.animate(
                [
                    { transform: `translate(0px, ${fromY}px)` },
                    { transform: `translate(${bulge}px, ${midY}px)` },
                    { transform: `translate(0px, ${toY}px)` },
                ],
                {
                    duration: 280,
                    easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                    fill: 'forwards',
                },
            );
        };

        snap();
        const raf = requestAnimationFrame(snap);
        void document.fonts?.ready.then(snap);

        return () => cancelAnimationFrame(raf);
    }, [activeKey]);

    return (
        <span
            ref={dotRef}
            aria-hidden
            data-slot="bounce-nav-dot"
            className="pointer-events-none absolute top-0 left-0 z-10 size-1.5 rounded-full bg-primary opacity-0 shadow-[0_0_0_3px] shadow-primary/15 group-data-[collapsible=icon]:hidden"
        />
    );
}
