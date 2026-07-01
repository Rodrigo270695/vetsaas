import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

type Props = {
    logoUrl?: string | null;
    className?: string;
    iconClassName?: string;
    fallbackClassName?: string;
};

/**
 * Logo de clínica si existe; si no, el icono VetSaaS por defecto.
 */
export function ClinicLogoMark({
    logoUrl,
    className,
    iconClassName = 'size-5',
    fallbackClassName,
}: Props) {
    if (logoUrl) {
        return (
            <img
                src={logoUrl}
                alt=""
                className={cn('shrink-0 object-contain', className)}
            />
        );
    }

    return (
        <div
            className={cn(
                'flex aspect-square shrink-0 items-center justify-center',
                fallbackClassName ??
                    cn(
                        'rounded-md bg-sidebar-primary text-sidebar-primary-foreground',
                        className ?? 'size-8',
                    ),
            )}
        >
            <AppLogoIcon className={iconClassName} />
        </div>
    );
}
