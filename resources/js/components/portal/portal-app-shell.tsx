import { router } from '@inertiajs/react';
import { LogOut, PawPrint } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { PortalInstallButtons } from '@/components/portal/portal-install-buttons';
import { PortalPushToggle } from '@/components/portal/portal-push-toggle';
import { PortalThemeToggle } from '@/components/portal/portal-theme-toggle';

type Push = {
    enabled: boolean;
    vapid: string;
    subscribe_url: string;
};

export function PortalAppShell({
    clinicName,
    clinicLogo,
    greeting,
    logoutUrl,
    push,
    children,
}: {
    clinicName: string;
    clinicLogo: string | null;
    greeting: string;
    logoutUrl: string;
    push: Push;
    children: ReactNode;
}) {
    const { t } = useTranslation('portal-propietario');

    return (
        <div className="flex min-h-dvh flex-col bg-[#f3f7f6] dark:bg-slate-950">
            <header
                className="sticky top-0 z-30 border-b border-black/5 bg-white/90 pt-[env(safe-area-inset-top)] backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/90"
            >
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-2 px-3 sm:h-16 sm:px-6">
                    <div className="flex min-w-0 items-center gap-2.5">
                        {clinicLogo ? (
                            <img src={clinicLogo} alt="" className="h-8 w-auto object-contain sm:h-9" />
                        ) : (
                            <PawPrint className="size-7 shrink-0 text-teal-600" />
                        )}
                        <div className="min-w-0">
                            <p className="truncate text-[11px] font-bold tracking-wide text-teal-800 uppercase dark:text-teal-200">
                                {clinicName}
                            </p>
                            <p className="truncate text-sm font-medium text-slate-600 dark:text-slate-300">
                                {greeting}
                            </p>
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <div className="hidden items-center gap-2 md:flex">
                            <PortalInstallButtons />
                            <PortalPushToggle
                                enabled={push.enabled}
                                vapid={push.vapid}
                                subscribeUrl={push.subscribe_url}
                            />
                        </div>
                        <PortalThemeToggle />
                        <button
                            type="button"
                            aria-label={t('home.logout')}
                            className="inline-flex size-11 cursor-pointer items-center justify-center rounded-full bg-slate-100 text-slate-800 ring-1 ring-slate-200/80 transition hover:bg-slate-200 dark:bg-white/10 dark:text-white dark:ring-white/10"
                            onClick={() => router.post(logoutUrl)}
                        >
                            <LogOut className="size-4" />
                        </button>
                    </div>
                </div>
            </header>

            <div className="flex-1 pb-[calc(4.75rem+env(safe-area-inset-bottom))] md:pb-8">{children}</div>

            <div className="fixed inset-x-0 bottom-0 z-30 border-t border-black/5 bg-white/95 px-3 pt-2 pb-[max(0.65rem,env(safe-area-inset-bottom))] backdrop-blur-xl md:hidden dark:border-white/10 dark:bg-slate-950/95">
                <div className="flex items-stretch gap-2">
                    <PortalInstallButtons />
                    <PortalPushToggle
                        enabled={push.enabled}
                        vapid={push.vapid}
                        subscribeUrl={push.subscribe_url}
                    />
                </div>
            </div>
        </div>
    );
}
