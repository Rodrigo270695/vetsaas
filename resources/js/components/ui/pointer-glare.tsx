import type { PointerEvent } from 'react';

export function pointerGlareMove(event: PointerEvent<HTMLElement>) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    const el = event.currentTarget;
    const rect = el.getBoundingClientRect();
    el.style.setProperty('--glare-x', `${event.clientX - rect.left}px`);
    el.style.setProperty('--glare-y', `${event.clientY - rect.top}px`);
    el.style.setProperty('--glare-op', '1');
}

export function pointerGlareLeave(event: PointerEvent<HTMLElement>) {
    event.currentTarget.style.setProperty('--glare-op', '0');
}

/** Destello que sigue el mouse (Magic UI Magic Card / Glare). */
export function PointerGlare() {
    return (
        <div
            aria-hidden
            className="pointer-events-none absolute inset-0 z-[2] rounded-[inherit] opacity-[var(--glare-op,0)] transition-opacity duration-300"
            style={{
                background:
                    'radial-gradient(170px circle at var(--glare-x, 50%) var(--glare-y, 40%), rgb(255 255 255 / 0.42), transparent 58%)',
            }}
        />
    );
}
