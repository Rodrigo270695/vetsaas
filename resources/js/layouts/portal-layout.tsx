import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function PortalLayout({ children }: { children: ReactNode }) {
    return (
        <>
            <Head>
                <meta
                    head-key="viewport"
                    name="viewport"
                    content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content"
                />
                <meta name="apple-mobile-web-app-capable" content="yes" />
                <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
                <meta name="mobile-web-app-capable" content="yes" />
            </Head>
            <div className="min-h-dvh overflow-x-hidden bg-[#f3f7f6] text-foreground antialiased [-webkit-tap-highlight-color:transparent] [&_a]:cursor-pointer [&_button]:cursor-pointer dark:bg-slate-950">
                {children}
            </div>
        </>
    );
}
