import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { VETSAAS_DEFAULT_LOGO } from '@/lib/brand';

const MIN_VISIBLE_MS = 320;

function isLoginPost(method: string, url: string | URL): boolean {
    if (method.toLowerCase() !== 'post') {
        return false;
    }

    const path = (typeof url === 'string' ? new URL(url, window.location.origin) : url)
        .pathname.replace(/\/+$/, '');

    return path === '/login' || path.endsWith('/login');
}

/**
 * Overlay de sesión: logo VetSaaS con un arco que recorre el borde.
 * Vive en `app.tsx` para no desmontarse al pasar del login al dashboard.
 */
export default function AuthLoginLoader() {
    const { t } = useTranslation('auth');
    const [visible, setVisible] = useState(false);
    const shownAt = useRef(0);
    const hideTimer = useRef<number>(0);
    const pending = useRef(false);

    useEffect(() => {
        const show = () => {
            window.clearTimeout(hideTimer.current);
            pending.current = true;
            shownAt.current = Date.now();
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

    if (!visible || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            role="status"
            aria-live="polite"
            aria-busy="true"
            className="fixed inset-0 z-[9999] flex flex-col items-center justify-center gap-5 bg-background/72 backdrop-blur-md dark:bg-background/80"
        >
            <div className="relative size-[7.25rem]">
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
                        strokeWidth="2.25"
                        className="opacity-20"
                    />
                    <circle
                        cx="50"
                        cy="50"
                        r="46"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2.5"
                        strokeLinecap="round"
                        className="auth-logo-loader-orbit drop-shadow-[0_0_8px_var(--brand-400)]"
                    />
                </svg>
                <div className="absolute inset-[11%] overflow-hidden rounded-full bg-zinc-950 shadow-[0_0_0_1px_rgb(255_255_255/0.08)]">
                    <img
                        src={VETSAAS_DEFAULT_LOGO}
                        alt=""
                        className="size-full object-cover"
                    />
                </div>
            </div>
            <p className="text-sm font-medium tracking-wide text-foreground">
                {t('login.entering')}
            </p>
        </div>,
        document.body,
    );
}
