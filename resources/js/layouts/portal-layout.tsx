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
            <div className="portal-skin h-dvh overflow-x-hidden overflow-y-auto overscroll-none bg-brand-50 text-foreground antialiased scrollbar-hidden [-webkit-tap-highlight-color:transparent] [&_a]:cursor-pointer [&_button]:cursor-pointer dark:bg-slate-950">
                {children}
            </div>
        </>
    );
}
