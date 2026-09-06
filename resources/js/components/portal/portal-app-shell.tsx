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
    showDock = true,
    children,
}: {
    clinicName: string;
    clinicLogo: string | null;
    greeting: string;
    logoutUrl: string;
    push: Push;
    showDock?: boolean;
    children: ReactNode;
}) {
    const { t } = useTranslation('portal-propietario');

    return (
        <div className="flex min-h-dvh flex-col bg-[#eef5f3] dark:bg-slate-950">
            <header className="sticky top-0 z-30 bg-white/75 pt-[env(safe-area-inset-top)] backdrop-blur-2xl dark:bg-slate-950/75">
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-2 px-3 sm:h-16 sm:px-6">
                    <div className="flex min-w-0 items-center gap-2.5">
                        {clinicLogo ? (
                            <img
                                src={clinicLogo}
                                alt=""
                                className="h-8 w-auto object-contain sm:h-9"
                            />
                        ) : (
                            <PawPrint className="size-7 shrink-0 text-teal-600" />
                        )}
                        <div className="min-w-0">
                            <p className="truncate text-[10px] font-bold tracking-[0.14em] text-teal-800 uppercase dark:text-teal-200">
                                {clinicName}
                            </p>
                            <p className="truncate text-[13px] font-semibold text-slate-700 dark:text-slate-200">
                                {greeting}
                            </p>
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-1.5">
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
                            className="inline-flex size-11 cursor-pointer items-center justify-center rounded-full bg-slate-100/90 text-slate-800 ring-1 ring-slate-200/70 transition hover:bg-slate-200 dark:bg-white/10 dark:text-white dark:ring-white/10"
                            onClick={() => router.post(logoutUrl)}
                        >
                            <LogOut className="size-4" />
                        </button>
                    </div>
                </div>
            </header>

            <div
                className={`flex-1 ${showDock ? 'pb-[calc(5.1rem+env(safe-area-inset-bottom))] md:pb-8' : 'pb-6'}`}
            >
                {children}
            </div>

            {showDock ? (
                <div className="fixed inset-x-0 bottom-0 z-30 px-3 pb-[max(0.55rem,env(safe-area-inset-bottom))] pt-1 md:hidden">
                    <div className="rounded-[1.4rem] border border-white/60 bg-white/90 p-1.5 shadow-[0_-8px_32px_rgba(15,50,40,0.12)] backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/90">
                        <div className="mx-auto mb-1.5 h-1 w-10 rounded-full bg-slate-300/80 dark:bg-white/20" />
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
            ) : null}
        </div>
    );
}
