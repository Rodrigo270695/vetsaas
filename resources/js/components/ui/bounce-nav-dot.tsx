import { useLayoutEffect, useRef } from 'react';

type BounceNavDotProps = {
    /** Cambia cuando cambia la ruta activa del menú. */
    activeKey: string | null;
};

type Point = { x: number; y: number };

/** Última posición en el sidebar (sobrevive remounts de Inertia). */
let lastPoint: Point | null = null;

const DOT_SIZE = 6;
/** Hueco a la izquierda del fondo activo (fuera del cuadrante azul). */
const OUTSIDE_GAP = 8;
const ARC_STEPS = 24;

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
        x: elRect.left - rootRect.left - size - OUTSIDE_GAP,
        y: elRect.top - rootRect.top + elRect.height / 2 - size / 2,
    };
}

/** Media luna a la izquierda: el punto recorre un arco, no una recta vertical. */
function arcKeyframes(from: Point, to: Point): Keyframe[] {
    const dx = to.x - from.x;
    const dy = to.y - from.y;
    const chord = Math.hypot(dx, dy);
    const rx = Math.min(26, Math.max(16, chord * 0.5));
    const frames: Keyframe[] = [];

    for (let i = 0; i <= ARC_STEPS; i++) {
        const t = i / ARC_STEPS;
        const bulge = Math.sin(Math.PI * t) * rx;
        frames.push({
            transform: `translate(${from.x + dx * t - bulge}px, ${from.y + dy * t}px)`,
        });
    }

    return frames;
}

/**
 * Punto fuera del ítem activo que viaja en media luna entre rutas.
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
        let cancelled = false;

        const place = () => {
            if (cancelled) {
                return;
            }

            const to = measureActive(root, DOT_SIZE);
            if (!to) {
                dot.style.opacity = '0';
                return;
            }

            const live = readTransform(dot);
            const from = live ?? lastPoint;
            lastPoint = to;
            dot.style.opacity = '1';
            dot.style.transition = 'none';

            const alreadyThere =
                from !== null && Math.abs(from.x - to.x) < 0.5 && Math.abs(from.y - to.y) < 0.5;

            dot.getAnimations().forEach((a) => a.cancel());

            if (reduce || from === null || alreadyThere) {
                dot.style.transform = `translate(${to.x}px, ${to.y}px)`;
                return;
            }

            dot.style.transform = `translate(${from.x}px, ${from.y}px)`;
            dot.animate(arcKeyframes(from, to), {
                duration: 520,
                easing: 'cubic-bezier(0.37, 0, 0.63, 1)',
                fill: 'forwards',
            });
        };

        place();

        return () => {
            cancelled = true;
        };
    }, [activeKey]);

    return (
        <span
            ref={dotRef}
            aria-hidden
            data-slot="bounce-nav-dot"
            className="pointer-events-none absolute top-0 left-0 z-20 size-1.5 rounded-full bg-primary opacity-0 shadow-[0_0_0_3px] shadow-primary/15 will-change-transform group-data-[collapsible=icon]:hidden"
        />
    );
}
