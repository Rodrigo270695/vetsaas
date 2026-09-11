import { router } from '@inertiajs/react';
import { Check, Copy, Download, Share2, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { cn } from '@/lib/utils';
import {
    OPEN_PWA_INSTALL_HELP_EVENT,
    isAndroidDevice,
    isIosChromeLike,
    isIosDevice,
    isStandaloneDisplay,
    promptPwaInstall,
    subscribePwaInstallPrompt,
} from '@/lib/pwa-install';

const DISMISS_KEY = 'vetsaas-pwa-install-dismiss-until';
const DISMISS_MS = 24 * 60 * 60 * 1000;

const HIDE_PATH_PREFIXES = [
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/cuenta/cambiar-password',
    '/portal',
] as const;

function shouldHideBanner(pathname: string): boolean {
    const p = pathname.split('?')[0] ?? '';
    return HIDE_PATH_PREFIXES.some(
        (prefix) => p === prefix || p.startsWith(`${prefix}/`),
    );
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

function clearDismissed(): void {
    try {
        localStorage.removeItem(DISMISS_KEY);
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
    const [android, setAndroid] = useState(false);
    const [installing, setInstalling] = useState(false);
    const [copied, setCopied] = useState(false);
    const [forced, setForced] = useState(false);

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

        setIos(isIosDevice());
        setIosChrome(isIosChromeLike());
        setAndroid(isAndroidDevice());

        const unsubNavigate = router.on('navigate', updatePath);
        window.addEventListener('popstate', updatePath);

        const unsub = subscribePwaInstallPrompt((event) => {
            setDeferred(event);
            if (event === null && isStandaloneDisplay()) {
                setDismissed(true);
            }
        });
        const onInstalled = () => {
            setDeferred(null);
            setDismissed(true);
        };
        const onHelp = () => {
            clearDismissed();
            setDismissed(false);
            setForced(true);
        };
        window.addEventListener('appinstalled', onInstalled);
        window.addEventListener(OPEN_PWA_INSTALL_HELP_EVENT, onHelp);

        return () => {
            unsubNavigate();
            window.removeEventListener('popstate', updatePath);
            window.removeEventListener('appinstalled', onInstalled);
            window.removeEventListener(OPEN_PWA_INSTALL_HELP_EVENT, onHelp);
            unsub();
        };
    }, []);

    const onDismiss = useCallback(() => {
        writeDismissed();
        setDismissed(true);
        setForced(false);
    }, []);

    const onInstallClick = useCallback(async () => {
        setInstalling(true);
        try {
            await promptPwaInstall();
        } finally {
            setInstalling(false);
        }
    }, []);

    const copyClinicUrl = useCallback(async () => {
        try {
            await navigator.clipboard.writeText(window.location.origin);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2500);
        } catch {
            setCopied(false);
        }
    }, []);

    if (isStandaloneDisplay()) {
        return null;
    }

    if (!forced && (dismissed || shouldHideBanner(pathname))) {
        return null;
    }

    const showChromiumInstall = Boolean(deferred);
    const showIosHint = ios;
    const showAndroidHint = android && !showChromiumInstall;
    const showHelp = showChromiumInstall || showIosHint || showAndroidHint || forced;

    if (!showHelp) {
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
                    {showIosHint && !showChromiumInstall ? (
                        <Share2 className="size-4" />
                    ) : (
                        <Download className="size-4" />
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    {showChromiumInstall ? (
                        <>
                            <p className="text-sm leading-snug font-semibold text-foreground">
                                Instalar {appLabel} en este celular
                            </p>
                            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                Un toque y queda el ícono en el inicio, como una app.
                            </p>
                            <button
                                type="button"
                                className="mt-2 inline-flex h-10 w-full items-center justify-center rounded-lg bg-[#008064] px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-[#006B52] focus-visible:ring-2 focus-visible:ring-[#008064]/40 focus-visible:outline-none disabled:opacity-60 sm:w-auto"
                                onClick={onInstallClick}
                                disabled={installing}
                            >
                                {installing ? 'Instalando…' : 'Instalar ahora'}
                            </button>
                        </>
                    ) : showIosHint ? (
                        iosChrome ? (
                            <>
                                <p className="text-sm leading-snug font-semibold text-foreground">
                                    En iPhone hay que usar Safari
                                </p>
                                <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                    Chrome / Instagram / WhatsApp no instalan la app. Copiá el enlace,
                                    abrilo en Safari → Compartir → Añadir a pantalla de inicio.
                                </p>
                                <button
                                    type="button"
                                    className="mt-2 inline-flex h-10 items-center gap-1.5 rounded-lg bg-[#008064] px-3 text-sm font-medium text-white"
                                    onClick={() => void copyClinicUrl()}
                                >
                                    {copied ? <Check className="size-4" /> : <Copy className="size-4" />}
                                    {copied ? 'Enlace copiado' : 'Copiar enlace de la clínica'}
                                </button>
                            </>
                        ) : (
                            <>
                                <p className="text-sm leading-snug font-semibold text-foreground">
                                    Instalá {appLabel} en el iPhone
                                </p>
                                <ol className="mt-1.5 list-decimal space-y-1 pl-4 text-xs leading-relaxed text-muted-foreground">
                                    <li>
                                        Tocá <span className="font-semibold text-foreground">Compartir</span> (cuadrado con flecha ↑) abajo en Safari.
                                    </li>
                                    <li>
                                        Bajá y tocá <span className="font-semibold text-foreground">Añadir a pantalla de inicio</span>.
                                    </li>
                                    <li>Confirmá con Añadir. El ícono queda en el escritorio.</li>
                                </ol>
                            </>
                        )
                    ) : (
                        <>
                            <p className="text-sm leading-snug font-semibold text-foreground">
                                Instalá {appLabel} en el celular
                            </p>
                            <ol className="mt-1.5 list-decimal space-y-1 pl-4 text-xs leading-relaxed text-muted-foreground">
                                <li>
                                    En Chrome, tocá el menú{' '}
                                    <span className="font-semibold text-foreground">⋮</span> arriba a la derecha.
                                </li>
                                <li>
                                    Elegí <span className="font-semibold text-foreground">Instalar aplicación</span> o
                                    «Añadir a la pantalla de inicio».
                                </li>
                            </ol>
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

export function PwaInstallHeaderButton() {
    const [show, setShow] = useState(false);

    useEffect(() => {
        setShow(!isStandaloneDisplay());
    }, []);

    if (!show) {
        return null;
    }

    return (
        <button
            type="button"
            className="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-md px-2.5 text-[#008064] hover:bg-[#008064]/10 lg:hidden"
            onClick={() => {
                window.dispatchEvent(new Event(OPEN_PWA_INSTALL_HELP_EVENT));
            }}
            aria-label="Instalar app"
        >
            <Download className="size-4" strokeWidth={2.25} />
            <span className="text-xs font-medium">App</span>
        </button>
    );
}
