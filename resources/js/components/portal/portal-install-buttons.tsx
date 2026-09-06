import { Download, Share2, Smartphone, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    isAndroidDevice,
    isIosChromeLike,
    isIosDevice,
    isStandaloneDisplay,
    promptPwaInstall,
    subscribePwaInstallPrompt,
} from '@/lib/pwa-install';

export function PortalInstallButtons() {
    const { t } = useTranslation('portal-propietario');
    const [canPrompt, setCanPrompt] = useState(false);
    const [installed, setInstalled] = useState(false);
    const [busy, setBusy] = useState(false);
    const [howTo, setHowTo] = useState(false);

    useEffect(() => {
        setInstalled(isStandaloneDisplay());
        const unsub = subscribePwaInstallPrompt((event) => {
            setCanPrompt(Boolean(event));
        });
        const onInstalled = () => setInstalled(true);
        window.addEventListener('appinstalled', onInstalled);
        return () => {
            unsub();
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    const install = useCallback(async () => {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            const result = await promptPwaInstall();
            if (result === 'unavailable') {
                setHowTo(true);
            }
        } finally {
            setBusy(false);
        }
    }, [busy]);

    if (installed) {
        return null;
    }

    const btn =
        'inline-flex h-12 min-h-11 cursor-pointer items-center justify-center gap-2 rounded-2xl px-3 text-xs font-semibold shadow-sm transition hover:brightness-105 md:h-10 md:rounded-full md:text-sm';

    const howToText = isIosDevice()
        ? isIosChromeLike()
            ? t('home.install_how_ios_chrome')
            : t('home.install_how_ios')
        : isAndroidDevice()
          ? t('home.install_how_android')
          : t('home.install_how_desktop');

    return (
        <div className="flex min-w-0 flex-1 flex-col gap-1 md:flex-none">
            <div className="flex min-w-0 items-center gap-2">
                <button
                    type="button"
                    onClick={() => void install()}
                    disabled={busy}
                    className={`${btn} flex-1 bg-brand-600 text-white md:flex-none`}
                >
                    {isIosDevice() ? (
                        <Share2 className="size-4 shrink-0" />
                    ) : canPrompt || isAndroidDevice() ? (
                        <Smartphone className="size-4 shrink-0" />
                    ) : (
                        <Download className="size-4 shrink-0" />
                    )}
                    <span className="md:hidden">{t('home.install_short')}</span>
                    <span className="hidden md:inline">
                        {isIosDevice()
                            ? t('home.install_ios')
                            : isAndroidDevice()
                              ? t('home.install_android')
                              : t('home.install_desktop')}
                    </span>
                </button>
            </div>

            {howTo ? (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 md:items-center">
                    <div className="w-full max-w-sm rounded-3xl bg-white p-5 shadow-2xl dark:bg-slate-900">
                        <div className="mb-3 flex items-start justify-between gap-3">
                            <p className="text-base font-bold text-brand-800 dark:text-brand-200">
                                {t('home.install_how_title')}
                            </p>
                            <button
                                type="button"
                                className="inline-flex size-9 cursor-pointer items-center justify-center rounded-full hover:bg-slate-100 dark:hover:bg-white/10"
                                onClick={() => setHowTo(false)}
                                aria-label="Cerrar"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                        <p className="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            {howToText}
                        </p>
                        <button
                            type="button"
                            className="mt-4 h-11 w-full cursor-pointer rounded-2xl bg-brand-600 text-sm font-semibold text-white"
                            onClick={() => setHowTo(false)}
                        >
                            {t('home.install_how_ok')}
                        </button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
