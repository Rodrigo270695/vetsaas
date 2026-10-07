import { useLayoutEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

type BounceNavDotProps = {
    /** Cambia cuando cambia la ruta activa del menú. */
    activeKey: string | null;
    /** Sidebar en modo icono: anillo alrededor del icono activo. */
    compact?: boolean;
};

type Frame = { x: number; y: number; size: number };

/** Última posición en el sidebar (sobrevive remounts de Inertia). */
let lastFrame: Frame | null = null;

const DOT_SIZE = 6;
/** Círculo un poco mayor que el botón de 32px, para envolver el icono. */
const RING_SIZE = 34;
/** Hueco a la izquierda del fondo activo (fuera del cuadrante azul). */
const OUTSIDE_GAP = 8;
const ARC_STEPS = 24;
const EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';

function readFrame(el: HTMLElement): Frame | null {
    const t = getComputedStyle(el).transform;
    if (!t || t === 'none') {
        return null;
    }
    const m = new DOMMatrixReadOnly(t);

    return { x: m.m41, y: m.m42, size: el.offsetWidth || DOT_SIZE };
}

function isVisuallyUsable(el: HTMLElement, root: HTMLElement): boolean {
    if (el.closest('[data-slot="collapsible-content"][data-state="closed"]')) {
        return false;
    }

    if (typeof el.checkVisibility === 'function' && !el.checkVisibility()) {
        return false;
    }

    let node: HTMLElement | null = el;
    while (node && node !== root) {
        const style = getComputedStyle(node);
        if (style.display === 'none' || style.visibility === 'hidden') {
            return false;
        }
        const rect = node.getBoundingClientRect();
        const clipped =
            style.overflow === 'hidden'
            || style.overflowY === 'hidden'
            || style.overflowX === 'hidden';
        if (clipped && (rect.height < 4 || rect.width < 4)) {
            return false;
        }
        node = node.parentElement;
    }

    const elRect = el.getBoundingClientRect();
    const rootRect = root.getBoundingClientRect();
    if (elRect.height < 2 || elRect.width < 2) {
        return false;
    }
    if (elRect.bottom < rootRect.top + 1 || elRect.top > rootRect.bottom - 1) {
        return false;
    }

    return true;
}

function pointFor(el: HTMLElement, root: HTMLElement, compact: boolean): Frame {
    const rootRect = root.getBoundingClientRect();
    const elRect = el.getBoundingClientRect();

    if (compact) {
        return {
            x: elRect.left - rootRect.left + (elRect.width - RING_SIZE) / 2,
            y: elRect.top - rootRect.top + (elRect.height - RING_SIZE) / 2,
            size: RING_SIZE,
        };
    }

    return {
        x: elRect.left - rootRect.left - DOT_SIZE - OUTSIDE_GAP,
        y: elRect.top - rootRect.top + elRect.height / 2 - DOT_SIZE / 2,
        size: DOT_SIZE,
    };
}

function measureActive(root: HTMLElement, compact: boolean): Frame | null {
    const marked = Array.from(root.querySelectorAll<HTMLElement>('[data-bounce-active="true"]'));
    const visible = marked.find((el) => isVisuallyUsable(el, root));
    if (visible) {
        return pointFor(visible, root, compact);
    }

    const hiddenActive = marked[0];
    if (!hiddenActive) {
        return null;
    }

    const group = hiddenActive.closest<HTMLElement>('[data-nav-group], [data-slot="collapsible"]');
    const trigger = group?.querySelector<HTMLElement>(
        '[data-slot="collapsible-trigger"], [data-sidebar="menu-button"]',
    );

    if (trigger && isVisuallyUsable(trigger, root)) {
        return pointFor(trigger, root, compact);
    }

    return null;
}

/** Arco suave a la izquierda con la barra abierta. El anillo se desliza casi recto. */
function arcKeyframes(from: Frame, to: Frame): Keyframe[] {
    const dx = to.x - from.x;
    const dy = to.y - from.y;
    const chord = Math.hypot(dx, dy);
    const ring = to.size > 16;
    const rx = ring ? Math.min(2, chord * 0.015) : Math.min(7, Math.max(2.5, chord * 0.05));
    const frames: Keyframe[] = [];

    for (let i = 0; i <= ARC_STEPS; i++) {
        const t = i / ARC_STEPS;
        const bulge = Math.sin(Math.PI * t) * rx;
        const size = from.size + (to.size - from.size) * t;
        frames.push({
            transform: `translate(${from.x + dx * t - bulge}px, ${from.y + dy * t}px)`,
            width: `${size}px`,
            height: `${size}px`,
        });
    }

    return frames;
}

function travelDuration(from: Frame, to: Frame): number {
    const chord = Math.hypot(to.x - from.x, to.y - from.y);
    const morph = Math.abs(to.size - from.size) > 8;

    if (morph) {
        return 520;
    }

    if (to.size > 16) {
        return Math.min(460, 320 + chord * 0.2);
    }

    return Math.min(360, 200 + chord * 0.35);
}

function paintFrame(dot: HTMLElement, frame: Frame): void {
    dot.style.width = `${frame.size}px`;
    dot.style.height = `${frame.size}px`;
    dot.style.transform = `translate(${frame.x}px, ${frame.y}px)`;
}

/**
 * Punto fuera del ítem activo que viaja con un arco corto entre rutas.
 * El padre debe ser `position: relative` (SidebarMenu).
 */
export function BounceNavDot({ activeKey, compact = false }: BounceNavDotProps) {
    const dotRef = useRef<HTMLSpanElement>(null);

    useLayoutEffect(() => {
        const dot = dotRef.current;
        const root = dot?.parentElement;
        if (!dot || !root) {
            return;
        }

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let cancelled = false;
        let flyingTo: Frame | null = null;

        const place = () => {
            if (cancelled) {
                return;
            }

            const to = measureActive(root, compact);
            if (!to) {
                lastFrame = null;
                flyingTo = null;
                dot.style.opacity = '0';
                return;
            }

            const sameFlight =
                flyingTo !== null
                && Math.abs(flyingTo.x - to.x) < 0.75
                && Math.abs(flyingTo.y - to.y) < 0.75
                && Math.abs(flyingTo.size - to.size) < 0.75
                && dot.getAnimations().length > 0;

            if (sameFlight) {
                return;
            }

            const live = readFrame(dot);
            const from = live ?? lastFrame;
            lastFrame = to;
            dot.style.opacity = '1';
            dot.style.transition = 'none';

            const alreadyThere =
                from !== null
                && Math.abs(from.x - to.x) < 0.5
                && Math.abs(from.y - to.y) < 0.5
                && Math.abs(from.size - to.size) < 0.5;

            dot.getAnimations().forEach((a) => a.cancel());
            flyingTo = null;

            if (reduce || from === null || alreadyThere) {
                paintFrame(dot, to);
                return;
            }

            paintFrame(dot, from);
            flyingTo = to;
            const animation = dot.animate(arcKeyframes(from, to), {
                duration: travelDuration(from, to),
                easing: EASE,
                fill: 'forwards',
            });
            animation.finished.then(() => {
                if (!cancelled) {
                    flyingTo = null;
                    paintFrame(dot, to);
                }
            }).catch(() => {
                // La animación se cancela al cambiar de ruta.
            });
        };

        place();
        const afterWidth = window.setTimeout(place, 220);
        const afterCollapse = window.setTimeout(place, 360);
        const ro = new ResizeObserver(place);
        ro.observe(root);
        const sidebar = root.closest('[data-slot="sidebar"]') ?? root;
        const mo = new MutationObserver(place);
        mo.observe(sidebar, {
            subtree: true,
            attributes: true,
            attributeFilter: ['data-state', 'data-bounce-active', 'data-collapsible', 'class'],
        });

        return () => {
            cancelled = true;
            window.clearTimeout(afterWidth);
            window.clearTimeout(afterCollapse);
            ro.disconnect();
            mo.disconnect();
        };
    }, [activeKey, compact]);

    return (
        <span
            ref={dotRef}
            aria-hidden
            data-slot="bounce-nav-dot"
            className={cn(
                'pointer-events-none absolute top-0 left-0 z-20 box-border rounded-full opacity-0 will-change-transform',
                compact
                    ? 'z-20 border-[1.5px] border-primary bg-transparent shadow-[0_0_0_4px] shadow-primary/15'
                    : 'z-20 border-0 bg-primary shadow-[0_0_0_3px] shadow-primary/15',
            )}
        />
    );
}
