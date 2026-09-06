import { Download, Share2, Smartphone } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
};

function isStandalone(): boolean {
    if (window.matchMedia('(display-mode: standalone)').matches) {
        return true;
    }
    return Boolean((window.navigator as Navigator & { standalone?: boolean }).standalone);
}

function isIos(): boolean {
    return (
        /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

function isAndroid(): boolean {
    return /Android/i.test(navigator.userAgent);
}

export function PortalInstallButtons() {
    const { t } = useTranslation('portal-propietario');
    const [promptEvent, setPromptEvent] = useState<BeforeInstallPromptEvent | null>(null);
    const [installed, setInstalled] = useState(false);
    const [iosHint, setIosHint] = useState(false);

    useEffect(() => {
        setInstalled(isStandalone());
        const onPrompt = (e: Event) => {
            e.preventDefault();
            setPromptEvent(e as BeforeInstallPromptEvent);
        };
        window.addEventListener('beforeinstallprompt', onPrompt);
        window.addEventListener('appinstalled', () => setInstalled(true));
        return () => window.removeEventListener('beforeinstallprompt', onPrompt);
    }, []);

    const install = useCallback(async () => {
        if (promptEvent) {
            await promptEvent.prompt();
            return;
        }
        if (isIos()) {
            setIosHint(true);
        }
    }, [promptEvent]);

    if (installed) {
        return null;
    }

    const btn =
        'inline-flex h-12 min-h-11 cursor-pointer items-center justify-center gap-2 rounded-2xl px-3 text-xs font-semibold shadow-sm transition hover:brightness-105 md:h-10 md:rounded-full md:text-sm';

    return (
        <div className="flex min-w-0 flex-1 flex-col gap-1 md:flex-none">
            <div className="flex min-w-0 items-center gap-2">
                {(isAndroid() || promptEvent) && (
                    <button
                        type="button"
                        onClick={() => void install()}
                        className={`${btn} flex-1 bg-[#3ddc84] text-[#053b1f] md:flex-none`}
                    >
                        <Smartphone className="size-4 shrink-0" />
                        <span className="md:hidden">{t('home.install_short')}</span>
                        <span className="hidden md:inline">{t('home.install_android')}</span>
                    </button>
                )}
                {isIos() && (
                    <button
                        type="button"
                        onClick={() => setIosHint(true)}
                        className={`${btn} flex-1 bg-[#007aff] text-white md:flex-none`}
                    >
                        <Share2 className="size-4 shrink-0" />
                        <span className="md:hidden">{t('home.install_short')}</span>
                        <span className="hidden md:inline">{t('home.install_ios')}</span>
                    </button>
                )}
                {!isAndroid() && !isIos() && (
                    <button
                        type="button"
                        onClick={() => void install()}
                        className={`${btn} flex-1 bg-slate-800 text-white md:flex-none dark:bg-slate-100 dark:text-slate-900`}
                    >
                        <Download className="size-4 shrink-0" />
                        <span className="md:hidden">{t('home.install_short')}</span>
                        <span className="hidden md:inline">{t('home.install_desktop')}</span>
                    </button>
                )}
            </div>
            {iosHint ? (
                <p className="max-w-56 text-[11px] leading-snug text-muted-foreground sm:max-w-none">
                    {t('home.install_ios_hint')}
                </p>
            ) : null}
        </div>
    );
}
