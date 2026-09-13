import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type AuthFormCardProps = {
    children: ReactNode;
    className?: string;
};

/**
 * Card con efecto Liquid Glass / Glassmorphism premium.
 *
 * Capas:
 *  1. Halo exterior — gradiente brand difuminado (glow envolvente).
 *  2. Card transparente con backdrop-blur fuerte + saturación 150%.
 *  3. Top highlight — línea blanca casi imperceptible (luz refractada).
 *  4. Noise interno — micro-textura para autenticidad de "vidrio".
 *  5. Contenido en z-10 sobre todo.
 */
export default function AuthFormCard({
    children,
    className,
}: AuthFormCardProps) {
    return (
        <div className="relative">
            {/* 1. Halo glow envolvente */}
            <div
                aria-hidden="true"
                className="absolute -inset-3 rounded-4xl bg-linear-to-br from-brand-500/35 via-brand-300/15 to-brand-700/30 opacity-70 blur-3xl"
            />

            {/* 2. Card glass */}
            <div
                className={cn(
                    'relative overflow-hidden rounded-3xl border border-white/50 bg-white/40 p-6 backdrop-blur-2xl backdrop-saturate-150 sm:p-8 lg:p-10',
                    'shadow-[0_24px_70px_-18px_rgba(0,40,30,0.28),0_10px_28px_-14px_rgba(0,40,30,0.18),inset_0_1px_0_0_rgba(255,255,255,0.6)]',
                    'dark:border-white/10 dark:bg-card/30 dark:shadow-[0_24px_70px_-18px_rgba(0,0,0,0.7),0_10px_28px_-14px_rgba(0,0,0,0.5),inset_0_1px_0_0_rgba(255,255,255,0.08)]',
                    className,
                )}
            >
                {/* 3. Top highlight — luz refractada en el borde superior */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-x-6 top-0 h-px bg-linear-to-r from-transparent via-white/90 to-transparent sm:inset-x-8 dark:via-white/25"
                />

                {/* 4. Noise interno fino */}
                <svg
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 h-full w-full opacity-[0.025] mix-blend-overlay dark:opacity-[0.08]"
                >
                    <filter id="card-grain">
                        <feTurbulence
                            type="fractalNoise"
                            baseFrequency="2"
                            numOctaves="2"
                            stitchTiles="stitch"
                        />
                        <feColorMatrix type="saturate" values="0" />
                    </filter>
                    <rect
                        width="100%"
                        height="100%"
                        filter="url(#card-grain)"
                    />
                </svg>

                {/* 5. Contenido */}
                <div className="relative z-10">{children}</div>
            </div>
        </div>
    );
}
