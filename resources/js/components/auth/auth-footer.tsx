type AuthFooterProps = {
    brandName: string;
};

/**
 * Pie discreto con copyright + enlaces legales y de soporte.
 */
export default function AuthFooter({ brandName }: AuthFooterProps) {
    const year = new Date().getFullYear();

    return (
        <footer className="relative z-10 mx-auto flex w-full max-w-5xl flex-col items-center gap-2 px-6 pb-6 text-center text-xs text-muted-foreground/80 sm:flex-row sm:justify-between sm:pb-8">
            <span>
                © {year} {brandName} · Hecho en Perú
            </span>
            <span className="flex items-center gap-3">
                <FooterLink href="#">Términos</FooterLink>
                <span aria-hidden="true">·</span>
                <FooterLink href="#">Privacidad</FooterLink>
                <span aria-hidden="true">·</span>
                <FooterLink href="mailto:soporte@vetsaas.pe">Soporte</FooterLink>
            </span>
        </footer>
    );
}

function FooterLink({
    href,
    children,
}: {
    href: string;
    children: React.ReactNode;
}) {
    return (
        <a
            href={href}
            className="cursor-pointer rounded-sm transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            {children}
        </a>
    );
}
