import type { ReactNode } from 'react';

/**
 * Layout mínimo para documentos públicos firmados (sin sidebar ni login).
 */
export default function PublicDocumentLayout({ children }: { children: ReactNode }) {
    return (
        <div className="min-h-dvh bg-linear-to-b from-sky-50/80 via-background to-background text-foreground dark:from-sky-950/30">
            <div className="mx-auto w-full max-w-3xl px-3 py-4 sm:px-4 sm:py-6">{children}</div>
        </div>
    );
}
