import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import ThemeToggle from '@/components/theme-toggle';
import { home } from '@/routes';

type AuthHeaderProps = {
    brandName: string;
    /** Email de contacto comercial. Si se omite, se oculta el CTA. */
    contactEmail?: string;
    contactLabel?: string;
};

/**
 * Cabecera de las páginas de auth: logo + CTA opcional + theme toggle.
 */
export default function AuthHeader({
    brandName,
    contactEmail = 'contacto@vetsaas.pe',
    contactLabel = '¿Sin cuenta? Hablemos',
}: AuthHeaderProps) {
    return (
        <header className="relative z-20 flex items-center justify-between px-5 py-5 sm:px-8 sm:py-6 lg:px-12">
            <Link
                href={home()}
                className="inline-flex items-center gap-2.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            >
                <span className="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm shadow-brand-700/20">
                    <AppLogoIcon className="size-5" />
                </span>
                <span className="text-base font-semibold tracking-tight text-foreground">
                    {brandName}
                </span>
            </Link>

            <div className="flex items-center gap-2 sm:gap-3">
                {contactEmail && (
                    <a
                        href={`mailto:${contactEmail}`}
                        className="group hidden cursor-pointer items-center gap-1.5 rounded-full border border-border/70 bg-card/70 px-3.5 py-1.5 text-xs font-medium text-muted-foreground backdrop-blur transition-colors hover:border-primary/40 hover:text-foreground sm:inline-flex"
                    >
                        {contactLabel}
                        <ArrowUpRight className="size-3.5 transition-transform group-hover:-translate-y-px group-hover:translate-x-px" />
                    </a>
                )}
                <ThemeToggle />
            </div>
        </header>
    );
}
