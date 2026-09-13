import { cn } from '@/lib/utils';

type RippleProps = {
    className?: string;
    rings?: number;
};

/**
 * Anillos concéntricos (Magic UI Ripple) para enfatizar un icono o tarjeta.
 */
export function Ripple({ className, rings = 5 }: RippleProps) {
    return (
        <div
            aria-hidden
            className={cn('pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]', className)}
        >
            {Array.from({ length: rings }, (_, i) => (
                <span
                    key={i}
                    className="auth-ripple-ring absolute top-[70%] left-[78%] rounded-full border border-primary/25 dark:border-primary/35"
                    style={{
                        width: `${2.2 + i * 1.15}rem`,
                        height: `${2.2 + i * 1.15}rem`,
                        animationDelay: `${i * 0.4}s`,
                    }}
                />
            ))}
        </div>
    );
}
