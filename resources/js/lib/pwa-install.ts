type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

type Listener = (event: BeforeInstallPromptEvent | null) => void;

let deferred: BeforeInstallPromptEvent | null = null;
const listeners = new Set<Listener>();
let capturing = false;

function emit(): void {
    listeners.forEach((fn) => fn(deferred));
}

export function capturePwaInstallPrompt(): void {
    if (typeof window === 'undefined' || capturing) {
        return;
    }
    capturing = true;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferred = e as BeforeInstallPromptEvent;
        emit();
    });

    window.addEventListener('appinstalled', () => {
        deferred = null;
        emit();
    });
}

export function getDeferredPwaPrompt(): BeforeInstallPromptEvent | null {
    return deferred;
}

export function subscribePwaInstallPrompt(listener: Listener): () => void {
    listeners.add(listener);
    listener(deferred);
    return () => {
        listeners.delete(listener);
    };
}

export async function promptPwaInstall(): Promise<'accepted' | 'dismissed' | 'unavailable'> {
    if (!deferred) {
        return 'unavailable';
    }
    const event = deferred;
    await event.prompt();
    const { outcome } = await event.userChoice;
    deferred = null;
    emit();
    return outcome;
}

export function isStandaloneDisplay(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }
    if (window.matchMedia('(display-mode: standalone)').matches) {
        return true;
    }
    return Boolean((window.navigator as Navigator & { standalone?: boolean }).standalone);
}

export function isIosDevice(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }
    return (
        /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

export function isIosChromeLike(): boolean {
    return isIosDevice() && /CriOS|EdgiOS|FxiOS/.test(navigator.userAgent);
}

export function isAndroidDevice(): boolean {
    return typeof navigator !== 'undefined' && /Android/i.test(navigator.userAgent);
}

export const OPEN_PWA_INSTALL_HELP_EVENT = 'vetsaas:pwa-install-help';

export function openPwaInstallHelp(): void {
    if (typeof window === 'undefined') {
        return;
    }
    window.dispatchEvent(new Event(OPEN_PWA_INSTALL_HELP_EVENT));
}
