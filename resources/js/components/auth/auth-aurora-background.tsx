import { Floating3DParticles } from '@/components/ui/floating-3d-particles';
import { useAppearance } from '@/hooks/use-appearance';
import { useClinicBranding } from '@/hooks/use-clinic-branding';

const FALLBACK_BRAND = '#006D55';

function brandHex(value: string | null | undefined): string {
    const hex = value?.trim() ?? '';
    if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
        return hex;
    }

    return FALLBACK_BRAND;
}

/**
 * Fondo decorativo de las pantallas de autenticación.
 * - 3 blobs OKLCH de la paleta brand con drift suave.
 * - Partículas 3D a pantalla completa, en el color primario de la clínica.
 * - Grain noise vía SVG turbulence.
 */
export default function AuthAuroraBackground() {
    const { resolvedAppearance } = useAppearance();
    const branding = useClinicBranding();
    const particleColor = brandHex(branding?.color_primario);
    const isDark = resolvedAppearance === 'dark';

    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-0 h-full min-h-svh w-full overflow-hidden"
        >
            <div className="aurora-blob-1 absolute -top-32 -left-24 size-168 rounded-full bg-[radial-gradient(circle_at_center,oklch(0.911_0.046_168/0.9),transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle_at_center,oklch(0.395_0.078_170/0.45),transparent_60%)]" />
            <div className="aurora-blob-2 absolute top-32 -right-28 size-144 rounded-full bg-[radial-gradient(circle_at_center,oklch(0.836_0.080_168/0.8),transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle_at_center,oklch(0.325_0.062_170/0.55),transparent_60%)]" />
            <div className="aurora-blob-3 absolute -bottom-40 left-1/3 size-160 rounded-full bg-[radial-gradient(circle_at_center,oklch(0.736_0.108_168/0.55),transparent_60%)] blur-3xl dark:bg-[radial-gradient(circle_at_center,oklch(0.272_0.050_170/0.6),transparent_60%)]" />

            <Floating3DParticles
                color={particleColor}
                quantity={420}
                size={4}
                opacity={isDark ? 0.38 : 0.32}
                drift={0.5}
                depth={0.42}
            />

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
