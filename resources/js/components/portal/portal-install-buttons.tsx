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

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap gap-2">
                {(isAndroid() || promptEvent) && (
                    <button
                        type="button"
                        onClick={() => void install()}
                        className="inline-flex items-center gap-2 rounded-full bg-[#3ddc84] px-4 py-2.5 text-sm font-semibold text-[#053b1f] shadow-sm"
                    >
                        <Smartphone className="size-4" />
                        {t('home.install_android')}
                    </button>
                )}
                {isIos() && (
                    <button
                        type="button"
                        onClick={() => setIosHint(true)}
                        className="inline-flex items-center gap-2 rounded-full bg-[#007aff] px-4 py-2.5 text-sm font-semibold text-white shadow-sm"
                    >
                        <Share2 className="size-4" />
                        {t('home.install_ios')}
                    </button>
                )}
                {!isAndroid() && !isIos() && (
                    <button
                        type="button"
                        onClick={() => void install()}
                        className="inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm"
                    >
                        <Download className="size-4" />
                        {t('home.install_desktop')}
                    </button>
                )}
            </div>
            {iosHint ? (
                <p className="text-xs text-muted-foreground">{t('home.install_ios_hint')}</p>
            ) : null}
        </div>
    );
}
