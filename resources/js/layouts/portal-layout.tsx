import type { ReactNode } from 'react';

/**
 * Layout a pantalla completa para el portal del titular (sin sidebar).
 */
export default function PortalLayout({ children }: { children: ReactNode }) {
    return (
        <div className="min-h-dvh bg-[hsl(40_33%_97%)] text-foreground dark:bg-[hsl(30_10%_8%)]">
            {children}
        </div>
    );
}
