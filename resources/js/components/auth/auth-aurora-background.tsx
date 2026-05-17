/**
 * Fondo decorativo de las pantallas de autenticación.
 * - 3 blobs OKLCH de la paleta brand con drift suave (18-30s).
 * - Grain noise vía SVG turbulence sobre todo.
 * - Respeta `prefers-reduced-motion` (keyframes en app.css).
 */
export default function AuthAuroraBackground() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-0 overflow-hidden"
        >
            <div className="aurora-blob-1 absolute -top-32 -left-24 size-168 rounded-full bg-[radial-gradient(circle_at_center,oklch(0.911_0.046_168/0.9),transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle_at_center,oklch(0.395_0.078_170/0.45),transparent_60%)]" />
            <div className="aurora-blob-2 absolute top-32 -right-28 size-144 rounded-full bg-[radial-gradient(circle_at_center,oklch(0.836_0.080_168/0.8),transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle_at_center,oklch(0.325_0.062_170/0.55),transparent_60%)]" />
            <div className="aurora-blob-3 absolute -bottom-40 left-1/3 size-160 rounded-full bg-[radial-gradient(circle_at_center,oklch(0.736_0.108_168/0.55),transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle_at_center,oklch(0.272_0.050_170/0.6),transparent_60%)]" />

            <svg className="absolute inset-0 h-full w-full opacity-[0.025] mix-blend-overlay dark:opacity-[0.06]">
                <filter id="auth-grain">
                    <feTurbulence
                        type="fractalNoise"
                        baseFrequency="0.85"
                        numOctaves="2"
                        stitchTiles="stitch"
                    />
                    <feColorMatrix type="saturate" values="0" />
                </filter>
                <rect width="100%" height="100%" filter="url(#auth-grain)" />
            </svg>
        </div>
    );
}
