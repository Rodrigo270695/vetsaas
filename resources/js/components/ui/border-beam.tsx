import { cn } from '@/lib/utils';

type BorderBeamProps = {
    className?: string;
    duration?: number;
    reverse?: boolean;
};

/**
 * Haz de luz recorriendo el borde (Magic UI Border Beam), solo CSS.
 */
export function BorderBeam({ className, duration = 8, reverse = false }: BorderBeamProps) {
    return (
        <div
            aria-hidden
            className="pointer-events-none absolute inset-0 rounded-[inherit] p-px"
            style={{
                WebkitMask:
                    'linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0)',
                WebkitMaskComposite: 'xor',
                mask: 'linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0)',
                maskComposite: 'exclude',
            }}
        >
            <div
                className={cn(
                    'auth-border-beam-spin absolute inset-[-40%]',
                    reverse && 'auth-border-beam-spin-reverse',
                    className,
                )}
                style={{ animationDuration: `${duration}s` }}
            />
        </div>
    );
}
