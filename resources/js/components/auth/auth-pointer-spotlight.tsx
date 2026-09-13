import type { PointerEvent } from 'react';
import { useClinicBranding } from '@/hooks/use-clinic-branding';

function brandHex(value: string | null | undefined): string {
    const hex = value?.trim() ?? '';
    if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
        return hex;
    }

    return '#006D55';
}

export function authPointerSpotlightMove(event: PointerEvent<HTMLDivElement>) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    const root = event.currentTarget;
    const rect = root.getBoundingClientRect();
    root.style.setProperty('--spot-x', `${event.clientX - rect.left}px`);
    root.style.setProperty('--spot-y', `${event.clientY - rect.top}px`);
}

/**
 * Luz suave que sigue el puntero en toda la pantalla de auth.
 */
export default function AuthPointerSpotlight() {
    const branding = useClinicBranding();
    const color = brandHex(branding?.color_primario);

    return (
        <div
            aria-hidden
            className="pointer-events-none absolute inset-0 z-[1]"
            style={{
                background: `radial-gradient(28rem circle at var(--spot-x, 50%) var(--spot-y, 38%), color-mix(in srgb, ${color} 16%, transparent), transparent 62%)`,
            }}
        />
    );
}
