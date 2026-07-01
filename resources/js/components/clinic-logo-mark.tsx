import { VETSAAS_DEFAULT_LOGO } from '@/lib/brand';
import { cn } from '@/lib/utils';

type Props = {
    logoUrl?: string | null;
    className?: string;
};

/**
 * Logo de clínica o, si no hay, la imagen VetSaaS original (no máscara CSS).
 */
export function ClinicLogoMark({ logoUrl, className }: Props) {
    const src = logoUrl?.trim() || VETSAAS_DEFAULT_LOGO;

    return (
        <img
            src={src}
            alt=""
            className={cn('shrink-0 object-contain', className)}
        />
    );
}
