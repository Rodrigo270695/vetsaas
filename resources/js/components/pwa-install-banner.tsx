import { Download, Share2, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

const DISMISS_KEY = 'vetsaas-pwa-install-dismiss-until';
const DISMISS_MS = 7 * 24 * 60 * 60 * 1000;

const HIDE_PATH_PREFIXES = [
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/cuenta/cambiar-password',
] as const;

function shouldHideBanner(pathname: string): boolean {
    const p = pathname.split('?')[0] ?? '';
    if (p === '/') {
        return true;
    }
    return HIDE_PATH_PREFIXES.some(
        (prefix) => p === prefix || p.startsWith(`${prefix}/`),
    );
}

function isStandalone(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }
    if (window.matchMedia('(display-mode: standalone)').matches) {
        return true;
    }
    const nav = window.navigator as Navigator & { standalone?: boolean };
    return Boolean(nav.standalone);
}

function isIos(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }
    return (
        /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

function isIosChrome(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }
    return isIos() && /CriOS|EdgiOS|FxiOS/.test(navigator.userAgent);
}

function isAndroid(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }
    return /Android/i.test(navigator.userAgent);
}

function isDesktopChromiumLike(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }
    const ua = navigator.userAgent;
    if (/Mobi|Android|iPhone|iPad|iPod/i.test(ua)) {
        return false;
    }
    return /Chrome|Edg|Chromium|Brave|OPR|Opera/i.test(ua);
}

function readDismissedUntil(): number {
    try {
        const raw = localStorage.getItem(DISMISS_KEY);
        if (!raw) {
            return 0;
        }
        return Number.parseInt(raw, 10) || 0;
    } catch {
        return 0;
    }
}

function writeDismissed(): void {
    try {
        localStorage.setItem(DISMISS_KEY, String(Date.now() + DISMISS_MS));
    } catch {
        /* ignore */
    }
}

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

const appLabel = import.meta.env.VITE_APP_NAME || 'VetSaaS';

