import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Renderiza /public/logo.png como máscara alpha pintada con el color actual.
 * Cambia el color con clases tipo `text-brand-600`, `text-white`, etc.
 *
 * Ventajas vs <img>:
 *  - Se recolorea por CSS sin filter hue-rotate (sin pérdidas).
 *  - Hereda dark-mode automáticamente.
 *  - Igual de nítido que el PNG original en Retina.
 */
export default function AppLogoIcon({
    className,
    ...props
}: HTMLAttributes<HTMLSpanElement>) {
    return (
        <span
            role="img"
            aria-label="VetSaaS"
            className={cn('inline-block bg-current align-middle', className)}
            style={{
                WebkitMaskImage: 'url(/logo.png)',
                maskImage: 'url(/logo.png)',
                WebkitMaskRepeat: 'no-repeat',
                maskRepeat: 'no-repeat',
                WebkitMaskPosition: 'center',
                maskPosition: 'center',
                WebkitMaskSize: 'contain',
                maskSize: 'contain',
            }}
            {...props}
        />
    );
}
