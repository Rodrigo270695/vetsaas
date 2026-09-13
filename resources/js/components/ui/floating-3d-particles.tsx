import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

export type Floating3DParticlesProps = {
    className?: string;
    quantity?: number;
    color?: string;
    size?: number;
    opacity?: number;
    drift?: number;
    depth?: number;
};

type Particle = {
    angle: number;
    radius: number;
    y: number;
    size: number;
    angularSpeed: number;
    opacity: number;
    screenX: number;
    screenY: number;
    projectedScale: number;
};

const MOBILE_BREAKPOINT = 768;
const SPREAD_FACTOR = 1.85;
const MAX_DPR = 2;

function hexToRgba(hex: string, alpha: number): string {
    const clean = hex.replace('#', '').trim();
    const full =
        clean.length === 3
            ? clean
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : clean;
    if (!/^[0-9a-f]{6}$/i.test(full)) {
        return `rgba(15,23,42,${alpha})`;
    }
    const n = Number.parseInt(full, 16);

    return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${alpha})`;
}

function deriveProjection(depth: number) {
    const t = Math.max(0, Math.min(1, depth));
    const fov = 800 - t * 600;
    const perspectiveDistance = 100 + t * 700;
    const depthRange = t * Math.min(400, fov + perspectiveDistance - 1);

    return { fov, perspectiveDistance, depthRange };
}

function spawnParticle(width: number, height: number, size: number, opacity: number): Particle {
    const sizeVariance = size * 0.4;
    const opacityVariance = 0.2;

    return {
        angle: Math.random() * Math.PI * 2,
        radius: Math.random() * Math.max(width, height) * SPREAD_FACTOR,
        y: (Math.random() - 0.5) * height * 2,
        size: Math.max(0.5, size - sizeVariance + Math.random() * sizeVariance * 2),
        angularSpeed: 0.0015 + Math.random() * 0.001,
        opacity: Math.min(1, Math.max(0, opacity - opacityVariance + Math.random() * opacityVariance * 2)),
        screenX: 0,
        screenY: 0,
        projectedScale: 1,
    };
}

/**
 * Campo de partículas pseudo-3D (Magic UI Floating 3D Particles).
 */
export function Floating3DParticles({
    quantity = 400,
    color = '#0f172a',
    size = 5,
    opacity = 0.3,
    drift = 0.8,
    depth = 0.5,
    className,
}: Floating3DParticlesProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const colorRef = useRef(color);
    colorRef.current = color;

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) {
            return;
        }
        const ctx = canvas.getContext('2d', { alpha: true });
        if (!ctx) {
            return;
        }

        let mounted = true;
        let paused = false;
        let reducedMotion = false;
        let rafId: number | null = null;
        let width = 0;
        let height = 0;
        let particles: Particle[] = [];
        let staticDirty = true;
        let io: IntersectionObserver | null = null;

        const { fov, perspectiveDistance, depthRange } = deriveProjection(depth);
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        const syncReducedMotion = () => {
            reducedMotion = mq.matches;
            staticDirty = true;
        };
        syncReducedMotion();

        const draw = (p: Particle) => {
            const r = Math.max(0, p.size * p.projectedScale);
            if (r <= 0) {
                return;
            }
            ctx.beginPath();
            ctx.fillStyle = hexToRgba(colorRef.current, p.opacity);
            ctx.arc(p.screenX, p.screenY, r, 0, Math.PI * 2);
            ctx.fill();
        };

        const project = (p: Particle, withSin: boolean) => {
            const denom = Math.max(
                1,
                fov + perspectiveDistance + (withSin ? Math.sin(p.angle) * depthRange : 0),
            );
            const scale = fov / denom;
            p.screenX = width / 2 + Math.cos(p.angle) * p.radius * scale;
            p.screenY = height / 2 + p.y * scale;
            p.projectedScale = scale;
        };

        const staticFrame = () => {
            ctx.clearRect(0, 0, width, height);
            for (const p of particles) {
                project(p, false);
                draw(p);
            }
        };

        const tick = () => {
            if (!mounted) {
                return;
            }
            if (paused) {
                rafId = requestAnimationFrame(tick);
                return;
            }
            if (reducedMotion) {
                if (staticDirty) {
                    staticDirty = false;
                    staticFrame();
                }
                rafId = requestAnimationFrame(tick);
                return;
            }

            staticDirty = true;
            ctx.clearRect(0, 0, width, height);

            for (const p of particles) {
                p.angle += p.angularSpeed;
                p.y -= drift;
                if (p.y < -height) {
                    p.y = height;
                    p.radius = Math.random() * Math.max(width, height) * SPREAD_FACTOR;
                } else if (p.y > height) {
                    p.y = -height;
                    p.radius = Math.random() * Math.max(width, height) * SPREAD_FACTOR;
                }
                project(p, true);
            }

            particles.sort((a, b) => a.projectedScale - b.projectedScale);
            for (const p of particles) {
                draw(p);
            }

            rafId = requestAnimationFrame(tick);
        };

        const resize = () => {
            const host = canvas.parentElement ?? canvas;
            const rect = host.getBoundingClientRect();
            width = Math.max(1, Math.round(rect.width || window.innerWidth));
            height = Math.max(1, Math.round(rect.height || window.innerHeight));
            const dpr = Math.max(1, Math.min(window.devicePixelRatio || 1, MAX_DPR));
            canvas.width = Math.round(width * dpr);
            canvas.height = Math.round(height * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            const isMobile = window.innerWidth < MOBILE_BREAKPOINT;
            const count = isMobile ? Math.round(quantity * 0.2) : quantity;
            particles = Array.from({ length: Math.max(0, count) }, () =>
                spawnParticle(width, height, size, opacity),
            );
            staticDirty = true;
        };

        const onVisibilityChange = () => {
            paused = document.hidden;
        };

        const ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(resize) : null;
        const host = canvas.parentElement ?? canvas;
        if (ro) {
            ro.observe(host);
            ro.observe(canvas);
        } else {
            window.addEventListener('resize', resize);
        }

        if (typeof IntersectionObserver !== 'undefined') {
            io = new IntersectionObserver(
                ([entry]) => {
                    paused = document.hidden || !entry?.isIntersecting;
                },
                { threshold: 0 },
            );
            io.observe(canvas);
        }

        document.addEventListener('visibilitychange', onVisibilityChange);
        mq.addEventListener('change', syncReducedMotion);
        resize();
        const boot = requestAnimationFrame(() => {
            resize();
            rafId = requestAnimationFrame(tick);
        });

        return () => {
            mounted = false;
            cancelAnimationFrame(boot);
            if (rafId !== null) {
                cancelAnimationFrame(rafId);
            }
            ro?.disconnect();
            if (!ro) {
                window.removeEventListener('resize', resize);
            }
            io?.disconnect();
            document.removeEventListener('visibilitychange', onVisibilityChange);
            mq.removeEventListener('change', syncReducedMotion);
        };
    }, [quantity, size, opacity, drift, depth]);

    return (
        <canvas
            ref={canvasRef}
            aria-hidden
            className={cn('pointer-events-none absolute inset-0 block h-full w-full', className)}
        />
    );
}
