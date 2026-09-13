import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { VETSAAS_DEFAULT_LOGO } from '@/lib/brand';

const MIN_VISIBLE_MS = 480;
const STEP_KEYS = [
    'login.entering_step_1',
    'login.entering_step_2',
    'login.entering_step_3',
] as const;

const NODES = [
    { x: 18, y: 28 },
    { x: 82, y: 22 },
    { x: 88, y: 58 },
    { x: 70, y: 86 },
    { x: 28, y: 84 },
    { x: 12, y: 54 },
    { x: 48, y: 12 },
    { x: 56, y: 90 },
] as const;

function isLoginPost(method: string, url: string | URL): boolean {
    if (method.toLowerCase() !== 'post') {
        return false;
    }

    const path = (typeof url === 'string' ? new URL(url, window.location.origin) : url)
        .pathname.replace(/\/+$/, '');

    return path === '/login' || path.endsWith('/login');
}

/**
 * Overlay de ingreso: mismos rayos/radar en claro y oscuro.
 * El PNG del logo tiene fondo negro: en oscuro se funde (screen);
 * en claro se recorta por luminancia y se pinta con el color de marca.
 */
export default function AuthLoginLoader() {
    const { t } = useTranslation('auth');
    const [visible, setVisible] = useState(false);
    const [step, setStep] = useState(0);
    const shownAt = useRef(0);
    const hideTimer = useRef<number>(0);
    const pending = useRef(false);

    useEffect(() => {
        const show = () => {
            window.clearTimeout(hideTimer.current);
            pending.current = true;
            shownAt.current = Date.now();
            setStep(0);
            setVisible(true);
        };

        const hide = () => {
            if (!pending.current) {
                return;
            }
            pending.current = false;
            const wait = Math.max(0, MIN_VISIBLE_MS - (Date.now() - shownAt.current));
            hideTimer.current = window.setTimeout(() => setVisible(false), wait);
        };

        const offStart = router.on('start', (event) => {
            if (isLoginPost(event.detail.visit.method, event.detail.visit.url)) {
                show();
            }
        });
        const offFinish = router.on('finish', hide);
        const offCancel = router.on('cancel', hide);

        return () => {
            offStart();
            offFinish();
            offCancel();
            window.clearTimeout(hideTimer.current);
        };
    }, []);

    useEffect(() => {
        if (!visible) {
            return;
        }

        const id = window.setInterval(() => {
            setStep((current) => (current + 1) % STEP_KEYS.length);
        }, 900);

        return () => window.clearInterval(id);
    }, [visible]);

    if (!visible || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            role="status"
            aria-live="polite"
            aria-busy="true"
            className="auth-login-world fixed inset-0 z-[9999] overflow-hidden bg-background/82 backdrop-blur-2xl"
        >
            <div aria-hidden className="auth-login-world-veil" />
            <div aria-hidden className="auth-login-world-grid" />
            <div aria-hidden className="auth-login-world-glow" />
            <div aria-hidden className="auth-login-world-scan" />

            <div className="relative z-10 flex flex-col items-center px-6">
                <div className="relative size-44 sm:size-52">
                    <svg
                        className="absolute inset-[-18%] size-[136%] text-primary"
                        viewBox="0 0 100 100"
                        aria-hidden
                    >
                        {NODES.map((node, index) => {
                            const next = NODES[(index + 3) % NODES.length];
                            if (!next) {
                                return null;
                            }

                            return (
                                <line
                                    key={`l-${index}`}
                                    x1={node.x}
                                    y1={node.y}
                                    x2={next.x}
                                    y2={next.y}
                                    stroke="currentColor"
                                    strokeWidth="0.35"
                                    className="auth-login-world-link"
                                />
                            );
                        })}
                        {NODES.map((node, index) => (
                            <circle
                                key={`n-${index}`}
                                cx={node.x}
                                cy={node.y}
                                r="1.15"
                                fill="currentColor"
                                className="auth-login-world-node"
                                style={{ animationDelay: `${index * 0.12}s` }}
                            />
                        ))}
                    </svg>

                    <span className="auth-login-world-ring auth-login-world-ring-a" />
                    <span className="auth-login-world-ring auth-login-world-ring-b" />

                    <svg
                        className="absolute inset-0 size-full -rotate-90 text-primary"
                        viewBox="0 0 100 100"
                        aria-hidden
                    >
                        <circle
                            cx="50"
                            cy="50"
                            r="46"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="0.7"
                            className="opacity-25"
                        />
                        <circle
                            cx="50"
                            cy="50"
                            r="46"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            className="auth-logo-loader-orbit"
                        />
                    </svg>

                    <span
                        aria-hidden
                        className="auth-login-world-mark auth-login-world-mark-mask pointer-events-none absolute inset-[14%] size-[72%] dark:hidden"
                    />
                    <img
                        src={VETSAAS_DEFAULT_LOGO}
                        alt=""
                        className="auth-login-world-mark auth-login-world-mark-photo pointer-events-none absolute inset-[14%] hidden size-[72%] object-contain dark:block"
                    />
                </div>

                <p className="mt-8 text-center text-base font-medium tracking-wide text-foreground">
                    {t('login.entering')}
                </p>
                <p className="mt-1.5 min-h-5 text-center text-xs tracking-wide text-primary/70">
                    {t(STEP_KEYS[step] ?? STEP_KEYS[0])}
                </p>
            </div>
        </div>,
        document.body,
    );
}
