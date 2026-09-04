import type { ReactNode } from 'react';

/**
 * Layout mínimo para documentos públicos firmados (sin sidebar ni login).
 */
export default function PublicDocumentLayout({ children }: { children: ReactNode }) {
    return (
        <div className="min-h-dvh bg-[#ebe6dc] text-foreground dark:bg-[#2a2723]">
            <div className="mx-auto w-full max-w-2xl px-3 py-6 sm:px-4 sm:py-10">{children}</div>
        </div>
    );
}
