import type { ReactNode } from 'react';
import { LegalModal, useLegalModal } from '@/components/legal/legal-modal';
import type { LegalDocId } from '@/components/legal/legal-docs';

type AuthFooterProps = {
    brandName: string;
};

/**
 * Pie discreto con copyright + enlaces legales (modal) y soporte.
 */
export default function AuthFooter({ brandName }: AuthFooterProps) {
    const year = new Date().getFullYear();
    const legal = useLegalModal();

    return (
        <>
            <footer className="relative z-10 mx-auto flex w-full max-w-5xl flex-col items-center gap-2 px-6 pb-6 text-center text-xs text-muted-foreground/80 sm:flex-row sm:justify-between sm:pb-8">
                <span>
                    © {year} {brandName} · Hecho en Perú
                </span>
                <span className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                    <FooterButton onClick={() => legal.openDoc('terminos')}>Términos</FooterButton>
                    <span aria-hidden="true">·</span>
                    <FooterButton onClick={() => legal.openDoc('privacidad')}>
                        Privacidad
                    </FooterButton>
                    <span aria-hidden="true">·</span>
                    <FooterButton onClick={() => legal.openDoc('datos')}>Datos</FooterButton>
                    <span aria-hidden="true">·</span>
                    <FooterButton onClick={() => legal.openDoc('cookies')}>Cookies</FooterButton>
                    <span aria-hidden="true">·</span>
                    <FooterButton onClick={() => legal.openDoc('seguridad')}>Seguridad</FooterButton>
                    <span aria-hidden="true">·</span>
                    <FooterButton onClick={() => legal.openDoc('soporte')}>Soporte</FooterButton>
                </span>
            </footer>
            <LegalModal
                open={legal.open}
                onOpenChange={legal.setOpen}
                docId={legal.docId}
                onDocIdChange={legal.setDocId}
            />
        </>
    );
}

function FooterButton({
    onClick,
    children,
}: {
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="cursor-pointer rounded-sm transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            {children}
        </button>
    );
}