export default function PwaInstallBanner() {
    const [pathname, setPathname] = useState(() =>
        typeof window === 'undefined' ? '' : window.location.pathname,
    );
    const [deferred, setDeferred] = useState<BeforeInstallPromptEvent | null>(
        null,
    );
    const [dismissed, setDismissed] = useState(false);
    const [ios, setIos] = useState(false);
    const [iosChrome, setIosChrome] = useState(false);
    const [installing, setInstalling] = useState(false);
    const [androidMenuHint, setAndroidMenuHint] = useState(false);
    const [desktopHint, setDesktopHint] = useState(false);

    useEffect(() => {
        const until = readDismissedUntil();
        if (until > Date.now()) {
            setDismissed(true);
        }
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const updatePath = () => setPathname(window.location.pathname);

        setIos(isIos());
        setIosChrome(isIosChrome());

        window.addEventListener('popstate', updatePath);

        const originalPushState = history.pushState;
        const originalReplaceState = history.replaceState;

        history.pushState = function (...args) {
            const ret = originalPushState.apply(this, args);
            updatePath();
            return ret;
        };

        history.replaceState = function (...args) {
            const ret = originalReplaceState.apply(this, args);
            updatePath();
            return ret;
        };

        const onBip = (e: Event) => {
            e.preventDefault();
            setDeferred(e as BeforeInstallPromptEvent);
        };
        const onInstalled = () => {
            setDeferred(null);
            setDismissed(true);
        };
        window.addEventListener('beforeinstallprompt', onBip);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('popstate', updatePath);
            history.pushState = originalPushState;
            history.replaceState = originalReplaceState;
            window.removeEventListener('beforeinstallprompt', onBip);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined' || isIos() || !isAndroid()) {
            return;
        }
        const t = window.setTimeout(() => setAndroidMenuHint(true), 5000);
        return () => window.clearTimeout(t);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined' || isIos() || isAndroid()) {
            return;
        }
        if (!isDesktopChromiumLike()) {
            return;
        }
        const t = window.setTimeout(() => setDesktopHint(true), 4000);
        return () => window.clearTimeout(t);
    }, []);

    const onDismiss = useCallback(() => {
        writeDismissed();
        setDismissed(true);
    }, []);

    const onInstallClick = useCallback(async () => {
        if (!deferred) {
            return;
        }
        setInstalling(true);
        try {
            await deferred.prompt();
            await deferred.userChoice;
        } finally {
            setInstalling(false);
            setDeferred(null);
        }
    }, [deferred]);

    if (dismissed || isStandalone() || shouldHideBanner(pathname)) {
        return null;
    }

    const showChromiumInstall = Boolean(deferred);
    const showIosHint = ios && !showChromiumInstall;
    const showAndroidHint = !ios && !showChromiumInstall && androidMenuHint;
    const showDesktopHint =
        !ios &&
        !isAndroid() &&
        !showChromiumInstall &&
        desktopHint &&
        isDesktopChromiumLike();

    if (
        !showChromiumInstall &&
        !showIosHint &&
        !showAndroidHint &&
        !showDesktopHint
    ) {
        return null;
    }

    return (
        <div
            className={cn(
                'fixed right-0 bottom-0 left-0 z-40 border-t border-border/70 bg-background/95 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-6px_28px_rgba(0,0,0,0.12)] backdrop-blur-md supports-backdrop-filter:bg-background/90',
                'px-4',
            )}
            role="region"
            aria-label="Instalar aplicación"
        >
            <div className="mx-auto flex max-w-lg items-start gap-3">
                <div
                    className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#008064]/15 text-[#008064]"
                    aria-hidden
                >
                    {showIosHint && !iosChrome ? (
                        <Share2 className="size-4" />
                    ) : (
                        <Download className="size-4" />
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    {showChromiumInstall ? (
                        <>
                            <p className="text-sm leading-snug font-semibold text-foreground">
                                Instalar {appLabel}
                            </p>
                            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                Acceso rápido desde tu pantalla de inicio, como
                                una app.
                            </p>
                            <button
                                type="button"
                                className="mt-2 inline-flex h-9 items-center justify-center rounded-lg bg-[#008064] px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-[#006B52] focus-visible:ring-2 focus-visible:ring-[#008064]/40 focus-visible:outline-none disabled:opacity-60"
                                onClick={onInstallClick}
                                disabled={installing}
                            >
                                {installing ? 'Instalando…' : 'Instalar'}
                            </button>
                        </>
                    ) : showDesktopHint ? (
                        <>
                            <p className="text-sm leading-snug font-semibold text-foreground">
                                Instalar {appLabel} en tu equipo
                            </p>
                            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                Busca el icono de instalar en la barra de
                                direcciones o abre el menú{' '}
                                <span className="font-medium text-foreground">
                                    ⋮
                                </span>{' '}
                                y elige «Instalar aplicación».
                            </p>
                        </>
                    ) : showAndroidHint ? (
                        <>
                            <p className="text-sm leading-snug font-semibold text-foreground">
                                Añade {appLabel} a tu inicio
                            </p>
                            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                Abre el menú{' '}
                                <span className="font-medium text-foreground">
                                    ⋮
                                </span>{' '}
                                y busca «Instalar aplicación» o «Añadir a la
                                pantalla de inicio».
                            </p>
                        </>
                    ) : (
                        <>
                            <p className="text-sm leading-snug font-semibold text-foreground">
                                Añade {appLabel} a tu inicio
                            </p>
                            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                {iosChrome ? (
                                    <>
                                        Abre el menú{' '}
                                        <span className="font-medium text-foreground">
                                            ⋮
                                        </span>{' '}
                                        y elige «Añadir a la pantalla de inicio».
                                    </>
                                ) : (
                                    <>
                                        En Safari, pulsa{' '}
                                        <span className="font-medium text-foreground">
                                            Compartir
                                        </span>{' '}
                                        y «Añadir a la pantalla de inicio».
                                    </>
                                )}
                            </p>
                        </>
                    )}
                </div>
                <button
                    type="button"
                    className="-mt-1 -mr-1 flex size-9 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-[#008064]/30 focus-visible:outline-none"
                    onClick={onDismiss}
                    aria-label="Cerrar aviso de instalación"
                >
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}
